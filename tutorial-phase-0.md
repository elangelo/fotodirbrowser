# Phase 0 tutorial: clean the ground

A companion to [plan.md](plan.md), Phase 0. You write the code. For each step this tutorial covers:
- what to build;
- which Go ideas it teaches, with C# comparisons where they help;
- how to check your work.

Hints and sketches are folded away. Open them only when you're stuck.

**By the end:**
- `go run ./cmd/fotodirbrowser` serves the existing frontend from one binary, with no Node.js, webpack or chi.
- The UI can browse folders again. That's temporary: Phase 2 replaces the API.
- `docker build .` works, so CI is green again.

**Go ideas you'll meet:** modules and packages, exported names, `internal/`, interfaces satisfied implicitly, function types with methods, closures as middleware, struct embedding, `defer`/`recover`, errors as values, table-driven tests, `embed` and `io/fs`, struct tags and JSON, `context` and graceful shutdown, static binaries.

## Before you start

**Toolchain:**
- **PATH:** `/usr/local/go/bin` isn't on the PATH of non-interactive shells. Add `export PATH=$PATH:/usr/local/go/bin:$HOME/go/bin` to `~/.profile`, then log out and back in. `$HOME/go/bin` is where `go install` puts tools.
- **VS Code:** install the official Go extension. It installs `gopls` (the language server) and `dlv` (the debugger). Turn on format-on-save: all Go code is `gofmt`-formatted, so there's never a style discussion.
- **Commands you'll use all the time:**

  | Command | What it does |
  |---|---|
  | `go build ./...` | Compile everything; `./...` means "this directory and below" |
  | `go vet ./...` | Catch suspicious code the compiler allows |
  | `go test ./...` | Run all tests |
  | `go doc net/http.ServeMux` | Documentation, offline |
  | `go mod tidy` | Add missing and remove unused dependencies in `go.mod` |

**Reading material.** Skim it as needed; don't study it all upfront.
- [A Tour of Go](https://go.dev/tour/): the "Basics" and "Methods and interfaces" sections, an hour or two.
- [Effective Go](https://go.dev/doc/effective_go): read sections as topics come up.
- [pkg.go.dev/std](https://pkg.go.dev/std): docs for every standard library package, with examples.

**C# → Go in one table:**

| C# | Go | Note |
|---|---|---|
| `.csproj` + NuGet | `go.mod` | |
| `packages.lock.json` | `go.sum` | checksums; commit it |
| namespace | package (one per directory) | |
| `public` / `internal` | Capitalized / lowercase name; `internal/` directories | `internal/` = only this module may import it |
| class | struct + methods | no inheritance; compose with embedding |
| `class Foo : IFoo` | interfaces are satisfied implicitly | having the methods is enough |
| exceptions | `error` return values | `panic` exists, but means "bug, crash" |
| `using` / `finally` | `defer` | |
| `CancellationToken` | `context.Context` | also carries deadlines |
| `Task.Run` / `Task.WhenAll` | `go f()` / `sync.WaitGroup` | |
| `Channel<T>` | `chan T` | built into the language |
| `[JsonPropertyName("x")]` | struct tag `` `json:"x"` `` | |
| `default(T)` | zero value | every type has one |
| `ILogger` | `log/slog` | |
| minimal APIs `app.MapGet` | `mux.HandleFunc("GET /x", h)` | |

## Step 0.1: Commit what's in flight and remove the binary

**Do:**
1. Commit your uncommitted width/height/orientation work, so the moves below start from a clean tree.
2. Run `git rm --cached go-backend/main`. In `.gitignore`, add the binary you'll build (`/fotodirbrowser`) and the data directory (`/data/`).

**Why:** compiled binaries don't belong in git. They're large, platform-specific and always out of date.

✅ `git ls-files go-backend/main` prints nothing.

## Step 0.2: One module at the repository root

**Concepts:**
- **Module:** the unit of versioning, defined by `go.mod`. Its *module path* is the prefix of every import path inside it. Use the repository URL: `module github.com/elangelo/fotodirbrowser`. A package in `internal/api` is then imported as `github.com/elangelo/fotodirbrowser/internal/api`.
- **Package:** one directory is one package. The package name is normally the last element of the path. `package main` with a `func main()` builds an executable.
- **`internal/`:** packages below an `internal/` directory can only be imported by code rooted at the parent of `internal/`. The compiler enforces this; it's how Go says "not public API".
- **`cmd/<name>/`:** the convention for executables. `go build ./cmd/fotodirbrowser` produces a binary called `fotodirbrowser`.
- **The `go` line in `go.mod`:** the minimum language version the code needs. It also switches on newer semantics, such as each loop iteration getting its own loop variable (since 1.22).

**Do:**
1. Move with `git mv`, so history follows the files:
   - `go-backend/go.mod` and `go-backend/go.sum` → repo root
   - `go-backend/internal/api` → `internal/api`
   - `go-backend/cmd/server` → `cmd/fotodirbrowser`
   - `frontend/public` → `web`
2. Check what's left in `go-backend/`: only empty directories and the untracked binary. Then delete it.
3. In `go.mod`, set the module path to `github.com/elangelo/fotodirbrowser` and the `go` line to `1.27`.
4. Fix the import in `main.go`: `fotodirbrowser/internal/api` becomes the new path.

✅ `go build ./... && go vet ./...` prints nothing. `go list ./...` lists two packages: `cmd/fotodirbrowser` and `internal/api`.

<details><summary>Hint: the frontend path in main.go</summary>

`main.go` serves `../frontend/dist`, which no longer exists. You'll replace that in step 0.5, so for now it only has to compile.
</details>

## Step 0.3: Replace chi with the standard library

**Concepts:**
- **`http.Handler`** is a one-method interface: `ServeHTTP(http.ResponseWriter, *http.Request)`. Anything with that method is a handler; there's no `implements` keyword. Your `DirectoryHandler` already is one.
- **`http.HandlerFunc`** is a *function type that has a method*:
  ```go
  type HandlerFunc func(ResponseWriter, *Request)
  func (f HandlerFunc) ServeHTTP(w ResponseWriter, r *Request) { f(w, r) }
  ```
  Converting a plain function to `HandlerFunc` makes it a `Handler`. Coming from C# that's unusual; in Go it's the standard way to write an adapter.
- **`http.ServeMux` patterns** (Go 1.22+):
  - `"GET /health"` matches one method and path. A `GET` pattern also answers `HEAD`.
  - `"GET /api/thumb/{id}/{size}"` captures segments; read them with `r.PathValue("id")`.
  - `"GET /api/media/{path...}"` captures the rest of the path.
  - `"/"` matches anything no more specific pattern matched.

  The most specific pattern wins, so registration order doesn't matter. A wrong method gets an automatic `405 Method Not Allowed`.
- **Middleware** is a function `func(http.Handler) http.Handler` that returns a handler wrapping the next one. It's a *closure*: the returned function captures `next`. Same idea as ASP.NET middleware, without the framework.
- **Struct embedding:** a struct that embeds `http.ResponseWriter` gets all its methods ("promoted"), and you override only the one you need. It's composition that looks like inheritance. Also give it an `Unwrap() http.ResponseWriter` method, so `http.ResponseController` can still reach the real writer; Phase 1 needs that for streaming.
- **`defer` + `recover()`:** a deferred function runs when the surrounding function returns, including during a panic, and `recover()` inside it stops the panic. Use it in one place at the edge (a middleware), never for normal control flow.
- **`log/slog`:** structured logging, `slog.Info("request", "method", r.Method, "status", 200)`.

Here's the shape of a middleware: a generic example, not one you need:

```go
func withHeader(name, value string, next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set(name, value) // runs before the wrapped handler
		next.ServeHTTP(w, r)
	})
}
```

**Do:**
1. In `main.go`, replace `chi.NewRouter()` with `http.NewServeMux()`. Register the same routes with method patterns:
   - `GET /health`
   - `GET /api/directory` (your existing handler)
   - `GET /api/media/{path...}` (the file server; keep the `http.StripPrefix`)
   - `/` for the frontend (step 0.5 fills it in)
2. Write two middlewares in `internal/api/middleware.go` and wrap the mux in them:
   - `logRequests` logs method, path, status and duration. `ResponseWriter` doesn't expose the status, so wrap it in a struct that embeds it and records the `WriteHeader` call.
   - `recoverPanics` turns a panic into a 500 and logs it.
3. Drop CORS. The frontend is served from the same origin, so it isn't needed.
4. Run `go mod tidy`; chi and cors disappear from `go.mod`.

✅ From the repo root:
```bash
MEDIA_DIR=docs/example go run ./cmd/fotodirbrowser &
curl -i localhost:3000/health                      # 200
curl -i -X POST localhost:3000/health              # 405
curl -s 'localhost:3000/api/directory?path=/2020'  # JSON
kill %1
```
Each request logs one line that includes its status.

<details><summary>Hint: capturing the status</summary>

`WriteHeader` isn't always called. A handler that only calls `Write` gets an implicit 200. Start your recorder at `status: http.StatusOK`.
</details>

<details><summary>Spoiler: one way to write both middlewares</summary>

```go
type statusRecorder struct {
	http.ResponseWriter // embedded: Header and Write are promoted
	status int
}

func (r *statusRecorder) WriteHeader(code int) {
	r.status = code
	r.ResponseWriter.WriteHeader(code)
}

// Unwrap lets http.ResponseController reach the real writer (for Flush).
func (r *statusRecorder) Unwrap() http.ResponseWriter { return r.ResponseWriter }

func logRequests(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		rec := &statusRecorder{ResponseWriter: w, status: http.StatusOK}
		next.ServeHTTP(rec, r)
		slog.Info("request", "method", r.Method, "path", r.URL.Path,
			"status", rec.status, "took", time.Since(start))
	})
}

func recoverPanics(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if v := recover(); v != nil {
				slog.Error("panic", "err", v, "path", r.URL.Path)
				http.Error(w, "internal error", http.StatusInternalServerError)
			}
		}()
		next.ServeHTTP(w, r)
	})
}
```
Wire them as `handler := logRequests(recoverPanics(mux))`. The outermost wrapper runs first.
</details>

## Step 0.4: Configuration and a small `main`

**Concepts:**
- **Keep `main` tiny.** `main` calls `run(…) error` and exits with a non-zero status if it returns an error. `run` can be tested. `log.Fatal` deep in the code can't be, because it calls `os.Exit` and skips every `defer`.
- **Errors are values.** Functions return `(T, error)`, and the caller checks `if err != nil`. Add context as the error travels up: `fmt.Errorf("parse SCAN_INTERVAL %q: %w", v, err)`. `%w` *wraps* the error, so callers can still test for the cause with `errors.Is` / `errors.As`.
- **No constructors.** A `Load` or `New…` function that returns a filled struct plays that role.
- **Subcommands with `flag.FlagSet`.** The stdlib `flag` package parses `-name value` and `--name=value`. One `FlagSet` per subcommand gives you `fotodirbrowser serve` now, and `fotodirbrowser scan --path /2020` in Phase 1.

**Do:**
1. Package `internal/config`:
   - A `Config` struct with the settings from the plan's table: `MediaDir`, `DataDir`, `Addr`, `ScanInterval time.Duration`, `ThumbWorkers int`, `Dev bool`.
   - `func Load(getenv func(string) string) (Config, error)`. Taking `getenv` as a parameter (`os.Getenv` in production) makes it testable without touching the real environment.
   - Validate:
     - `MEDIA_DIR` is required, must be a directory, and is made absolute with `filepath.Abs`.
     - `SCAN_INTERVAL` is parsed with `time.ParseDuration`.
     - `THUMB_WORKERS` defaults to `runtime.NumCPU()`.
2. In `main.go`, `os.Args[1]` selects the subcommand, defaulting to `serve`. Give each subcommand its own `flag.FlagSet`. An unknown subcommand prints usage and exits with status 2.
3. Write your first test, `internal/config/config_test.go`. Make it table-driven: a slice of cases, each with an env map and the expected result or error, looped with `t.Run(tc.name, …)`.

Here's the table-driven pattern, the Go equivalent of xUnit's `[Theory]` + `[InlineData]`. `parseSize` is only an example:

```go
func TestParseSize(t *testing.T) {
	tests := []struct {
		name    string
		in      string
		want    int
		wantErr bool
	}{
		{"plain", "300", 300, false},
		{"empty", "", 0, true},
	}
	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			got, err := parseSize(tc.in)
			if (err != nil) != tc.wantErr {
				t.Fatalf("err = %v, wantErr %v", err, tc.wantErr)
			}
			if got != tc.want {
				t.Errorf("got %d, want %d", got, tc.want)
			}
		})
	}
}
```

✅ Three checks:
- `go test ./internal/config -v` passes.
- `MEDIA_DIR=/nope go run ./cmd/fotodirbrowser` exits with a clear one-line error.
- `go run ./cmd/fotodirbrowser bogus` prints usage.

<details><summary>Hint: a fake getenv</summary>

A map does the job: `env := map[string]string{"MEDIA_DIR": dir}`, then pass `func(k string) string { return env[k] }`. A missing key returns the zero value `""`, just like an unset variable. `t.TempDir()` gives you a directory that exists and is cleaned up after the test.
</details>

## Step 0.5: Embed the frontend and drop Node.js

**Concepts:**
- **`//go:embed`** compiles files into the binary. The directive goes right above a variable of type `embed.FS`. It names files or directories relative to the Go file's own directory, and can't use `..`, so the Go file must live in or above `web/`.
  - Put a tiny package inside `web/`: a file `web/web.go` that starts with `package web` and contains
    ```go
    //go:embed index.html css js
    var Files embed.FS
    ```
  - Names starting with `.` or `_` are skipped unless you use the `all:` prefix.
- **`io/fs.FS`** is the read-only filesystem interface: `Open(name) (fs.File, error)`. `embed.FS`, `os.DirFS("web")` and, later, `os.Root.FS()` all implement it.
  - That's how DEV mode works: production serves the embedded `fs.FS`, and `DEV=1` serves `os.DirFS("web")`, so you can edit JS or CSS and just reload.
  - The handler doesn't know which one it got: the same idea as depending on an interface in C#.
- **`http.FileServerFS(fsys)`** serves any `fs.FS`, with `index.html` handling, `Last-Modified`, `Range` and content types.
- **ES modules need no bundler.** Browsers load `import { API } from './api.js'` natively when the entry script is `<script type="module">`. Module scripts run deferred, before `DOMContentLoaded`, so `main.js` doesn't change.

**Do:**
1. Create `web/web.go` as above.
2. In `web/index.html`, replace `<script src="/js/bundle.js"></script>` with `<script type="module" src="/js/main.js"></script>`.
3. In `main.go`, pick the filesystem and serve it:
   - `var static fs.FS = web.Files`;
   - if `cfg.Dev`, use `os.DirFS("web")` instead;
   - `mux.Handle("/", http.FileServerFS(static))`.
4. Delete what's left of `frontend/`: `server.js`, `package.json`, `webpack.config.js`, `README.md` and `.github/`. Remove the Node.js entries from `.gitignore`.
5. Delete the dead code:
   - `web/js/mosaic.js` and `web/css/mosaic.css`, plus its `<link>` in `index.html`;
   - the 1/2/3 shortcuts and `switchLayout` in `keyboard.js`. They call `setLayoutType`, which `JustifiedLayout` doesn't have.

✅ Three checks:
- **It's really embedded:** `go build -o fdb ./cmd/fotodirbrowser`, copy `fdb` to a directory outside the repo, and run it there with `MEDIA_DIR` set. The page still loads.
- **DEV mode:** with `DEV=1`, a CSS edit shows up on reload without rebuilding.
- **Scripts load:** the browser console shows no 404s for scripts. Folders don't load yet; that's the next step.

## Step 0.6: Make the UI work again (a throwaway exercise)

The frontend was written for the old Express server. It calls `/api/breadcrumb`, which returns 404 here, so no folder loads. Phase 2 replaces all of this with `/api/dir`. Fixing it now is still worth it: it's a good first real handler, and you get a working UI to look at.

**Concepts:**
- **Struct tags** set JSON names: ``Path string `json:"relativePath"` `` is the Go version of `[JsonPropertyName("relativePath")]`. Only exported (capitalized) fields are encoded.
- **nil vs empty slices:** a nil `[]string` encodes as `null`, but an empty one (`[]string{}`) encodes as `[]`. JavaScript that calls `.length` on `null` throws, so initialize slices you return as JSON.
- **Pointers for "maybe absent":** a nil `*string` encodes as `null`.
- **`path` vs `path/filepath`:** `path` is for slash-separated paths, like URLs and the API's `/2020/2020-01-01`. `filepath` is for paths on the operating system. Use `path.Dir`, `path.Base` and `path.Clean` on API paths.
- **`slices` and `strings` packages:** `strings.Split`, `slices.Index`, `slices.Sort`.

**Do:**
1. In `internal/api/types.go`:
   - tag the `Path` fields `json:"relativePath"`;
   - add ``Type string `json:"type"` `` (always `"folder"`) and `Tags []string` to `FolderItem`;
   - fill them in `readDirectory`.
2. Add a handler for `GET /api/breadcrumb?path=` that returns:
   ```json
   {"breadcrumbs": [{"name": "Home", "path": "/"}, {"name": "2020", "path": "/2020"}],
    "navigation": {"previous": null, "next": "/2021"}}
   ```
   `previous` and `next` are the sibling directories before and after the current one, sorted by name.
3. Put the pure part (path → breadcrumbs) in its own function and give it a table-driven test. Cover `/`, `/2020`, `/2020/2020-01-01` and a trailing slash.

✅ The browser shows folders, breadcrumbs and working ◀ ▶ folder navigation. Thumbnails are still full-size originals; Phase 2 fixes that.

<details><summary>Hint: siblings</summary>

`os.ReadDir` already returns entries sorted by name. Keep the directories, find the current name with `slices.Index`, then look at index−1 and index+1. The root `/` has no siblings.
</details>

## Step 0.7: Graceful shutdown and server timeouts

**Concepts:**
- **`context.Context`** is roughly `CancellationToken` + deadline + request-scoped values. By convention it's the first parameter: `func Do(ctx context.Context, …)`. Every incoming request has one, `r.Context()`, which is cancelled when the client disconnects.
- **`signal.NotifyContext(ctx, os.Interrupt, syscall.SIGTERM)`** returns a context that's cancelled on Ctrl-C or `docker stop`.
- **`http.Server{…}` instead of `http.ListenAndServe`** gives you two things:
  - `ReadHeaderTimeout`, which protects against clients that send headers very slowly;
  - `Shutdown(ctx)`, which stops accepting new connections and waits for the requests in flight.

  Don't set a short `WriteTimeout`: streaming a video is one long response.
- **Goroutines and channels:**
  - `go f()` runs `f` concurrently.
  - A channel receive, `<-ch`, blocks until a value arrives or the channel is closed.
  - `select` waits for whichever of several channel operations is ready first.

**Do:**
1. Start `srv.ListenAndServe()` in a goroutine.
2. Wait for the signal context.
3. Call `srv.Shutdown` with a 10-second timeout context (`context.WithTimeout`, followed by `defer cancel()`).
4. Treat `http.ErrServerClosed` from `ListenAndServe` as normal.

✅ Start the server, request `/health`, press Ctrl-C. You see a "shutting down" log line, and `echo $?` prints `0`.

<details><summary>Hint: the shape</summary>

```go
ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
defer stop()

errc := make(chan error, 1) // buffered, so the goroutine never blocks on send
go func() { errc <- srv.ListenAndServe() }()

select {
case err := <-errc: // failed to start, e.g. port in use
	return err
case <-ctx.Done(): // Ctrl-C or SIGTERM
}
// … Shutdown with a timeout context
```
</details>

## Step 0.8: Docker and CI

**Concepts:**
- **Static binaries.** With `CGO_ENABLED=0`, the Go binary doesn't depend on the system's C library, so it runs in any Linux image.
  - `-trimpath` removes your local paths from the binary.
  - `-ldflags="-s -w"` strips debug symbols, for a smaller release binary.
- **Cross-compiling** takes two environment variables: `GOOS=linux GOARCH=arm64 go build …`.
- **Multi-stage Dockerfile:** compile in the `golang` image, then copy only the binary into a slim runtime image that has the tools it calls.

**Do:**
1. Write a root `Dockerfile`:
   - **Build stage:** `FROM golang:1.27 AS build`. Copy `go.mod` and `go.sum` first and run `go mod download`; that layer stays cached until the dependencies change. Then copy the rest and build with `CGO_ENABLED=0`.
   - **Runtime stage:** `FROM debian:trixie-slim`.
     - `apt-get install -y --no-install-recommends ffmpeg libvips-tools`.
     - Copy the binary to `/usr/local/bin/fotodirbrowser`.
     - Create and switch to a non-root user whose uid can read your photos on the NAS.
     - `ENTRYPOINT ["/usr/local/bin/fotodirbrowser"]`, `CMD ["serve"]`, `EXPOSE 3000`.
2. Write a `.dockerignore` listing `.git`, `legacy/`, `data/` and `docs/`.
3. Push. The existing workflow builds the root `Dockerfile`, so CI goes green. (Optional: update the actions to their current major versions.)

✅ Build and run:
```bash
docker build -t fotodirbrowser .
docker run --rm -p 3000:3000 -e MEDIA_DIR=/media \
  -v "$PWD/docs/example:/media:ro" fotodirbrowser
```
The UI works. `docker image ls fotodirbrowser` shows that most of the image is ffmpeg, not your binary.

## Step 0.9: Debugging

Replace the PHP `.vscode/launch.json` with:

```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "serve",
      "type": "go",
      "request": "launch",
      "mode": "auto",
      "program": "${workspaceFolder}/cmd/fotodirbrowser",
      "args": ["serve"],
      "env": {
        "MEDIA_DIR": "${workspaceFolder}/docs/example",
        "DATA_DIR": "${workspaceFolder}/data",
        "DEV": "1"
      }
    }
  ]
}
```

Then try it out:
1. Set a breakpoint in your breadcrumb handler and press F5.
2. Load the page and look at `r.URL.Query()`.
3. In the debug console, evaluate an expression such as `len(entries)`.

The terminal equivalent is `dlv debug ./cmd/fotodirbrowser -- serve`.

✅ You stopped on a breakpoint and stepped into `os.ReadDir`.

## Wrap-up

Can you answer these without looking back?
1. Why is `DirectoryHandler` an `http.Handler` without ever saying so?
2. What does `http.HandlerFunc(f)` do, and why is it a *type conversion* rather than a function call?
3. Why does `run()` return an error instead of calling `log.Fatal`?
4. What happens to a request in flight when you press Ctrl-C, now and before step 0.7?
5. Why can't `cmd/fotodirbrowser/main.go` embed `../../web` directly?
6. When does `encoding/json` write `null` and when `[]`?

Next: [Phase 1, the index and the scanner](tutorial-phase-1.md).
