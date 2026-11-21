# Phase 1 tutorial: the index and the scanner

A companion to [plan.md](plan.md), Phase 1; the design is in plan §3.1 and §3.2. It assumes you've done [Phase 0](tutorial-phase-0.md).

**By the end:**
- `fotodirbrowser scan` indexes the media tree into SQLite, and a second run changes nothing.
- Renamed and moved files keep their id, so the tags and favorites they get later survive.
- `serve` keeps the index fresh in the background and reports progress live.

**Go ideas you'll meet:** `database/sql` (connection pools, transactions, NULLs), embedding SQL files, sentinel errors, small `io` interfaces, hashing, `os/exec` with JSON output, the reference-time date layout, generics, goroutines, channels, `sync.WaitGroup`, `sync.Mutex`, `sync/atomic`, dependency injection with function values, testing with temp dirs and fakes, the race detector, server-sent events.

**Packages in this phase:**

```
internal/store/   SQLite: Open, migrations, queries            steps 1.1–1.3
internal/media/   Probe: dimensions, dates, camera, video      step 1.4
internal/scan/    fingerprints, reconcile, full scan, status   steps 1.5–1.7
internal/flight/  "do this only once per key" (generics)       step 1.6
```

Dependencies point one way: `scan` uses `store`, `media` and `flight`, while `store` and `media` know nothing about each other. Go refuses to compile import cycles, so the compiler enforces this layering.

**Tools for this phase:**
- `sudo apt install sqlite3` gives you a command-line tool to look inside the database while you work.
- A few photos from your own phone (at least one portrait). The photos in `docs/example` have no EXIF data at all.

## Step 1.1: Opening SQLite

**Concepts:**
- **`database/sql`** is the standard library's ADO.NET: one API, with pluggable drivers.
  - You *blank-import* a driver: `import _ "modernc.org/sqlite"`. It registers itself under the name `"sqlite"` from its `init()` function, a bit like a static constructor.
  - The `_` means "import this only for its side effects".
- **`*sql.DB` is a connection pool, not a connection.** Create one at startup, share it everywhere and close it at shutdown.
  - That's why per-connection SQLite settings (`foreign_keys`, `busy_timeout`) belong in the connection string: every connection the pool opens then gets them.
  - The modernc driver accepts them as `_pragma=name(value)` parameters:
    ```
    file:data/fotodirbrowser.db?_pragma=busy_timeout(5000)&_pragma=journal_mode(WAL)&_pragma=foreign_keys(1)&_pragma=synchronous(NORMAL)&_txlock=immediate
    ```
- **`_txlock=immediate`** makes every transaction start with `BEGIN IMMEDIATE`, which takes SQLite's single write lock up front. With the default (deferred), two transactions that each read and then write can fail with "database is locked". With immediate, the second one waits for up to `busy_timeout` milliseconds instead.
- **WAL mode:** readers don't block the writer and the writer doesn't block readers. That's what you need when the web server reads while the scanner writes.

**Do:**
1. Run `go get modernc.org/sqlite@latest`.
2. Create package `internal/store`:
   ```go
   type Store struct{ db *sql.DB }

   func Open(ctx context.Context, path string) (*Store, error)
   func (s *Store) Close() error
   ```
   `Open` does three things, then runs migrations from step 1.2:
   - creates the parent directory with `os.MkdirAll`;
   - opens the pool;
   - calls `db.PingContext`, so a bad path fails here rather than at the first query.
3. In `run()`, open the store at `filepath.Join(cfg.DataDir, "fotodirbrowser.db")` and `defer st.Close()`.
4. Test with a database in `t.TempDir()`, and use `t.Context()` as the context:
   - take two connections with `db.Conn(ctx)` and keep both open;
   - check that `PRAGMA foreign_keys` returns 1 on each of them.

✅ `go test ./internal/store` passes.

Optional experiment: remove the `_pragma` parameters from the connection string, run `PRAGMA foreign_keys=ON` once with `db.Exec`, and watch the second-connection check fail. That's the pool at work.

## Step 1.2: Migrations

**Concepts:**
- **Embedding SQL files:** `//go:embed migrations/*.sql` into an `embed.FS`. `fs.ReadDir` returns the files sorted by name, so number them `001_init.sql`, `002_….sql`.
- **`PRAGMA user_version`** is an integer that SQLite keeps in the file header for you.
  - Migration *n* runs only when `user_version < n`, and then sets it to *n*. No migrations table and no library.
  - Pragmas can't take `?` parameters. Format the number into the statement with `fmt.Sprintf("PRAGMA user_version = %d", n)`. That's safe because `n` is an `int` you control, never user input.
- **Transactions, with the `defer tx.Rollback()` idiom:**
  ```go
  tx, err := s.db.BeginTx(ctx, nil)
  if err != nil {
  	return err
  }
  defer tx.Rollback() // does nothing after a successful Commit

  if _, err := tx.ExecContext(ctx, sqlText); err != nil {
  	return err // the deferred Rollback undoes everything
  }
  return tx.Commit()
  ```
  This is Go's version of `using var tx = …`. Run each migration together with its `user_version` update in one transaction, so a failing migration leaves nothing half-applied.
- **Wrapping errors** builds a readable trail without stack traces: `fmt.Errorf("migration %s: %w", name, err)` produces messages like `open store: migration 001_init.sql: near "CRATE": syntax error`.

**Do:**
1. `internal/store/migrations/001_init.sql`:
   - the schema from plan §3.1: `folders`, `media`, `tags`, `media_tags`, `folder_tags`, `events` and `search`;
   - the root folder: `INSERT INTO folders (path, name, mtime) VALUES ('/', '', 0);`.
2. `func (s *Store) migrate(ctx context.Context) error`, called from `Open`.
3. Tests:
   - Opening the same file twice works, and the second open doesn't re-run anything.
   - Inserting a `media` row with a `folder_id` that doesn't exist fails. That proves foreign keys are really on.

✅ Run `go run ./cmd/fotodirbrowser`, then `sqlite3 data/fotodirbrowser.db '.tables' 'PRAGMA user_version'` lists the tables and prints `1`.

## Step 1.3: Queries

**Concepts:**
- **Three query shapes:**

  | Method | Use it for |
  |---|---|
  | `ExecContext` | no rows back: INSERT, UPDATE, DELETE |
  | `QueryRowContext(…).Scan(&a, &b)` | exactly one row; returns `sql.ErrNoRows` if there is none |
  | `QueryContext` | many rows |

  For many rows, loop `for rows.Next() { rows.Scan(…) }`, then check `rows.Err()`, and `defer rows.Close()`. Forgetting `rows.Close()` keeps a pooled connection busy forever.
- **Always use `?` placeholders,** never string concatenation. Apart from SQL injection, a folder called `L'Escala` would simply break the query.
- **NULL:** a NULL column can't be scanned into an `int64`. Either:
  - scan into a pointer (`*int64`), which also means "optional" in your structs and becomes JSON `null` in Phase 2; or
  - use the generic `sql.Null[int64]`.
- **Sentinel errors:**
  - Define `var ErrNotFound = errors.New("not found")` in `store`, and translate `sql.ErrNoRows` into it.
  - Callers check `errors.Is(err, store.ErrNotFound)`, so code above `store` never depends on `database/sql` details.
- **Upsert with RETURNING:** `INSERT … ON CONFLICT(path) DO UPDATE SET … RETURNING id` gets the id in one statement, whether the row is new or not.
- **Pointer receivers:** `func (s *Store) Method()` gets a pointer to the shared value. Rule of thumb: if any method of a type needs a pointer receiver, give them all one.
- **Transactions as a callback.** The scanner writes a whole folder's changes in one transaction. A method that takes a function keeps the begin/rollback/commit logic in one place:
  ```go
  type Tx struct{ tx *sql.Tx }

  func (s *Store) WithTx(ctx context.Context, fn func(*Tx) error) error
  ```

**Do:**

Define `Folder` and `Media` structs that mirror the tables, with pointers for nullable columns. Give `Media` an extra `Path string` field that isn't a column: queries fill it by joining `folders`, because the scanner needs the full path to check whether a file still exists.

Methods you'll need over the next steps:

```go
func (s *Store) FolderByPath(ctx context.Context, path string) (Folder, error)
func (s *Store) ChildFolders(ctx context.Context, parentID int64) ([]Folder, error)
func (s *Store) MediaInFolder(ctx context.Context, folderID int64) ([]Media, error)
func (s *Store) FindByFingerprint(ctx context.Context, size int64, fp string) ([]Media, error)

func (t *Tx) UpsertFolder(ctx context.Context, f *Folder) error  // sets f.ID
func (t *Tx) InsertMedia(ctx context.Context, m *Media) error    // sets m.ID
func (t *Tx) UpdateMedia(ctx context.Context, m *Media) error
func (t *Tx) MoveMedia(ctx context.Context, id, folderID int64, name string, mtime int64) error
func (t *Tx) SetMissing(ctx context.Context, table string, ids []int64, since *int64) error
func (t *Tx) SetFolderMTime(ctx context.Context, id, mtime int64) error
```

Don't write them all upfront. Add each one when a later step needs it, together with a small test. `SetMissing` takes a table name, which can't be a `?` parameter. Accept only `"media"` or `"folders"` and reject anything else.

✅ Tests:
- Upserting the same folder twice returns the same id.
- A media row reads back equal to what you inserted. Compare with `reflect.DeepEqual`, and print both with `%+v` on failure.

## Step 1.4: Reading metadata (`internal/media`)

**Concepts:**
- **Codecs registered by blank import:** `image.DecodeConfig` reads only the image header, which is cheap, but the format must be registered with `import _ "image/jpeg"`. Your current code already does this.
- **`io.Reader` and `io.Seeker`:** after `DecodeConfig` has read some bytes, rewind with `f.Seek(0, io.SeekStart)` rather than reopening the file, as the current code does.
- **Dates are parsed by example.** Go layouts use the reference time `Mon Jan 2 15:04:05 MST 2006`, whose parts count 1-2-3-4-5-6-7.
  - EXIF writes dates like `2020:01:01 23:30:00`, so the layout is `"2006:01:02 15:04:05"`.
  - `time.Parse` with no zone in the input returns UTC: exactly the wall-clock storage the plan asks for (§3.1, "Time zones").
- **`os/exec`:** `exec.CommandContext(ctx, "ffprobe", args...)` followed by `cmd.Output()`.
  - Arguments are passed straight to the program, not through a shell, so file names with spaces or quotes are safe.
  - When the program fails, the error is an `*exec.ExitError`. Get at it with `errors.As`, and log its `Stderr` field.
- **JSON into nested anonymous structs:** declare only the fields you need; everything else is ignored. A number that ffprobe sends as a string (`"duration": "3.000000"`) decodes into a `float64` with the tag option `json:"duration,string"`.
- **Video times are real UTC.** `creation_time` looks like `2020-01-01T11:30:00.000000Z` (layout `time.RFC3339Nano`). To store wall-clock time:
  1. convert it with `t.In(loc)`;
  2. rebuild the result with `time.Date(…, time.UTC)` from the same year, month, day, hour, minute and second.

  Make `loc` a parameter, so a test can pass `time.FixedZone("test", 3600)` and doesn't depend on your machine's zone. In production pass `time.Local`. In a slim container that's UTC unless you set `TZ` *and* provide zone data; `import _ "time/tzdata"` embeds that data (~450 KB) into the binary.

**What ffprobe actually returns** for a rotated clip, checked with ffmpeg 7.1 (trimmed):

```json
"streams": [{
  "codec_type": "video", "codec_name": "h264", "width": 320, "height": 240,
  "avg_frame_rate": "25/1",
  "tags": {},
  "side_data_list": [{ "side_data_type": "Display Matrix", "rotation": 90 }]
}],
"format": { "duration": "3.000000", "tags": { "creation_time": "2020-01-01T11:30:00.000000Z" } }
```

There's no `tags.rotate` any more. Your current `getVideoDimensions` only reads that tag, so it would report every portrait phone video sideways.
- Read `side_data_list[].rotation` first, and fall back to `tags.rotate` for older ffmpeg versions.
- A rotation of ±90 or ±270 swaps width and height.

**Do:**
1. Create package `internal/media`. Move `getImageDimensions`, `getVideoDimensions` and `videoRotationToExifOrientation` over from `directory.go`, then grow them into:
   ```go
   type Info struct {
   	Kind          string         // "image" or "video"
   	Width, Height int            // display size, orientation already applied
   	Orientation   int            // EXIF 1–8; 1 if unknown
   	TakenAt       time.Time      // wall-clock, stored as UTC; zero if unknown
   	Duration      float64        // seconds; videos only
   	Camera        string         // "Make Model"
   	Meta          map[string]any // extra fields for the info panel (Phase 6)
   }

   func Kind(name string) string // by extension: "image", "video" or ""
   func Probe(ctx context.Context, path string, loc *time.Location) (Info, error)
   ```
2. **Images:**
   - Width and height come from `DecodeConfig`. EXIF comes from goexif's `exif.Decode`.
   - For the date: `x.Get(exif.DateTimeOriginal)` → `tag.StringVal()` → `time.Parse`. `Make` and `Model` work the same way.
   - Orientation 5–8 swaps width and height.
   - A photo without EXIF is normal, not an error.
3. **Videos:**
   - Run `ffprobe -v error -print_format json -show_format -show_streams <path>`.
   - Use the first stream whose `codec_type` is `"video"`.
   - Take the rotation from the side data, and convert `creation_time` as above.
4. **Test fixtures** go in `internal/media/testdata/`; the `go` tool ignores `testdata` directories when building.
   - Copy in two of your own phone photos, one taken in portrait. `.gitignore` ignores `*.jpg`, so add an exception line `!**/testdata/**`.
   - Don't commit a video. Generate one inside the test instead; these commands are verified:
     ```bash
     ffmpeg -v error -y -f lavfi -i testsrc=duration=3:size=320x240:rate=25 \
       -c:v libx264 -pix_fmt yuv420p -metadata creation_time=2020-01-01T11:30:00Z plain.mp4
     ffmpeg -v error -y -display_rotation 90 -i plain.mp4 -c copy \
       -metadata creation_time=2020-01-01T11:30:00Z rotated.mp4
     ```
   - In the test, check `exec.LookPath("ffmpeg")` first, and call `t.Skip("ffmpeg not installed")` if it's missing.

✅ Tests:
- the portrait photo gives `Height > Width`;
- `rotated.mp4` gives 240×320 and a `Duration` of 3;
- with a `+01:00` test zone, the clip's `TakenAt` is `2020-01-01 12:30:00 UTC`;
- `Kind("notes.txt")` is `""`.

<details><summary>Hint: the ffprobe struct</summary>

```go
var out struct {
	Streams []struct {
		CodecType string `json:"codec_type"`
		CodecName string `json:"codec_name"`
		Width     int    `json:"width"`
		Height    int    `json:"height"`
		Tags      struct {
			Rotate string `json:"rotate"` // older ffmpeg only
		} `json:"tags"`
		SideData []struct {
			Rotation float64 `json:"rotation"`
		} `json:"side_data_list"`
	} `json:"streams"`
	Format struct {
		Duration float64 `json:"duration,string"`
		Tags     struct {
			CreationTime string `json:"creation_time"`
		} `json:"tags"`
	} `json:"format"`
}
```
`Rotation` is a `float64` to be safe across ffmpeg versions; round it with `math.Round` before comparing.
</details>

## Step 1.5: Fingerprints

**Concepts:**
- **Accept interfaces, return structs.** `Fingerprint` only needs to read bytes at given positions, so it takes an `io.ReaderAt`.
  - `*os.File` implements that, and so do `bytes.NewReader` and `strings.NewReader`.
  - So your tests don't need any files: pass `bytes.NewReader(data)`.
- **`io.NewSectionReader(r, offset, n)`** is an `io.Reader` over a window of an `io.ReaderAt`.
- **A `hash.Hash` is an `io.Writer`.** `io.Copy(h, section)` streams bytes into SHA-256 without holding them all in memory. `hex.EncodeToString(h.Sum(nil))` turns the result into a string.
- **Constants with shifts:** `const chunk = 64 << 10` is 64 KiB.

**Do:** in `internal/scan`, write `func Fingerprint(r io.ReaderAt, size int64) (string, error)`. It hashes the first and last 64 KiB; files of 128 KiB or less are hashed whole.

✅ A table-driven test with `bytes.NewReader`:
- the same content gives the same fingerprint;
- changing the last byte changes it;
- changing one byte in the middle of a 1 MB slice does **not** change it.

That last case documents the trade-off you chose: it's fast, and good enough to recognize a moved photo, but it isn't a content checksum.

<details><summary>Spoiler</summary>

```go
const chunk = 64 << 10 // 64 KiB

func Fingerprint(r io.ReaderAt, size int64) (string, error) {
	parts := []*io.SectionReader{io.NewSectionReader(r, 0, size)}
	if size > 2*chunk {
		parts = []*io.SectionReader{
			io.NewSectionReader(r, 0, chunk),
			io.NewSectionReader(r, size-chunk, chunk),
		}
	}
	h := sha256.New()
	for _, p := range parts {
		if _, err := io.Copy(h, p); err != nil {
			return "", err
		}
	}
	return hex.EncodeToString(h.Sum(nil)), nil
}
```
</details>

## Step 1.6: Reconciling one folder

This is the heart of the scanner. Write it as plain sequential code first; step 1.7 makes it concurrent.

**The algorithm** (plan §3.2):

```
ReconcileFolder(path):
  stat the directory on disk → dirMTime        (gone? mark the folder missing, stop)
  entries := read the directory; skip names starting with "." and "@eaDir"
  split entries into subdirectories and media files (media.Kind(name) != "")
  rows := this folder's media rows from the database, keyed by name

  for each media file on disk:
    row exists, same size and mtime, not missing  → nothing to do
    row exists, size or mtime changed             → fingerprint, probe, update
    row exists but is marked missing              → it came back: clear missing_since
    no row                                        → fingerprint, then:
        candidates := FindByFingerprint(size, fp)
        a candidate whose file no longer exists    → MOVE: re-point that row here
        otherwise                                  → probe, insert
  rows left over (no file on disk)                → set missing_since; keep the row!

  subdirectories: upsert a folder row for each one;
                  child folder rows with no directory on disk → missing
  last of all: store dirMTime on the folder row
```

**Concepts:**
- **Maps as indexes:** `byName := make(map[string]store.Media, len(rows))`.
  - `m, ok := byName[name]` is the "comma ok" idiom, like `TryGetValue`.
  - Delete entries as you match files; whatever is left wasn't on disk.
- **Keep slow work out of transactions.** SQLite has one writer at a time. Fingerprint and probe *first* and collect the results. Then open one short `WithTx` for all of this folder's writes.
- **Idempotency.** Store `dirMTime` *last*. If the process dies halfway, the folder still looks changed and is simply reconciled again next time. Every step must be safe to repeat.
- **Dependency injection with a function field.**
  - The `Scanner` gets a field `probe func(ctx context.Context, path string) (media.Info, error)`, which `New` sets to call `media.Probe`.
  - Tests replace it with a fake that counts calls and returns fixed data. Scanner tests then need neither ffprobe nor real photos, and can assert "the second run probed nothing".
- **`os.Root`** (Go 1.24+): open the media directory once with `os.OpenRoot` and do all disk access through it (`root.Stat`, `root.Open`, `fs.ReadDir(root.FS(), name)`).
  - A name can then never escape the media directory, not through `..` and not through a symlink pointing outside.
  - Root-relative names have no leading slash, and `"."` is the root itself. `os.Root` rejects `/2020/…` exactly like `../etc`. Write a helper that turns the database's `/2020/2020-01-01` into `2020/2020-01-01`.
  - `ffprobe` still needs a real path; build it with `filepath.Join(mediaDir, rel)`. That's safe because `rel` comes from your own directory listing.
- **Generics, for "only once per key".** A web request (Phase 2) and the background scan may reconcile the same folder at the same moment. Write a small package `internal/flight`:
  ```go
  type Group[K comparable] struct { /* … */ }

  func (g *Group[K]) Do(ctx context.Context, key K, fn func(context.Context) error) error
  ```
  - A second caller with the same key waits for the first call's result instead of running `fn` again.
  - `[K comparable]` is a type parameter, roughly `where K : IEquatable<K>`; `comparable` means "usable as a map key".
  - Phase 2 reuses this for thumbnails.

**Do:**
1. `internal/flight`, with a test: 50 goroutines call `Do` with the same key while `fn` sleeps 50 ms, and `fn` must run exactly once. Run it with `go test -race`.
2. `internal/scan`:
   ```go
   type Scanner struct {
   	mediaDir string
   	root     *os.Root
   	store    *store.Store
   	probe    func(ctx context.Context, path string) (media.Info, error)
   	folders  flight.Group[string]
   	// counters come in step 1.7
   }

   func New(mediaDir string, st *store.Store) (*Scanner, error)
   func (s *Scanner) ReconcileFolder(ctx context.Context, path string) error
   ```
3. Scenario tests on a real `t.TempDir()` tree. With the fake probe, file contents can be any bytes. Cover:
   - new files are inserted, and the probe is called once per file;
   - a second run makes no probe calls and no changes;
   - changed content (rewrite the file and move its mtime with `os.Chtimes`) is probed again;
   - a file renamed within its folder keeps its id;
   - a file moved to another folder keeps its id and gets the new folder;
   - a copied file gets a second row;
   - a deleted file keeps its row, with `missing_since` set;
   - dotfiles and `@eaDir` are ignored.

✅ `go test -race ./internal/flight ./internal/scan` passes.

<details><summary>Hint: a test helper</summary>

A helper that can fail the test should call `t.Helper()` first, so the failure points at the test's line rather than the helper's:

```go
func writeFile(t *testing.T, path, data string) {
	t.Helper()
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path, []byte(data), 0o644); err != nil {
		t.Fatal(err)
	}
}
```
</details>

<details><summary>Spoiler: flight.Group (verified with -race)</summary>

```go
type call struct {
	done chan struct{}
	err  error
}

// Group deduplicates concurrent work by key. The zero value is ready to use.
type Group[K comparable] struct {
	mu       sync.Mutex
	inflight map[K]*call
}

// Do runs fn once for all concurrent callers with the same key. fn gets a
// context that isn't cancelled when the first caller gives up, because other
// callers may still be waiting for the result.
func (g *Group[K]) Do(ctx context.Context, key K, fn func(context.Context) error) error {
	g.mu.Lock()
	if g.inflight == nil {
		g.inflight = make(map[K]*call)
	}
	c, ok := g.inflight[key]
	if !ok {
		c = &call{done: make(chan struct{})}
		g.inflight[key] = c
		go func() {
			c.err = fn(context.WithoutCancel(ctx))
			g.mu.Lock()
			delete(g.inflight, key)
			g.mu.Unlock()
			close(c.done) // closing a channel wakes every waiter at once
		}()
	}
	g.mu.Unlock()

	select {
	case <-c.done:
		return c.err
	case <-ctx.Done(): // this caller gives up; fn keeps running for the others
		return ctx.Err()
	}
}
```
Reading `c.err` after `<-c.done` is safe: closing the channel "happens before" the receive completes, and the race detector agrees.
</details>

## Step 1.7: The full scan, concurrently

**Concepts:**
- **Goroutines are cheap,** a few KB each, so starting thousands is fine. Running thousands of `ffprobe` processes at once isn't, so bound the work.
- **A semaphore is a buffered channel:** `sem := make(chan struct{}, n)`. Acquire a slot with `sem <- struct{}{}`, which blocks while all `n` slots are taken, and release it with `<-sem`. `struct{}` takes zero bytes.
- **`sync.WaitGroup`:** `wg.Go(func() { … })` (Go 1.25+) starts a goroutine and tracks it, and `wg.Wait()` blocks until all of them finish. It's the equivalent of `Task.WhenAll`.
- **Sharing results safely:**
  - Goroutines that each write to their *own index* of a pre-sized slice don't race.
  - Appending to one shared slice does race; protect it with a `sync.Mutex`.
- **Atomic counters:** `atomic.Int64` with `.Add(1)` and `.Load()`. The HTTP handler can read progress while the scan updates it, without a lock.
- **Cancellation:**
  - Check `ctx.Err()` between folders.
  - `exec.CommandContext` kills `ffprobe` when the context is cancelled.
  - A Ctrl-C during a scan should stop it within a second.
- **The race detector:** `go test -race` and `go run -race` instrument memory accesses and report unsynchronized ones. Use it on everything that has goroutines. It needs cgo, which is fine: gcc is installed, and the production build stays `CGO_ENABLED=0`.

**Do:**
1. **Parallel probing:** in `ReconcileFolder`, probe the files that need it concurrently, at most `runtime.NumCPU()` at a time.
2. **`func (s *Scanner) FullScan(ctx context.Context) error`:**
   - Reconcile `/`, then every subfolder recursively.
   - **Clean up only after a complete pass.** Then delete the media rows that are still missing, write an `events` row for each, and delete missing folders, deepest first. Moves are all resolved by then, whichever order the folders were visited in. A cancelled scan must skip the cleanup.
   - Log a summary with `slog`: folders, files, new, moved, changed, removed, duration.
3. **Status:** a struct of atomic counters, with a `Snapshot()` method that returns a plain struct (for JSON in step 1.8).
4. **Periodic runs:** `func (s *Scanner) Run(ctx context.Context, every time.Duration)` does a full scan right away, then one every `every`, until `ctx` is cancelled:
   ```go
   ticker := time.NewTicker(every)
   defer ticker.Stop()
   for {
   	select {
   	case <-ctx.Done():
   		return
   	case <-ticker.C:
   		// full scan; log errors, keep going
   	}
   }
   ```
   With `every == 0`, scan once and return.

✅ `go test -race ./...` is clean. One more test: 200 fake files, with a fake probe that sleeps 10 ms each, finish in well under the 2 s that sequential probing would take. Keep timing thresholds generous so the test isn't flaky.

## Step 1.8: Wiring it up: `scan`, background scanning, progress

**Concepts:**
- **Server-sent events** are a long-lived `GET` response with `Content-Type: text/event-stream`.
  - Write `data: {…}\n\n` blocks, and flush after each one with `http.NewResponseController(w).Flush()`. That works through your logging wrapper thanks to its `Unwrap` method from Phase 0.
  - The loop ends when `r.Context()` is cancelled, meaning the browser went away.
  - In the browser it's one line: `new EventSource('/api/events')`.
- **Every goroutine needs a way to stop.** The background scan gets the server's signal context, so Ctrl-C stops it. `run()` waits for it to finish, with a `WaitGroup` or a `done` channel, *before* the deferred `st.Close()` runs.

**Do:**
1. `fotodirbrowser scan [--path /2020] [--reextract]` opens the store, runs `FullScan` (or reconciles just the subtree under `--path`), prints the summary and exits. `--reextract` probes every file even when it's unchanged; use it after improving `media.Probe`.
2. `serve` starts `scanner.Run(ctx, cfg.ScanInterval)` in a goroutine.
3. Two new endpoints:
   - `GET /api/status` returns the status snapshot as JSON;
   - `GET /api/events` sends one SSE event immediately, then one per second while a scan runs.
4. Keep the Phase 0 `/api/directory` and `/api/breadcrumb` handlers for now. The frontend still uses them until Phase 2.

✅ The plan's done-when, on your real library:
```bash
export MEDIA_DIR=/path/to/your/library
go run ./cmd/fotodirbrowser scan     # first run: everything is new
go run ./cmd/fotodirbrowser scan     # second run: 0 new, 0 changed, nothing probed
sqlite3 data/fotodirbrowser.db 'select kind, count(*) from media group by kind'
sqlite3 data/fotodirbrowser.db 'select count(*) from media where width is null'  # files the probe couldn't read
go run ./cmd/fotodirbrowser serve &  # then, in another terminal:
curl -N localhost:3000/api/events    # one line per second while the startup scan runs
```
Then rename a folder on disk and run `scan` again. The summary should say "moved", not "new" plus "removed".

## Wrap-up

Can you answer these without looking back?
1. Why must `foreign_keys` be in the connection string rather than executed once after `Open`?
2. What happens if the scanner crashes after writing a folder's media rows but before storing its mtime?
3. Why does the scanner probe files *before* opening the transaction?
4. When are media rows actually deleted, and why not as soon as the file is gone?
5. What does `[K comparable]` allow, and what does it rule out?
6. Which of your tests would catch a data race, and how?

Next: [Phase 2, browsing parity](tutorial-phase-2.md).
