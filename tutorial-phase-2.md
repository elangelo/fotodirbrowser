# Phase 2 tutorial: browsing parity

A companion to [plan.md](plan.md), Phase 2; the design is in plan §3.3 (thumbnails) and §3.4 (API). It assumes you've done [Phase 1](tutorial-phase-1.md).

**By the end:**
- The frontend talks only to the new API, and `/api/dir` is served from the index.
- Thumbnails are real: generated once, cached on disk, cached by the browser.
- Originals and videos stream with Range support, so seeking works.
- `?path=…&media=…` links open the lightbox directly.
- The Phase 0 throwaway endpoints are gone.

**Go ideas you'll meet:** designing JSON responses (tags, `omitempty`/`omitzero`, nil slices), mapping errors to HTTP status codes, an error type with `errors.As`, `http.ServeContent` (Range and conditional requests), `mime`, safe file access with `os.Root`, external programs with timeouts, atomic file writes, bounded concurrency with deduplication, `context.WithoutCancel`, interfaces declared by the consumer, a buffered channel as a queue, `httptest`.

**Before you start:** `sudo apt install libvips-tools jq`. That's `vipsthumbnail` for the thumbnails and `jq` for reading JSON in the terminal.

## Step 2.1: Response types and helpers

**Concepts:**
- **Design the JSON first** (the contract in plan §3.4), then write Go structs that match it. Field names are `camelCase` in JSON via struct tags; the Go fields stay `PascalCase`.
- **Optional values:**
  - A nil `*float64` encodes as `null`.
  - `omitempty` leaves out zero values: `0`, `""`, `nil` and empty slices.
  - `omitzero` (Go 1.24+) leaves out a field only when it holds its type's zero value, which also works for structs such as `time.Time`.

  Choose per field what the frontend expects.
- **Lists are `[]`, never `null`:** create them with `make([]T, 0, n)`.
- **One function writes JSON:** `func writeJSON(w http.ResponseWriter, status int, v any)` sets `Content-Type`, writes the status and encodes. `any` is another name for `interface{}`, roughly `object`.
- **One place maps errors to status codes:**
  - Handlers return an `error`.
  - A small adapter turns `store.ErrNotFound` into 404, your own "bad request" error type into 400, and anything else into a logged 500.
  - Give the error type its own struct:
    ```go
    type httpError struct {
    	status int
    	msg    string
    }

    func (e *httpError) Error() string { return e.msg }
    ```
  - In the adapter, `var he *httpError; if errors.As(err, &he) { … }` finds it even when it has been wrapped.
  - The adapter itself is the `http.HandlerFunc` trick from Phase 0 again, now with an error result: `type handler func(http.ResponseWriter, *http.Request) error`, plus a `ServeHTTP` method on that type.
- **Dependency injection without a container:** an `API` struct holds what the handlers need, and `main.go` builds it:
  ```go
  type API struct {
  	store   *store.Store
  	scanner *scan.Scanner
  	thumbs  *thumbs.Cache // step 2.4
  	root    *os.Root
  }

  func (a *API) Routes(mux *http.ServeMux)
  ```

**Do:** in `internal/api`, write:
- the response structs: `DirResponse`, `Crumb`, `FolderJSON` and `MediaJSON`;
- `writeJSON`;
- the error type and the adapter;
- the `API` struct.

Keep the Phase 0 handlers registered until step 2.7.

✅ A test: a handler that returns `store.ErrNotFound` produces a 404 with a JSON error body. A wrapped `&httpError{400, "bad path"}` produces a 400.

## Step 2.2: `GET /api/dir`

**Concepts:**
- **Clean, then look up.** Require a leading `/`, then clean the path: `path.Clean("/2020/../etc")` is `"/etc"`. Then look it up in the database.
  - The database is your allowlist: no folder row means 404, and the path never touches the disk.
  - `os.Root` still guards the disk access that happens later.
- **Lazy reconcile.** Before reading, call `scanner.EnsureFresh(ctx, path)`.
  - It compares the folder's mtime on disk with the stored one, and runs `ReconcileFolder` only if they differ.
  - If the background scan happens to be in the same folder, `flight.Group` makes the two share one reconcile.
  - If it fails, log the error and still serve what the database has.
- **Pure functions are easy to test.** Building breadcrumbs from a path needs no database: move your Phase 0 function here.
- **Previous/next siblings:** one query for the parent folder's children, ordered by name, then `slices.IndexFunc` to find the current folder. If you like SQL, the window functions `LAG()`/`LEAD()` can do this inside the query.
- **Never put request input into SQL text.** Map `sort` to one of two fixed `ORDER BY` clauses:
  - `taken`: `taken_at, name COLLATE NOCASE`;
  - `name`: `name COLLATE NOCASE`.

  Anything else is a 400.
- **Versioned thumbnail URLs** (plan §3.3). Each media item carries ready-made `thumb` and `preview` URLs that include its mtime: `/api/thumb/812/thumb?v=1577870000`. Step 2.5 tells browsers to cache thumbnails permanently. That's only safe because an edited photo gets a new URL.

**Do:**
1. Add `EnsureFresh` to `scan`.
2. Add store queries for the folder view:
   - the folder by path;
   - its visible child folders (not hidden, not missing) with a media count each;
   - its visible media;
   - the parent's children, for previous/next;
   - folder tags (empty until Phase 4, but the field is there).
3. Write the handler and register it as `GET /api/dir`.

✅ Five checks:
- `curl -s 'localhost:3000/api/dir?path=/2020/2020-01-01' | jq` shows breadcrumbs, previous/next, and media with `id`, `width`, `height` and `thumb`.
- `?path=/nope` returns 404, and `?path=2020` (no leading slash) returns 400.
- Copy a new photo into a folder and reload it: the photo appears without waiting for the periodic scan.
- **Handler test:** build a fixture with a temp media directory, a temp store and `FullScan` using the fake probe from Phase 1. Then call the handler with `httptest.NewRecorder()`, `httptest.NewRequest("GET", "/api/dir?path=/a", nil)` and `mux.ServeHTTP(rec, req)`.
- **Breadcrumb test:** the breadcrumb function keeps its own table-driven test.

## Step 2.3: `GET /api/file/{id}`

**Concepts:**
- **`http.ServeContent(w, r, name, modtime, f)`** works with any `io.ReadSeeker`. It implements:
  - `Range` requests, answered with 206: what makes video seeking work;
  - `If-Modified-Since`, answered with 304;
  - `HEAD`.

  It replaces all of `VideoStream.php`.
- **Content types:** `ServeContent` picks one from the file name via `mime.TypeByExtension`.
  - Go's built-in table has `.jpg` and `.png`, but not `.mp4`, `.m4v` or `.mov` unless the OS provides `/etc/mime.types`, and the slim Docker image doesn't.
  - Register them once at startup: `mime.AddExtensionType(".mp4", "video/mp4")`, `".m4v"` → `video/x-m4v`, `".mov"` → `video/quicktime`.
- **Download file names:**
  - `mime.FormatMediaType("attachment", map[string]string{"filename": name})` builds a correct `Content-Disposition` header, including quoting and non-ASCII names. For example, `kona process été.jpg` becomes `attachment; filename*=utf-8''kona%20process%20%C3%A9t%C3%A9.jpg`.
  - Never build this header by concatenating strings.
- **`r.PathValue("id")` with `strconv.ParseInt`:** a malformed id is the client's mistake, so return 400, not 500.

**Do:**
1. Look up the row with `MediaByID`, which joins in the folder path.
2. Open the file through `os.Root`.
3. When `?download=1`, set `Content-Disposition`.
4. Call `ServeContent` with the file's mtime.

✅ From the shell:
```bash
curl -s -o /dev/null -w '%{http_code}\n' -H 'Range: bytes=0-99' localhost:3000/api/file/1   # 206
curl -sI 'localhost:3000/api/file/1?download=1' | grep -i content-disposition
```
A handler test asserts the 206 and `Content-Range: bytes 0-99/<size>`.

## Step 2.4: The thumbnail cache (`internal/thumbs`)

**Try the tools by hand first.** It's quicker to learn their behavior in a shell than through Go:

```bash
vipsthumbnail docs/example/2020/2020-01-01/black-sedan-parked-under-tree-1841120.jpg \
  --size x600 -o /tmp/t.jpg[Q=82]
ffmpeg -v error -y -ss 2 -i some-video.mp4 -frames:v 1 -vf scale=-2:600 /tmp/v.jpg
```

Look at the results: size, orientation, file size. Run `vipsthumbnail --help`. If your version treats `-o` paths differently than shown here, write the Go code to match what you observe.

**Concepts:**
- **The cache key** is `sha1(relpath|filesize|mtime)` in hex. `crypto/sha1` is fine here: it's a cache key, not security.
  - Shard the files into `thumbs/<size>/<first 2 hex chars>/<key>.jpg`, so no single directory holds 100k files.
  - The key doubles as the ETag.
- **Atomic writes:**
  1. Generate into a temp name *in the same directory*: `os.CreateTemp(dir, ".tmp-*.jpg")`. Close it right away; the tool overwrites it.
  2. `os.Rename` it to the final name. A rename within one filesystem is atomic, so readers see either no file or a complete one.
  3. On any error, `os.Remove` the temp file.
- **Timeouts for external programs:**
  - `ctx, cancel := context.WithTimeout(ctx, 2*time.Minute)`, then `defer cancel()`, then `exec.CommandContext(ctx, …)`.
  - Use `cmd.CombinedOutput()`, so your error message includes whatever the tool printed.
- **Bounded and deduplicated:**
  - A semaphore channel of size `THUMB_WORKERS` limits how many generations run at once.
  - `flight.Group[string]`, keyed by cache key, makes 30 simultaneous requests for the same thumbnail generate it only once.
- **`context.WithoutCancel`** (built into your `flight.Group`). Suppose several requests wait for one generation and the first one's browser tab closes.
  - Its context is cancelled, but the generation must continue for the others.
  - `fn` runs with `WithoutCancel`, which keeps values but drops cancellation; add its own timeout on top.
  - Each *waiter* still stops waiting when its own context is cancelled.
- **Function values make it testable.** The cache holds a `generate func(ctx context.Context, src Source, size Size, dst string) error`.
  - Production uses one that runs vipsthumbnail or ffmpeg.
  - Tests inject a fake that writes a few bytes, counts calls and can sleep.

**Do:**
```go
type Size string

const (
	Thumb   Size = "thumb"   // 600 px tall; images and videos
	Preview Size = "preview" // 2048 px long edge; images only (the lightbox plays videos directly)
)

type Source struct {
	RelPath string // for the cache key and messages
	AbsPath string // for the tools
	Kind    string // "image" or "video"
	Size    int64
	MTime   int64
}

func New(dir string, workers int) *Cache
func (c *Cache) Get(ctx context.Context, src Source, size Size) (path, etag string, err error)
```
For videos, take the frame at 2 s. If that produces no file (a clip shorter than 2 s), retry at 0 s.

✅ Tests, run with `-race`:
- `Get` twice: the generator runs once, and the second call is served from disk.
- 50 concurrent `Get` calls for the same source: the generator runs once.
- When the generator fails: no file is left behind, the error comes back, and the next `Get` retries.
- With `workers = 2` and a sleeping fake, at most 2 run at the same time. Track it with an `atomic.Int32` for "running now" and another for the maximum seen.

## Step 2.5: `GET /api/thumb/{id}/{size}` and warming the cache

**Concepts:**
- **Conditional requests for free.** Set two headers *before* calling `ServeContent`:
  - `ETag: "<key>"` (with the quotes);
  - `Cache-Control: public, max-age=31536000, immutable`.

  `ServeContent` then answers `If-None-Match` with 304 by itself. `immutable` tells the browser not even to ask again, which is safe because of the `?v=` in the URL (step 2.2).
- **Interfaces belong to the consumer.** The scanner should pre-generate thumbnails for new files, but `scan` shouldn't depend on the `thumbs` package.
  - Declare what the scanner needs *in `scan`*: `type Warmer interface { Warm(relPath, absPath, kind string, size, mtime int64) }`.
  - `*thumbs.Cache` gets a `Warm` method with that signature. It never mentions `scan.Warmer`, yet satisfies it.
  - This is the opposite of the usual C# habit of declaring `IThumbnailCache` next to its implementation. In Go, small interfaces live where they're used.
- **A buffered channel as a queue,** with a send that never blocks:
  ```go
  select {
  case c.queue <- src:
  default: // queue full: skip it; the thumbnail is generated on demand when someone looks
  }
  ```
  One background goroutine drains the queue, and only makes `Thumb` sizes, so warming never competes much with what you're actually looking at.

**Do:**
1. Write the handler:
   - `size` must be `thumb` or `preview`; anything else is a 400.
   - `preview` is for images only.
   - Get the file from the cache, set the headers, then `ServeContent`.
2. In `thumbs`: `Warm`, the queue and the warming goroutine. The goroutine stops when the server's context is cancelled.
3. In `scan`: a `Warmer` field. Call it for every media row that was inserted or changed.

✅ In the browser's devtools Network tab:
- Open a folder for the first time: thumbnails arrive at tens of KB each, not MB.
- Reload: thumbnails don't hit the server at all.
- Force revalidation (Shift-reload): you get 304s.

Plus a handler test: a `GET` with `If-None-Match` set to the ETag from the first response returns 304.

## Step 2.6: The frontend on the new contract

This step isn't Go, but Phase 2 isn't done without it. The JSON shape is in plan §3.4.

- **`api.js`:**
  - `getDir(path, sort)` calls `/api/dir`.
  - Remove `getBreadcrumb`, `search`, `uploadFiles`, `createDirectory` and `getFileInfo` for now; later phases bring back what they need.
- **`main.js`:** one `getDir` call; breadcrumbs and previous/next come from its response. The gallery tells folders from media by `item.type`, so set `type: 'folder'` on folders when merging the two lists.
- **`gallery.js`:**
  - Images and videos both render `<img src="${item.thumb}" loading="lazy" width height>`; videos keep the `.video-overlay`.
  - No more `<video preload="metadata">` tiles on the originals.
- **`modal.js`:**
  - Images use `item.preview`.
  - Videos use `<video controls preload="metadata" src="/api/file/${item.id}">`.
  - The thumbnail strip uses `thumb`.
- **Download:** an `<a href="/api/file/${id}?download=1">` link instead of fetch → blob. The browser streams it to disk, so a 2 GB video no longer goes through JavaScript memory.
- **Renamed fields:**
  - `relativePath` → `path`;
  - `stats.size` → `size`;
  - `stats.creationTime` → `takenAt`. That's in seconds, so use `new Date(takenAt * 1000)`, and format it with `{ timeZone: 'UTC' }`: it's wall-clock time (plan §3.1), and the browser must not shift it.
- **Deep links:** `?path=/2020/2020-01-01&media=IMG_1234.jpg` opens the lightbox on load. Keep the URL current with `history.replaceState` while stepping through photos.

✅ Browsing, lightbox, video, download and deep links all work. The browser console is clean.

## Step 2.7: Clean up and the done-check

**Do:**
1. Delete the Phase 0 throwaway code:
   - the handlers for `/api/directory`, `/api/breadcrumb` and `/api/media/*`;
   - `internal/api/directory.go`, and what's left of `types.go`.
2. Run `go mod tidy`, `go vet ./...` and `go test -race ./...`.
3. Optional: `go run honnef.co/go/tools/cmd/staticcheck@latest ./...`. It's a stricter linter, and `go run pkg@version` runs it without adding anything to `go.mod`.

✅ The plan's done-when, checked in devtools:
- **Only what's needed loads.** Opening a folder with a few hundred photos loads `/api/dir` plus thumbnails; the total is a few MB, not GB.
- **No layout jump.** The layout stays put while thumbnails arrive, because width and height are known up front.
- **Seeking works.** A video plays, and seeking sends `Range` requests that get 206 answers.
- **Thumbnails are cached.** A reload doesn't request thumbnails again.

## Wrap-up

Can you answer these without looking back?
1. Why is the database the allowlist for `?path=`, and where does `os.Root` still matter?
2. What would go wrong with `immutable` caching if thumbnail URLs had no `?v=`?
3. Thirty tabs request the same uncached thumbnail, and 29 of them close. What happens, step by step?
4. Why is `Warmer` declared in `scan` and not in `thumbs`?
5. Why does the frontend format `takenAt` in UTC?
6. What does `ServeContent` do for you that `VideoStream.php` did by hand?

**Where next:** Phase 3 (hide/delete) in [plan.md](plan.md) is small. It's a good first phase to do without a tutorial, using the same patterns: a store method, a handler, an error mapping and a test.
