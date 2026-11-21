# fotodirbrowser: migration plan (PHP → Go + vanilla JS)

## 1. Where things stand

**Legacy (PHP, on `master` and copied to `legacy/`):** php-fpm, nginx, MongoDB 4.4, mongo-express and a separate scanner container.
- `Scanner.php` walks `BASEDIR` once (only when the DB doesn't exist yet). It stores EXIF/ffprobe metadata plus an md5 of every file in Mongo, then runs an inotify watcher for new files.
- `DirHandler.php` renders a folder page on the server from Mongo: breadcrumb, previous/next sibling folder, a justified mosaic (jquery.mosaic), a lightbox with delete/download/info (info is a stub), and folder tags.
- `ImageHandler.php` / `VideoHandler.php` serve libvips thumbnails (300/960) cached on disk, ffmpeg video stills with a play icon composited in, and Range-streamed video.

**New (`gogo` branch):**
- `go-backend/`: chi router. `/api/directory` reads the disk on every request: it decodes every image header and runs `ffprobe` for every video, every time. `/api/media/*` is an `http.FileServer` (Range support for free). No thumbnails, no index.
- `frontend/`: vanilla ES modules (justified layout, modal, side panel, keyboard, loading), bundled by webpack. **It was written against `frontend/server.js` (Express), not against the Go API.** It reads `relativePath`, `stats.size/creationTime`, `folder.tags` and `type: 'folder'`, and it calls `/api/breadcrumb`. Checked against the running Go server:
  - `/api/breadcrumb` returns 404, which makes `main.js`'s `Promise.all` fail, so no folder loads at all.
  - Responses use `path` instead of `relativePath`, and folders have no `type` or `tags`.
  - Both backends ignore `?size=300`: Go returns the full 1.9 MB original for a 300 px request. So the "thumbnails" are originals, and video tiles are `<video preload="metadata">` on the original file.
- Loose ends:
  - The 9 MB `go-backend/main` binary is committed.
  - There's no root `Dockerfile` on this branch, so the GitHub Actions docker job (which runs on every branch) can't succeed.
  - `mosaic.js` is never imported, yet keyboard shortcuts 1/2/3 call `setLayoutType()`, which only exists there.
  - `frontend/README.md` documents endpoints that don't exist (search, upload, info, create directory).

**Earlier attempt: photosafe (2020, `~/src/photosafe`; `features/video` is the furthest branch).** Node/Express with sharp on the server, React + Material-UI + Redux on the client. This was the JSON-file store.
- **Worth keeping:**
  - A **timeline** grouped by capture day, newest first. The client first fetches the list of days with counts, then the media for the days on screen, so it can lay out skeletons before thumbnails arrive.
  - **Favorites**, with their own view.
  - An info panel.
  - Indexing progress pushed to the browser over server-sent events.
- **What hurt:**
  - The whole library lived in memory, and every save rewrote every `media/<day>.json` file: rename them all to `.bak`, write everything, delete the backups. That's not atomic, and every save costs the size of the whole library.
  - `convertArrayToHash` fills the wrong object, so `tags.json` and `albums.json` load as empty and are overwritten with nothing on the next save. User-authored data never survived a restart. This is still the case on `features/video`.
  - Ids are `uuid.v5(path)`, so renaming or moving a file orphans its favorite flag.
  - About 1,100 npm packages across the client and server lockfiles, including a native module (sharp). Five years on, upgrading that is a project of its own.
  - Albums, Faces, Things and Trash were placeholder screens.

## 2. Storage decision: SQLite as an index, embedded in the Go binary

### Proposal

Use one SQLite file in a data directory. The Go server opens it itself, so there's no separate service. Use the pure-Go driver `modernc.org/sqlite`: no CGO, so you get a static binary and a trivial Docker build. FTS5 handles search.

Two rules make this work where Mongo and the JSON sidecars didn't:

1. **The disk stays the source of truth for media.** Folders, files, dimensions, EXIF and video probes are *derived* data. They're a cache of the filesystem that the scanner can rebuild at any time. The web server mounts the media volume read-only and never writes next to the photos. Only the importer (§3.6) adds files. It's a separate process, and it never overwrites or deletes anything.
2. **Only what you author lives solely in the DB:** tags, favorites, hidden/"deleted" flags, and later ratings or captions. This data is small, and two things protect it:
   - a nightly `VACUUM INTO` snapshot;
   - `fotodirbrowser export`, which writes all authored data to **one** JSON file, keyed by path + fingerprint. `import` restores it into a fresh DB. That's one file, not one per folder.

### Why not the alternatives

| Option | Verdict |
|---|---|
| Disk only (current Go code) | No search and no tags beyond hand-edited files. Every folder view re-decodes headers and spawns ffprobe per video. |
| JSON files | Tried twice. Sidecars scattered files through the photo tree; photosafe's day-chunked store had to load everything into memory, rewrote everything on each save, and lost tags and albums (see §1). Neither gives you queries or full-text search. |
| MongoDB (keep it) | A separate service plus an admin UI to run and upgrade, a startup race (`waitUntilOnline`), and the duplicate-record cleanup in `Dal::detectDoubles`. Its query power isn't needed at this size. |
| bbolt / Badger (key-value) | Embedded, but you hand-build every index, with no ad-hoc queries and no full-text search. |
| PostgreSQL | Excellent, but it's another service again. |
| SQLite via `mattn/go-sqlite3` | Same database, but it needs CGO, which makes cross-compiling and Docker builds fiddlier. `modernc.org/sqlite` (or `ncruces/go-sqlite3`) avoids that. |

The cost: `modernc.org/sqlite` links 8 pure-Go modules into the binary (`modernc.org/libc`, `memory`, `mathutil` and five small helpers). They upgrade together with one `go get -u`. Checked on this machine: v1.62.0 (SQLite 3.54.1) builds with `CGO_ENABLED=0`, and FTS5 works, including diacritic folding (`cafe` matches `café`) and prefix queries. Scale isn't a concern: a few hundred thousand rows is small for SQLite.

### Dependency budget for the whole app

- **Go:** stdlib, `modernc.org/sqlite` and `github.com/rwcarlsen/goexif` (already used). Drop `chi` and `go-chi/cors`: since Go 1.22 the stdlib `ServeMux` handles patterns like `GET /api/thumb/{id}/{size}`, and serving the frontend from the same origin needs no CORS.
- **External binaries** (from the OS package manager, installed in the Docker image):
  - `ffmpeg`/`ffprobe`: already required.
  - `vipsthumbnail`: the libvips CLI. Legacy already used libvips, through PHP FFI.

  Calling these as separate processes keeps CGO out, and upgrades follow the base image.
- **Frontend: zero npm dependencies and no build step.** Browsers load ES modules natively with `<script type="module" src="/js/main.js">`, and the Go binary embeds `web/` with `go:embed`. This removes express, multer, mime-types, path-to-regexp, webpack, webpack-cli, copy-webpack-plugin and nodemon.
- **Ops:** one container instead of five (nginx, php-fpm, scanner, mongo, mongo-express).
- **Optional ML worker,** only if Phase 10 happens: a separate container with its own pinned dependencies. The main binary doesn't change (§3.5).

## 3. Target architecture

```
fotodirbrowser/
├── go.mod                      # module moved to the repo root
├── cmd/fotodirbrowser/main.go  # subcommands: serve (default), scan, ingest, export, import, import-legacy
├── internal/
│   ├── config/    # env → Config struct
│   ├── store/     # SQLite open, migrations (embedded .sql + PRAGMA user_version), queries
│   ├── scan/      # walker, reconcile, fingerprint, job queue
│   ├── media/     # metadata extraction: EXIF (goexif), ffprobe
│   ├── thumbs/    # thumbnail cache + generation (vipsthumbnail / ffmpeg), worker pool
│   ├── ingest/    # importer (Phase 8): Nextcloud WebDAV + local dirs → YYYY/YYYY-MM-DD/
│   └── api/       # HTTP handlers
├── web/                        # was frontend/public; embedded, or served from disk when DEV=1
├── Dockerfile
├── docker-compose.yml
└── legacy/                     # deleted at cutover
```

The module moves to the root because `go:embed` can't reach `../frontend`, and one module at the root is the conventional layout. The existing empty `internal/config`, `internal/media` and `internal/filesystem` directories fit in: `filesystem` either becomes the safe-path helper or folds into `scan`.

**Configuration (environment variables):**

| Var | Default | Notes |
|---|---|---|
| `MEDIA_DIR` | (required) | The photo tree (legacy `BASEDIR`). Read-only for the server; only the importer writes to it. |
| `DATA_DIR` | `./data` | Holds `fotodirbrowser.db`, `thumbs/` and `backups/`. Replaces `THUMBDIR` and the Mongo volumes. |
| `ADDR` | `:3000` | |
| `SCAN_INTERVAL` | `6h` | Periodic full reconcile; `0` turns it off. |
| `THUMB_WORKERS` | number of CPUs | |
| `DEV` | unset | Serve `web/` from disk instead of the embedded copy. |

**Paths:**
- Resolve every path that comes from the client with `os.Root` (Go 1.24+), which by construction refuses to escape `MEDIA_DIR`. This replaces the current `strings.HasPrefix` check, which also accepts `/media2` when the root is `/media`.
- URLs address media and thumbnails by numeric id, so only folder paths ever come from the client. The legacy `&` → `_*_` hack disappears.

### 3.1 Schema (v1)

```sql
CREATE TABLE folders (
  id         INTEGER PRIMARY KEY,
  parent_id  INTEGER REFERENCES folders(id),
  path       TEXT NOT NULL UNIQUE,          -- '/', '/2020', '/2020/2020-01-01'
  name       TEXT NOT NULL,
  mtime      INTEGER NOT NULL,              -- directory mtime at last reconcile
  hidden     INTEGER NOT NULL DEFAULT 0,
  missing_since INTEGER                     -- directory gone from disk; removed by the next full reconcile
);

CREATE TABLE media (
  id            INTEGER PRIMARY KEY,
  folder_id     INTEGER NOT NULL REFERENCES folders(id),
  name          TEXT NOT NULL,
  kind          TEXT NOT NULL CHECK (kind IN ('image','video')),
  size          INTEGER NOT NULL,
  mtime         INTEGER NOT NULL,
  fingerprint   TEXT NOT NULL,              -- sha256(first 64 KiB ‖ last 64 KiB), matched together with size
  width         INTEGER,                    -- display size, orientation applied
  height        INTEGER,
  orientation   INTEGER,                    -- EXIF 1–8
  taken_at      INTEGER,                    -- wall-clock seconds; DateTimeOriginal / creation_time, else mtime
  duration      REAL,                       -- video only
  camera        TEXT,                       -- "Make Model"
  meta          TEXT,                       -- JSON: remaining EXIF / ffprobe fields for the info panel
  favorite      INTEGER NOT NULL DEFAULT 0,
  hidden        INTEGER NOT NULL DEFAULT 0, -- soft delete
  missing_since INTEGER,                    -- file gone from disk, not yet resolved
  UNIQUE (folder_id, name)
);
CREATE INDEX media_taken ON media(taken_at);
CREATE INDEX media_fav   ON media(taken_at) WHERE favorite = 1;
CREATE INDEX media_fp    ON media(size, fingerprint);

CREATE TABLE tags        (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE COLLATE NOCASE);
CREATE TABLE media_tags  (media_id  INTEGER REFERENCES media(id)   ON DELETE CASCADE,
                          tag_id    INTEGER REFERENCES tags(id)    ON DELETE CASCADE,
                          PRIMARY KEY (media_id, tag_id));
CREATE TABLE folder_tags (folder_id INTEGER REFERENCES folders(id) ON DELETE CASCADE,
                          tag_id    INTEGER REFERENCES tags(id)    ON DELETE CASCADE,
                          PRIMARY KEY (folder_id, tag_id));

-- audit log (legacy `logs` collection)
CREATE TABLE events (id INTEGER PRIMARY KEY, at INTEGER NOT NULL, op TEXT NOT NULL,
                     path TEXT NOT NULL, detail TEXT);

CREATE VIRTUAL TABLE search USING fts5(
  kind UNINDEXED, ref UNINDEXED,            -- 'folder' | 'media', row id
  name, path, tags, camera,
  tokenize = 'unicode61 remove_diacritics 2'
);
```

**Time zones:** EXIF `DateTimeOriginal` has no time zone. Store `taken_at` as wall-clock seconds (treat the value as UTC and never convert it), and group days with `date(taken_at, 'unixepoch')`. Otherwise photos taken near midnight land on the wrong day in the timeline. Video `creation_time` is real UTC, so convert it to local wall-clock time once, at extraction.

Open the database with `journal_mode=WAL`, `foreign_keys=ON`, `busy_timeout=5000` and `synchronous=NORMAL`. Write the SQL by hand, without an ORM. Whenever the store writes media, folders or tags, it also updates the matching `search` rows, in explicit code rather than triggers.

### 3.2 Keeping the index fresh (replaces Scanner.php + DirWatcher.php)

The first versions don't need inotify. Three mechanisms keep the index current:

1. **Lazily, per folder.** Opening a folder compares its on-disk mtime with `folders.mtime`. If they differ, a file was added, removed or renamed, so only that folder is reconciled before the response goes out. This covers "I just rsynced a new day in" cheaply.
2. **Periodic full reconcile**, every `SCAN_INTERVAL` and once at startup. It walks the tree and stats every file, but re-extracts metadata only where `(size, mtime)` changed. It catches in-place edits, which don't change the folder's mtime, and anything else that was missed.
3. **Manually**, with `POST /api/rescan?path=/2020` or `fotodirbrowser scan [--path /2020] [--reextract]`. These replace `--forcescan=<year>` and `--dropdb`.

**Reconcile rules:**
- **Skip junk:** dotfiles and dot-directories (rsync temp files) and NAS folders like `@eaDir`. Start by indexing `.jpg`/`.jpeg` and browser-playable video (`.mp4`, `.m4v`, `.mov`); other formats go in a config list.
- **New file:** fingerprint it (a 128 KiB read), then look for an existing row with the same `(size, fingerprint)` whose file no longer exists at its old path.
  - If one matches, the file was **moved or renamed**: re-point the old row, which keeps its id, tags and hidden flag.
  - Otherwise, insert a new row and queue metadata extraction.
- **File gone:** set `missing_since`, which removes it from listings immediately. Only a full reconcile deletes rows that are still missing at the end. Deleting a row also deletes its tags, and the deletion is logged in `events`. Folders follow the same rule with their own `missing_since`.
- **Worker pool:** metadata extraction and thumbnail pre-generation run in a bounded pool, and folders the user opens get priority.
- **Fresh DB:** the UI is usable straight away, because folders appear as soon as the walk reaches them. Legacy showed nothing until `dirScanned` was true.
- **Progress:** `GET /api/status` reports folders and files seen and the queue length. `GET /api/events` streams the same information as server-sent events (stdlib only, the way photosafe did it) to drive a live indicator in the UI.

fsnotify can come later if lazy plus periodic reconciling isn't enough. It's an extra dependency, and inotify doesn't see changes made from another machine on NFS/SMB mounts anyway.

### 3.3 Thumbnails (replaces ImageEngine, VideoEngine, ImageHandler, VideoHandler)

- **Two sizes** (legacy used 300 and 960):
  - `thumb`, at most 600 px tall: crisp at 2× pixel density for justified rows of about 300 px.
  - `preview`, at most 2048 px on the long edge: for the lightbox.
- **Images:** `vipsthumbnail <src> --size x600 -o <abs-dst>.jpg[Q=82]`. vipsthumbnail applies the EXIF rotation automatically.
- **Videos:** `ffmpeg -ss 2 -i <src> -frames:v 1 -vf scale=-2:600 <dst>.jpg`. For clips shorter than 2 s, retry with `-ss 0`. ffmpeg applies the rotation automatically. The play icon is a CSS overlay (the frontend already has `.video-overlay`) rather than being composited into the JPEG.
- **Cache location:** `DATA_DIR/thumbs/<thumb size>/<ab>/<sha1(relpath|filesize|mtime)>.jpg`.
  - An edited file gets a new key, nothing depends on DB ids, and deleting the directory is always safe.
  - This fixes a legacy bug: video stills were keyed by filename alone, so every `GOPR0001.MP4` across all folders shared one thumbnail.
- **Generation and serving:**
  - Write to a temp file, then rename it, so a thumbnail is never half-written.
  - Deduplicate concurrent requests for the same key with a map and a mutex.
  - Serve with `Cache-Control: public, max-age=31536000, immutable` and an ETag.
  - Version the URLs with the file's mtime (`/api/thumb/812/thumb?v=1577870000`). `/api/dir` returns them ready-made, so an edited photo gets a new URL and `immutable` stays safe.

### 3.4 HTTP API

| Method & path | Purpose | Replaces |
|---|---|---|
| `GET /api/dir?path=/2020/2020-01-01&sort=taken\|name` | Folder, breadcrumbs, previous/next sibling, folder tags, subfolders (with tags and counts) and media | `DirHandler.php`, `/api/directory`, `/api/breadcrumb` |
| `GET /api/thumb/{id}/{size}` | Cached JPEG | `ImageHandler.php?size=`, `VideoHandler.php?size=` |
| `GET /api/file/{id}[?download=1]` | Original via `http.ServeContent` (Range, If-Modified-Since, 304); `download=1` adds `Content-Disposition: attachment` | `ImageHandler.php` with size 0, `VideoStream.php`, `/api/media/*` |
| `GET /api/media/{id}` | Full metadata and tags for the info panel | Info button (a stub in legacy) |
| `DELETE /api/media/{id}`, `POST /api/media/{id}/restore` | Hide / unhide | `ImageHandler.php` DELETE |
| `PUT /api/media/{id}/tags`, `PUT /api/folder/tags?path=` | Set tags | `tags` file and the broken form |
| `POST /api/tags/apply` `{ids, add[], remove[]}` | Tag a selection in bulk | (new) |
| `GET /api/tags` | All tags with counts, for autocomplete and the tag cloud | (new) |
| `PUT /api/media/{id}/favorite` `{favorite: bool}` | Favorite toggle | photosafe favorites |
| `GET /api/search?q=…&cursor=` | Search (Phase 5) | (new) |
| `GET /api/timeline/days?favorite=1&q=…` | `[{day, count}]`, newest first, so the client can lay out skeletons | photosafe `/timeline` |
| `GET /api/timeline?days=2020-01-01,2020-01-02` | Media for the days on screen, grouped by day | photosafe `/timeline/multiple` |
| `POST /api/rescan?path=`, `GET /api/status`, `GET /api/events` (SSE), `GET /health` | Operations | `Scanner.php` CLI flags, photosafe `/updates` |

The whole frontend uses one flat JSON shape for media:

```json
{ "id": 812, "kind": "image", "name": "IMG_1234.jpg", "path": "/2020/2020-01-01/IMG_1234.jpg",
  "width": 4000, "height": 3000, "takenAt": 1577870000, "size": 5120345,
  "duration": null, "tags": ["beach"], "hidden": false,
  "thumb": "/api/thumb/812/thumb?v=1577870000", "preview": "/api/thumb/812/preview?v=1577870000" }
```

The default sort is `taken_at, name`, so folders that mix phone and camera files come out in chronological order. `sort=name` gives the legacy case-insensitive filename order.

Optional: redirect old bookmarks from `DirHandler.php?fileLocation=/x` to `/?path=/x`.

### 3.5 Optional: things and faces (local ML)

This isn't part of the migration. It's written down so the schema and pipeline leave room for it, and it's built in Phase 10. Everything runs on the NAS, and no photo leaves it.

**What it adds:**
- **Things:**
  - Search by content in plain language ("dog on the beach", "birthday cake", "snow").
  - "More like this" from the lightbox.
  - Grouping of near-identical burst shots, so you can keep the best one and hide the rest.
  - Optional automatic labels (`thing:dog`).
- **Faces:** detect faces and group them into suggested people. You name a group once, and new photos of that person are recognized from then on. Adds `person:anna` in search and a People view.

**How it fits:**
- **The Go server stays free of CGO and ML frameworks.** ML runs in an optional, stateless worker container that the server calls over HTTP. When `ML_URL` is unset or the worker is down, the ML features are hidden and pending work simply waits.
- **The server owns the database and the work queue.** The worker only turns pixels into numbers: a JPEG goes in, vectors and boxes come out. You can swap the worker (other models, another language) without touching your authored data.
- **The queue is a query, not a table:** "media without an embedding for the current model". That makes it resumable for free, and switching models just means re-running in the background.
- **Input:**
  - Photos use the preview JPEG the thumbnail step already made; faces need that resolution.
  - Videos use their thumbnail frame to start with.
- **Throttling for a NAS:** `ML_CONCURRENCY`, plus an optional run window (`ML_HOURS=01-07`).

**Worker contract.** The API is ours, so it doesn't depend on any other project:

| Endpoint | Returns |
|---|---|
| `GET /info` | Model names and vector dimensions |
| `POST /embed/image` (JPEG body) | L2-normalized image vector |
| `POST /embed/text` `{text}` | Text vector in the same space |
| `POST /faces` (JPEG body) | `[{box, score, landmarks, vec}]` |

**Models.** These are reasonable picks as of 2026; re-check what's current when you start.
- **Things:** a CLIP-family dual encoder exported to ONNX.
  - Default: SigLIP 2 base (Apache-2.0, 768-d). It's multilingual, so queries don't have to be in English.
  - If speed matters more than quality: OpenCLIP ViT-B/32 (512-d).
- **Faces:**
  - Best quality: InsightFace `buffalo_l` (SCRFD detector + ArcFace R50, 512-d). Its weights are licensed for non-commercial use only, which is fine for a personal library.
  - Permissive alternative: YuNet + SFace from the OpenCV model zoo (MIT/Apache-2.0), somewhat less accurate.
- **Captions** from a small vision-language model (via llama.cpp) are possible, but they take seconds per photo on a CPU. If ever, only for favorites or on demand.

**Worker implementation:**
- **Recommended:** Python using `onnxruntime`, `numpy`, `pillow` and `tokenizers`, pinned in `requirements.txt` (no PyTorch), with the stdlib HTTP server.
  - About 300 lines, in its own container, with the model files and their checksums baked into the image.
  - numpy keeps the pre- and post-processing short: resize/normalize, decoding the detector's boxes, non-maximum suppression and face alignment.
- **If you'd rather stay in Go:** a separate `fotodirbrowser-ml` binary using `onnxruntime_go`.
  - CGO and `libonnxruntime.so` stay confined to that binary.
  - But you write the CLIP tokenizer and the detector post-processing in Go yourself, and the wrapper's version has to match the runtime library's.
- **For a quick experiment only:** Immich's machine-learning container already serves CLIP and face models over HTTP. Its API is internal to Immich and changes between releases, so don't build on it long-term.

**Hardware.** Plan on the CPU alone.
- Recent Zen cores have AVX-512, which onnxruntime's CPU kernels use, and int8-quantized models run faster still.
- AMD GPU (ROCm) or NPU acceleration on Linux is possible but fiddly. It's only worth it if the CPU benchmark disappoints.
- Ballpark figures, to be verified in the first step of Phase 10:
  - tens of milliseconds per photo for embeddings;
  - around 0.1 s per photo for faces.

  So a library of 100k photos takes a few hours to a night, and after that only new photos are processed.

**Schema additions** (migration v2, only once this starts):

```sql
CREATE TABLE embeddings (
  media_id INTEGER NOT NULL REFERENCES media(id) ON DELETE CASCADE,
  model    TEXT NOT NULL,                    -- e.g. 'siglip2-b16-256'
  vec      BLOB NOT NULL,                    -- L2-normalized, float16 little-endian
  PRIMARY KEY (media_id, model)
);
CREATE TABLE people (
  id            INTEGER PRIMARY KEY,
  name          TEXT,                        -- NULL = suggested group, not named yet
  cover_face_id INTEGER,
  hidden        INTEGER NOT NULL DEFAULT 0   -- strangers: stop suggesting them
);
CREATE TABLE faces (
  id        INTEGER PRIMARY KEY,
  media_id  INTEGER NOT NULL REFERENCES media(id) ON DELETE CASCADE,
  model     TEXT NOT NULL,
  box       TEXT NOT NULL,                   -- x,y,w,h as fractions of the displayed (rotated) image
  score     REAL NOT NULL,
  vec       BLOB NOT NULL,                   -- 512-d, L2-normalized
  person_id INTEGER REFERENCES people(id) ON DELETE SET NULL,
  source    TEXT                             -- 'auto' | 'user' | 'rejected' (= user said: not person_id)
);
CREATE INDEX faces_media  ON faces(media_id);
CREATE INDEX faces_person ON faces(person_id);
CREATE TABLE labels (                        -- automatic 'things', kept apart from your own tags
  media_id INTEGER NOT NULL REFERENCES media(id) ON DELETE CASCADE,
  label    TEXT NOT NULL,
  score    REAL NOT NULL,
  PRIMARY KEY (media_id, label)
);
```

**Vector search without a vector database:**
- Load the current model's vectors into memory at startup: roughly 150–300 MB for 100k photos, depending on int8 or float32.
- Search with a brute-force dot product spread over goroutines, which takes tens of milliseconds per query at this scale.
- No extension and no extra service: `sqlite-vec` would need C extension loading, which the pure-Go driver doesn't support.
- SQL filters (date, tag, person, hidden) run first, and similarity ranks what's left.

**Faces: grouping, and keeping your corrections:**
- Ignore faces that are small (say under 40 px on the preview) or low-confidence; they group badly.
- Compare each new face with the centroid of every named person. Above the threshold it's assigned automatically (`source='auto'`); otherwise it stays unassigned.
- Periodically group the unassigned faces. Greedy centroid clustering costs O(faces × groups), which is fine in Go. Groups with at least N faces show up as suggested people.
- **Your actions are authored data**, so they go into `export`: naming a group, merging two people, "not this person", hiding a stranger.
- **Corrections survive a model change.** Face rows are derived. After re-detecting with a new model, carry each person over by matching boxes on the same photo by overlap (IoU). It's the same idea that keeps tags through file moves.
- **Tune the thresholds first.** They depend on the model, so tune them on a small labeled set of your own photos before trusting automatic assignment.

### 3.6 Importing from Nextcloud (and SD cards)

Both phones auto-upload to Nextcloud accounts on Hetzner Storage Share. Nextcloud stays what it is: online storage, not a gallery. The importer copies new photos from it into the library's `YYYY/YYYY-MM-DD/` folders. Local folders such as an SD card go through the same pipeline, replacing the old `copySD`.

**How it runs:**
- **A separate process from the same binary.** `fotodirbrowser ingest` runs apart from the web server. In Docker it's a second service from the same image, with the media volume mounted read-write; the web server's mount stays read-only.
- **Scheduling:** it loops with `--every 30m`, or runs once per invocation from a systemd timer or cron.
- **It only adds files.** It never overwrites or deletes anything in the library.
- **State lives in the main database** (the tables below). Two processes on one machine are fine for SQLite with WAL and `busy_timeout`, but `DATA_DIR` must be on a local disk, not NFS/SMB, or SQLite's locking breaks.
- **It tells the server what changed.** After a run it calls `POST /api/rescan?path=` for each folder it touched, so new photos appear in the timeline right away. Lazy reconcile alone isn't enough here: it only triggers when a folder is opened, and the timeline never opens folders.

**Talking to Nextcloud.** Plain WebDAV using `net/http` and `encoding/xml`, about 150 lines, with no WebDAV library.
- **Endpoint:** `https://<instance>.your-storageshare.de/remote.php/dav/files/<user>/<folder>`.
- **Credentials:** a Nextcloud app password per account (Settings → Security → Devices & sessions), named `fotodirbrowser`, which you can revoke without changing the real password. App passwords give full access to the account, so keep them in a Docker secret or a mode-600 file.
- **Listing:** `PROPFIND` with `Depth: 1`, walking down the tree, asking for `getetag`, `getlastmodified`, `getcontentlength`, `resourcetype` and `oc:fileid`.
- **Incremental runs:** Nextcloud changes a folder's etag whenever anything below it changes; that's how its desktop client syncs. Store the etag per remote folder and skip folders whose etag hasn't changed. A run with nothing new then costs about one request per account.
- **In-progress uploads:** skip files modified in the last 10 minutes, as a guard against uploads that haven't finished.

**Per file:**
1. **Already handled?** Look up `(account, oc:fileid)` in `imports`. A known file id is never imported twice, even if the local copy was later moved, renamed or hidden.
2. **Already in the library?** This covers photos you copied by hand before the importer existed. Only when a media row has the same size, fetch the first and last 64 KiB with two `Range` requests and compare fingerprints. A match is recorded as a duplicate, without downloading the file.
3. **Download** into `MEDIA_DIR/.ingest-tmp/`. It's a dot-directory, so the scanner ignores it, and it's on the same filesystem, so the final move is atomic.
4. **Capture date**, using the first of these that's available:
   - EXIF `DateTimeOriginal` or the video's `creation_time` (the `media` package from Phase 1);
   - the date in phone file names: `IMG_20240101_123456`, `PXL_…`, `VID_…`, Samsung's `20240101_123456`;
   - the remote last-modified time.
5. **Place** it at `YYYY/YYYY-MM-DD/<name>`:
   - Use `os.Link` and then remove the temp file. `os.Link` fails rather than overwriting when the name already exists.
   - If the name is taken by different content, use `<name>_2.jpg`.
   - Set the file's mtime to the remote last-modified time.
6. **Record** the outcome (`imported`, `duplicate` or `error`). Errors are retried on the next run.

**Configuration** lives in `DATA_DIR/ingest.json`; stdlib JSON, so no config-format dependency:

```json
{
  "nextcloud": [
    {
      "name": "samuel",
      "url": "https://<instance>.your-storageshare.de",
      "user": "samuel",
      "passwordFile": "/run/secrets/nextcloud-samuel",
      "folders": ["/InstantUpload/Camera"],
      "exclude": ["*/Screenshots/*", "*/WhatsApp*/*"]
    },
    {
      "name": "wife",
      "url": "https://<instance>.your-storageshare.de",
      "user": "<her user>",
      "passwordFile": "/run/secrets/nextcloud-wife",
      "folders": ["/InstantUpload/Camera"]
    }
  ]
}
```

The folder names are wherever each phone app uploads to; check them in the Nextcloud web UI.

**Schema additions** (a migration when this phase starts):

```sql
CREATE TABLE imports (
  source      TEXT NOT NULL,      -- account name from ingest.json, or 'dir'
  remote_id   TEXT NOT NULL,      -- Nextcloud oc:fileid; for a local dir: size + fingerprint
  remote_path TEXT NOT NULL,
  size        INTEGER NOT NULL,
  fingerprint TEXT,
  local_path  TEXT,               -- where it was placed, or the existing copy it matched
  outcome     TEXT NOT NULL,      -- 'imported' | 'duplicate' | 'error'
  detail      TEXT,               -- error message
  at          INTEGER NOT NULL,
  PRIMARY KEY (source, remote_id)
);
CREATE TABLE import_folders (     -- remote folder etags, to skip unchanged folders
  source      TEXT NOT NULL,
  remote_path TEXT NOT NULL,
  etag        TEXT NOT NULL,
  PRIMARY KEY (source, remote_path)
);
```

**Also useful:**
- **`--dry-run`** prints where every file would go. Run it once before the first real run, which will find many photos that are already in the library.
- **A `from:wife` search filter**, backed by `imports.source`: "photos from her phone" without tagging anything.
- **Import status:** `/api/status` shows the last run per account, with counts and errors. An expired app password shows up there instead of imports silently stopping.
- **HEIC:** iPhones shoot HEIC by default. The importer copies it like any other file, but the gallery can only show it once HEIC support exists (Phase 9). Bring that forward if either phone is an iPhone.
- **Deleting from Nextcloud after import,** to save paid storage, is possible but off by default; see the open questions.

## 4. Feature inventory: legacy → new

| # | Legacy feature | Legacy source | New home | Phase |
|---|---|---|---|---|
| 1 | Folder listing (folders + jpg/mp4/m4v), sorted by name | `DirHandler.php`, `Dal::getMediaForDirectory`, `Media::cmp_obj` | `GET /api/dir`, `gallery.js` | 2 |
| 2 | Breadcrumb with Home | `DirHandler.php` | `/api/dir` breadcrumbs, `breadcrumb.js` | 2 |
| 3 | Previous/next sibling folder | `Dal::getPreviousAndNextDirectory` | `/api/dir` prev/next | 2 |
| 4 | Justified mosaic using stored dimensions (no layout jump) | jquery.mosaic + stored width/height | `justified.js` with width/height from the DB | 2 |
| 5 | Image thumbnails (300) and previews (960), disk cache, 304 responses | `ImageEngine::resizeImageFromPath`, `ImageHandler.php` | `thumbs` package, `/api/thumb` | 2 |
| 6 | Video still at 2 s with a play icon | `VideoEngine::getStillFromVideo` | `thumbs` package + CSS overlay | 2 |
| 7 | Video streaming with Range | `VideoStream.php` | `/api/file` (`http.ServeContent`) | 2 |
| 8 | Lightbox: previous/next, caption, video controls | `DirHandler.php` inline JS | `modal.js` (keyboard and swipe already exist) | 2 |
| 9 | Download the original with its filename | `downloadItem()` | `/api/file?download=1` + a plain `<a download>` | 2 |
| 10 | Soft delete; an emptied folder is hidden too; logged | `Dal::delete`, `logs` collection | `hidden` flag, `events`, restore + "show hidden" view | 3 |
| 11 | Folder tags from a `;`-separated `tags` file, shown as a tag cloud on folder tiles and the page | `Folder::getTags`, `DirHandler.php` | `folder_tags`, imported once from `tags`/`.tags` files | 4 |
| 12 | Add-tag form | `DirHandler.php`. It never worked: `isset($POST)` is a typo, and the media mount is read-only. | Tag editor in the UI, stored in the DB | 4 |
| 13 | Info button | Stub (`getInfo() {}`) | Info panel from `GET /api/media/{id}` | 6 |
| 14 | Metadata: EXIF subset, w/h swapped for portrait orientation; ffprobe duration, codec, fps, rotation, audio | `ImageEngine::getMetaData`, `VideoEngine::getMetaData` | `media` package → columns + `meta` JSON | 1 |
| 15 | md5 of every file | `Media::withAbsoluteDirAndFilename` | Partial fingerprint (detects moves); a full hash only if a duplicate finder is ever built | 1 |
| 16 | Initial scan, `--dropdb`, `--forcescan=<year>` | `Scanner.php` | `fotodirbrowser scan [--path] [--reextract]` | 1 |
| 17 | inotify watcher for new files and folders | `DirWatcher.php` | Lazy per-folder + periodic reconcile | 1 |
| 18 | Duplicate-record cleanup | `Dal::detectDoubles` | Not needed: `UNIQUE(folder_id, name)` prevents duplicates | n/a |
| 19 | SD-card import into `YYYY/YYYY-MM-DD/` | `copySD/index.php` (marked "probably not working") | `fotodirbrowser ingest --dir <path>`, the same pipeline as the Nextcloud import (§3.6) | 8 |
| 20 | Docker (nginx, php-fpm, scanner, mongo, mongo-express) and CI pushing to ghcr.io | `Dockerfile`, compose files, workflow | One image, one service | 0, 7 |

**Legacy bugs not to carry over:**
- Video thumbnails cached by filename only.
- The `mkdir($thumbfoler…)` typo.
- `FileDateTime` used as the capture date. It's actually the file's mtime; use `DateTimeOriginal` instead.
- A full-file md5 in the scan's critical path.
- A preview `<img>` for every item, all rendered up front inside the modal.

**From photosafe:**

| # | photosafe feature | New home | Phase |
|---|---|---|---|
| P1 | Timeline by capture day, with days fetched first and then media per visible day | `/api/timeline/days` + `/api/timeline`; reuse the gallery and justified layout with day headers | 5 |
| P2 | Favorites + Favorites view | `media.favorite`, a heart toggle, the timeline filtered with `favorite=1` | 4, 5 |
| P3 | Indexing progress over SSE | `GET /api/events` | 1 |
| P4 | Info panel | `GET /api/media/{id}` | 6 |
| P5 | heic/dng/nef/png in the file mapping | Later: depends on libvips being built with libheif/libraw | 9 |
| P6 | Albums (placeholder) | Starts as tags; real albums later, if needed | 9 |
| P7 | Faces, Things (placeholders) | Optional local ML in a separate worker container (§3.5) | 10 |

## 5. Phases

Each phase ends with something that runs. Commit the in-progress width/height/orientation work first: Phase 1 builds on it.

Step-by-step tutorials, for writing the code yourself while learning Go: [Phase 0](tutorial-phase-0.md), [Phase 1](tutorial-phase-1.md), [Phase 2](tutorial-phase-2.md).

### Phase 0: Clean the ground
- [ ] `git rm --cached go-backend/main`, and ignore built binaries.
- [ ] Move the Go module to the repo root (layout in §3) and `frontend/public` → `web/`.
- [ ] Bump the `go` directive in `go.mod` from 1.23.8 to 1.27, the installed toolchain (1.24 is the minimum, for `os.Root`). Replace chi/cors with `http.ServeMux` plus a small logging/recover middleware.
- [ ] In `web/index.html`, load `<script type="module" src="/js/main.js">`. Embed `web/` with `go:embed`, and serve it from disk when `DEV=1`.
- [ ] Delete:
  - `frontend/server.js`, `package.json`, `webpack.config.js`, `frontend/README.md` and `frontend/.github/`;
  - the unused `mosaic.js` and `mosaic.css`, and the 1/2/3 layout shortcuts.
- [ ] Add a root `Dockerfile` so CI builds again: multi-stage, `golang` → `debian:stable-slim` with `ffmpeg libvips-tools`, `CGO_ENABLED=0`.
- [ ] Replace the PHP Xdebug `.vscode/launch.json` with a delve configuration.
- **Done when:** `go run ./cmd/fotodirbrowser` serves the current UI and `docker build .` succeeds.

### Phase 1: Index + scanner
- [ ] `store`:
  - Open the DB with the pragmas above.
  - Add embedded migrations tracked by `PRAGMA user_version`, and schema v1.
- [ ] `media`:
  - Port `getImageDimensions` and `getVideoDimensions` from `directory.go`.
  - Add `DateTimeOriginal`, Make/Model and the legacy EXIF subset; from ffprobe add duration, creation_time, codec and audio.
  - For rotation, read both `stream_tags=rotate` and `stream_side_data=rotation`: newer ffprobe versions only report the display-matrix side data.
- [ ] `scan`: walker, reconcile rules (§3.2), fingerprint and move detection, worker pool, `/api/status`.
- [ ] CLI: `serve` (the default) and `scan`.
- [ ] Tests against a temp media directory seeded from `docs/example`:
  - new file, removed file, changed file (re-extracted);
  - renamed or moved file keeps its id;
  - dotfiles are skipped.
- **Done when:** a scan of the real library completes, and a second scan changes nothing.

### Phase 2: Browsing parity
- [ ] Endpoints: `GET /api/dir`, `/api/file/{id}` and `/api/thumb/{id}/{size}`, plus the `thumbs` package (§3.3).
- [ ] Move the frontend to the new contract:
  - `api.js`: drop the search/upload/createDirectory stubs for now.
  - `main.js`: one `/api/dir` call instead of directory + breadcrumb.
  - `gallery.js`: thumbnail URLs; render videos as thumbnail images with the overlay instead of `<video preload="metadata">` on the original.
  - `modal.js`: preview size, and `/api/file` for video.
  - `sidebar.js`: new field names.
  - Download: `<a href="/api/file/{id}?download=1">` instead of fetch → blob.
- [ ] Deep links: `?path=` for folders (already works), plus `&media=<name>` to open the lightbox.
- [ ] Optional: a cover image per folder tile instead of the folder icon.
- **Done when:** opening a large folder loads only thumbnails, the layout doesn't jump, and videos can seek.

### Phase 3: Hide/delete
- [ ] `DELETE` and `restore`.
- [ ] Hide a folder automatically once nothing visible remains in it (legacy behavior), and write `events` rows.
- [ ] UI: deleting from the lightbox or side panel removes the tile, and a "show hidden" toggle allows restoring.
- **Done when:** delete matches legacy, plus undo.

### Phase 4: Tags and favorites
- [ ] Favorites: a heart toggle on tiles and in the lightbox, plus an `f` keyboard shortcut. The Favorites view arrives with the timeline in Phase 5; until then, add a "favorites only" filter on the folder view.
- [ ] Import tag files when a folder is first indexed:
  - `tags`: legacy, `;`-separated;
  - `.tags`: current Go code, one tag per line.

  After that the DB is the source of truth and the files are left alone.
- [ ] Endpoints from §3.4, with tags included in `/api/dir` responses.
- [ ] UI:
  - Tag chips with add/remove in the side panel and lightbox. Autocomplete via `<input list>` and a `<datalist>` filled from `/api/tags`.
  - The folder tag cloud on folder tiles (legacy parity).
  - Multi-select tiles (click / shift-click) with "tag selection" for bulk tagging.
- **Done when:** a whole day's folder can be tagged in a few clicks, and tags and favorites survive a full rescan, a file rename and a server restart.

### Phase 5: Search and timeline
- [ ] Keep the `search` FTS rows in sync: filename, folder path split into words, tags (including folder tags) and camera.
- [ ] Parse the query syntax in Go:
  - Plain words become FTS prefix matches (`beach*`).
  - `tag:x`, `type:video`, `camera:x`, `after:2019-06` and `before:2020` become SQL filters on `media`.
  - Paginate with a cursor on `(taken_at, id)`.
- [ ] UI: a search box in the nav bar. Results reuse the gallery, justified layout and lightbox. Searches live in the URL as `?q=` so they can be bookmarked.
- [ ] Timeline, ported from photosafe:
  - `/api/timeline/days` is a `GROUP BY date(taken_at, 'unixepoch')` that accepts the same filters as search. `/api/timeline` returns the media for the requested days.
  - UI: a Timeline view with day headers and skeletons sized from the day counts. Load days as they scroll into view (`IntersectionObserver`, no library). The lightbox works across day boundaries.
  - The Favorites view is the timeline with `favorite=1`, and a search can be shown as a timeline too.
- **Done when:**
  - `tag:kona after:2020` quickly returns the right photos from the full library.
  - Scrolling the timeline from today back to the oldest photo stays smooth.

### Phase 6: Info panel
- [ ] Back the side panel with `GET /api/media/{id}`. It shows camera, exposure, ISO, focal length, dimensions, duration/codecs, size, capture date and path, with a link to the containing folder.

### Phase 7: Legacy data migration and cutover

MongoDB holds one thing that isn't on disk: **which files were "deleted"**. Legacy delete only set `deleted: true`, so the files are still there. If Mongo is dropped without exporting this, every deleted photo reappears.

- [ ] Export with the Mongo command-line tools, so the Go app needs no Mongo driver:
  ```bash
  mongoexport --uri "$MONGO_URL" --db fotodirbrowser --collection media \
    --query '{"deleted": true}' --fields relativePath,type,md5sum --out deleted.json
  mongoexport --uri "$MONGO_URL" --db fotodirbrowser --collection logs --out logs.json
  ```
- [ ] `fotodirbrowser import-legacy --deleted deleted.json --logs logs.json`:
  - sets `hidden=1` on matching media and folders, matched by relative path;
  - copies the logs into `events`;
  - reports anything it couldn't match.
- [ ] Run the new and old versions side by side (on different ports) for a while. Compare counts per year: legacy non-deleted media vs. new visible media.
- [ ] Add the `export`/`import` commands for authored data (tags, favorites, hidden flags), and a nightly `VACUUM INTO DATA_DIR/backups/…` that keeps the last N snapshots.
- [ ] New `docker-compose.yml` with one service, `MEDIA_DIR` mounted `:ro` and a `DATA_DIR` volume. Point CI at it.
- [ ] Remove `legacy/`, the Mongo/nginx deploy files and the old thumbnail volume, then merge `gogo` into `master`.

### Phase 8: Import from Nextcloud (and SD cards)

The design is in §3.6. This phase can be pulled forward to any point after Phase 1: it needs the index, fingerprints and date extraction, but not the UI.

- [ ] **Ingest pipeline:** temp download, capture date, dedupe by fingerprint, placement that never overwrites, `imports` records, and the rescan call to the server.
- [ ] **Nextcloud source:** a WebDAV `PROPFIND` walk using folder etags, `Range`-based fingerprints for files with a size match, and app passwords read from files.
- [ ] **Directory source:** `fotodirbrowser ingest --dir /mnt/sdcard` (the old `copySD`). It only reads from the card.
- [ ] **Options:** `--dry-run` and `--every`, the `from:` search filter, and the last run per account in `/api/status`.
- [ ] **Compose:** a second service, `ingest`, from the same image, with media mounted read-write and both app passwords as secrets.
- [ ] **Tests** against a fake WebDAV server (`httptest` with a few canned `PROPFIND` responses):
  - a new file;
  - an unchanged folder is skipped;
  - a duplicate is detected without downloading;
  - a name collision;
  - an error is retried on the next run.
- **Done when:** a photo taken on either phone shows up in the timeline within one interval, and the first run doesn't duplicate photos you had already copied by hand.

### Phase 9: Later, if wanted
- An fsnotify watcher, if lazy plus periodic reconciling isn't enough.
- More catalog features:
  - a duplicate finder (full hash, computed lazily);
  - ratings/favorites and captions (each is one column or table);
  - "on this day";
  - a map view, if GPS is extracted.
- HEIC/RAW (photosafe already mapped heic/dng/nef): thumbnails work if libvips is built with libheif/libraw, but the lightbox would need to show the preview JPEG instead of the original.
- Real albums (an ordered set with a cover), only if tags turn out not to be enough.

### Phase 10 (optional): things and faces

The design is in §3.5. Each step is useful on its own, so stop wherever it stops being worth it.

- [ ] **Benchmark first.** Run the candidate models with onnxruntime on the NAS against about 500 of your own photos. Measure the time per photo, and judge quality by eye:
  - semantic search: 20 queries you actually care about;
  - faces: 5 people you know.
- [ ] **Worker and plumbing:**
  - the four-endpoint worker in its own container;
  - `ML_URL`, `ML_CONCURRENCY` and `ML_HOURS`;
  - migration v2 and the queue-as-a-query;
  - progress reported in `/api/events`.
- [ ] **Things:**
  - Compute embeddings for all media.
  - Add a semantic mode to the search box; filters still apply.
  - Add "more like this" to the lightbox.
  - Near-duplicate groups as a culling view: keep one, hide the rest. Groups are photos from the same day above a high similarity threshold, tuned per model.
  - Optional automatic labels from a short vocabulary you control (dog, cat, beach, snow, mountains, food, document, screenshot…). They feed a `thing:` filter and are shown separately from your own tags.
- [ ] **Faces:**
  - Detection and embeddings.
  - A toggle for face boxes in the lightbox; click a box to assign or correct it.
  - People view: named people first, then suggested groups with counts. You can name, merge, hide, and remove wrong faces.
  - `person:` filter in search and the timeline (`person:anna person:ben` means both are in the photo).
  - People and corrections included in `export`/`import`.
- **Done when:** "kids in the snow" and `person:anna after:2024` both find what you expect, and the NAS has caught up and only processes new photos.

## 6. Open questions (defaults chosen; say if you disagree)

- **Delete semantics:** the default is hide-only, as in legacy, so the media mount stays read-only. A real "empty trash" would need a writable mount and would move files into a `.trash` folder.
- **Auth:** legacy had none and relied on the LAN. Delete and tagging now write data, so if the app is ever reachable from outside, put it behind the reverse proxy's basic auth.
- **Formats:** start with jpg/jpeg and mp4/m4v/mov. `.avi`/`.mkv` would be listed as download-only, with no transcoding.
- **Sort:** capture date by default, with filename sort one click away.
- **Landing page:** the folder view, as in legacy, with the timeline (photosafe-style) one click away. Flip it if you mostly browse by date.
- **Nextcloud cleanup:** off by default. An optional `deleteAfterDays` setting would remove files from Nextcloud N days after a verified import; they go to Nextcloud's trash bin first. Before enabling it, check that the phone apps don't re-upload files deleted on the server.
- **ML worker language (Phase 10):** Python + onnxruntime by default. The alternative is Go + `onnxruntime_go`, if you'd rather keep one language and accept CGO in that one binary.
