# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**PRANATA** — a statistics dashboard for BPS Kota Pematangsiantar (Indonesian statistics agency). Users publish statistical tables (indicators), the app auto-detects how to visualize them, and an LLM writes an official-style Indonesian analysis narrative for each one using RAG over BPS PDF publications.

The repo holds **two deployables**:

- **Laravel 12 app** (`app/`, `routes/`, `resources/`) — the web UI, run locally via Herd at `C:\Herd\pranata`.
- **`main.py`** — a FastAPI "AI worker" deployed separately as a Hugging Face Space (`HUGGINGFACE_API_URL` in `.env`). It owns the Qdrant vector store, embeddings, and all LLM calls. The Laravel app only talks to it over HTTP. Editing `main.py` changes nothing until it is redeployed to the Space.

UI text, validation messages, and code comments are in **Indonesian**. Keep new user-facing strings Indonesian.

## Commands

```bash
composer run dev                     # serve + queue:listen + vite, all at once
php artisan serve                    # app only
npm run dev / npm run build          # vite only

composer test                        # config:clear + artisan test
php artisan test --filter=SomeTest   # single test
./vendor/bin/pest tests/Feature/X.php
./vendor/bin/pint                    # format PHP

php artisan migrate --seed           # seeds 3 accounts (see database/seeders/UserSeeder.php)
php artisan storage:link             # REQUIRED — profile photos and uploaded PDFs are served from storage/
```

MySQL database `pranata` (not the sqlite default in `.env.example`). `pranata.sql` at the repo root is a working mysqldump if you need to restore data.

Python worker: `pip install -r scripts/requirements_ingest.txt`, then `uvicorn main:app` — `main.py` has no `__main__` runner of its own, since Spaces supplies the ASGI server.

**Do not run `php artisan config:cache`.** Controllers read `env('HUGGINGFACE_API_URL')` at request time ([DashboardController.php:872](app/Http/Controllers/DashboardController.php#L872), [ModelController.php:86](app/Http/Controllers/ModelController.php#L86), [PengetahuanController.php:75](app/Http/Controllers/PengetahuanController.php#L75)). Caching config makes `env()` return null and silently breaks every AI feature.

## Roles and the three parallel UIs

`role_id` on `users` drives everything — 1 Admin, 2 Pimpinan, 3 Penanggung Jawab, 4 Pengguna Biasa. Roles are seeded by a migration ([0000_01_01_000000_create_roles_table.php](database/migrations/0000_01_01_000000_create_roles_table.php)), not a seeder.

There are three near-duplicate front-ends, one per role tier:

| | Admin (1) | Penanggung Jawab (3) | Pengguna (2 & 4) |
|---|---|---|---|
| views | `resources/views/admin/` | `.../penanggungjawab/` | `.../pengguna/` |
| layout tag | `<x-adminlayout>` | `<x-penanggungjawablayout>` | `<x-penggunalayout>` |
| css/js | `adminstyle.css`, `admin.js` | `pjstyle.css`, `pj.js` | `penggunastyle.css`, `pengguna.js` |
| routes | `/admin/...` (not grouped; legacy `/dashboard`, `/lihatdata` redirect) | `penanggungjawab.` prefix + name | `pengguna.` prefix + name |

Controllers are **shared** across all three; each branches on `role_id` to pick the view path (`DashboardController::index`, `DataController::showDataView`, `PengetahuanController::index`). A change to admin behaviour usually needs the same change in the PJ views, and often the pengguna views too. Pengguna is read-only: no CRUD, no AI generation.

**Authorization is ad-hoc, not middleware.** [bootstrap/app.php](bootstrap/app.php) registers a single custom middleware: `area.role` ([ArahkanKeAreaRole](app/Http/Middleware/ArahkanKeAreaRole.php)), applied to the whole `auth` group. It only *redirects*: when a non-AJAX GET page request lands in another role's route area, it sends the user to the same route name in their own area and keeps the parameters and query string. For example, a Pengguna at `/penanggungjawab/dashboard` is sent to `/pengguna/dashboard`. This usually happens through `redirect()->intended()` after login. A route with no equivalent in the user's area passes through to the controller, which returns 403. Role enforcement lives inside controller methods: `UserController::checkAdminAccess()` (role 1), `DataController::checkManageAccess()` and `PengetahuanController::checkManageAccess()` (roles 1, 3), `ModelController::bolehKelolaModel()` plus `update`, `DashboardController::generateNarrative`/`saveNarrative`, and an `abort_unless` in each role's Tentang Kami route closure. Route group prefixes are a naming convention only, so a route added to the `penanggungjawab` group is reachable by any logged-in user unless its controller checks. Hiding a sidebar menu is not protection, because the URL still works.

Every 403 page shares one design and one fixed message, [errors/403.blade.php](resources/views/errors/403.blade.php). It has inline CSS, so it needs no Vite build, and a role-aware "Kembali ke Dashboard" button. The page ignores the `abort(403, '...')` message; those strings only reach logs and JSON clients. JSON responses behind pop-ups (generate narasi, simpan narasi, Bangunkan AI, success toasts) keep their own messages. Add the check in the controller when adding routes, and add a case to [tests/Feature/HakAksesTest.php](tests/Feature/HakAksesTest.php), which covers the per-role access matrix.

`spatie/laravel-permission` is installed but unused — `User` does not use `HasRoles`, despite the comment saying so. Don't build on it without migrating the whole role system.

## The indicator data contract

`Category → Subject → Indicator → Narrative` (1:1), all with a nullable `user_id` audit column. Everything hangs off `Indicator::$data`, a JSON grid cast to array:

```
{ "headers": [ {value, colspan, rowspan, hidden}, ... ],
  "rows":    [ [ {value, colspan, rowspan, hidden}, ... ], ... ] }
```

`colspan/rowspan/hidden` preserve merged Excel cells and multi-level headers. Two writers produce this shape and must stay in sync: `DataController::storeIndicator` (manual matrix editor, `matrix_data` JSON field) and `DataController::importIndicator` (xlsx/csv via PhpSpreadsheet, which also trims phantom rows/columns and applies `getBpsDictionary()` term standardization — "SLTP"→"SMP", "L"→"Laki-laki", …).

Both normalize Indonesian number formatting on write: `"1.234,56"` is stored as the string `"1234.56"`. Values are always stored as **strings**. Display converts back via `window.formatNumberID` in [resources/js/admin.js](resources/js/admin.js).

## Visualization pipeline (DashboardController)

The ~700 private lines of [DashboardController.php](app/Http/Controllers/DashboardController.php) are one pipeline turning that ragged grid into chart-ready data. Reading order:

1. `parseIndicatorData` → `buildDenseGrid` expands colspan/rowspan into a rectangular grid.
2. Header rows are detected heuristically (a row counts as data once ≥50% of its cells are numeric), then multi-level headers are flattened vertically into `"Parent - Child"` names.
3. Wide→long unpivot: columns whose header contains a year become temporal observations.
4. `detectColumnTypes` classifies each column as `temporal` / `geographic` / `categorical` / `numeric` (`isGeographicColumn` matches kecamatan/kelurahan names).
5. `detectChartTypes` decides which of `line, bar, pie, choropleth, pyramid, card, table` are offered — e.g. pie needs 2–10 unique labels with a *uniform* unit; pyramid needs an age ("umur"/"usia") column.
6. `generateVisualizationConfig` + `generateFilterOptions` emit the axis/series mapping the Blade JS consumes.

Choropleth joins against `public/data/pematangsiantar.json` (GeoJSON); `normalizeKecamatanName` reconciles spelling between the data and the GeoJSON. If that file is missing, choropleth is dropped silently from `available_types`.

Charts are **Chart.js + chartjs-plugin-datalabels + Leaflet loaded from CDN inside the Blade layouts**, not npm — `alpinejs` and `lucide` are the only real runtime npm deps. `getFilteredData` serves client-side filter changes as JSON.

## AI narrative flow

1. Each user picks a model; it is stored per-user in `settings` under key `active_ai_model` ([ModelController.php](app/Http/Controllers/ModelController.php)). Default `llama-3.3-70b-versatile`. The model catalog is a hardcoded array in `ModelController::index` and must match what `main.py` can route.
2. `DashboardController::generateNarrative` POSTs `{model_id, category, subject, indicator, data_json}` to `{HUGGINGFACE_API_URL}/generate-narrative`, 120s timeout.
3. `main.py` retrieves RAG context from Qdrant (collection `bps_knowledge_bge_m3_v1`, `BAAI/bge-m3` embeddings), then `call_narrative_provider()` routes by model id: `gemini*` → Google GenAI, `groq`/`versatile`/`gpt-oss` → Groq (via `get_groq_compact_prompt()`, a shorter prompt that fits Groq's TPM limits), otherwise HF Inference. **There is deliberately no cross-provider fallback** — if the user's chosen model fails, the error surfaces as `status: "error"` naming that model, rather than silently answering from a different one. The only automatic retry is a single re-attempt *with the same model* when the returned text is detected as leaked chain-of-thought instead of prose (`looks_like_leaked_analysis`); if that also fails, the user is told to pick another model manually.
4. The system prompt forces a `<langkah_analisis>` chain-of-thought block; the worker strips it with a regex before returning `narrative_result`. Renaming that tag requires changing both the prompt and the stripper.
5. The user edits and saves via `saveNarrative` → one `Narrative` row per indicator (roles 1 and 3 only).

## Knowledge base ingestion

[PengetahuanController.php](app/Http/Controllers/PengetahuanController.php) manages the RAG corpus. PDFs land in `storage/app/public/dokumen_bps`; `storage/app/processed_log_bge_m3.txt` is a plain-text log of filenames already embedded. Ingest is **batched at 50 files per click** and reconciles against `/check-missing-files` on the worker, filtered by the local log (the log wins, to avoid re-ingest loops, since the Space is stateless). Deletes go through the worker first (`/delete-by-file`, `/delete-all`), then remove the log entry and the physical PDF.

[scripts/local_ingest.py](scripts/local_ingest.py) does the same embedding locally against Qdrant, bypassing the Hugging Face Space entirely (`--file`, `--force`, `--reset`). It has its own `scripts/.env` holding just the Qdrant credentials. Use it when the Space is asleep or for large backfills.

## Frontend build

Tailwind **v4** via `@tailwindcss/vite`, configured CSS-first in [resources/css/app.css](resources/css/app.css) (the `@theme` block holds the BPS palette; `@source` globs cover Blade). The root `tailwind.config.js` is a leftover v2-era stub that nothing loads — editing it has no effect. [vite.config.js](vite.config.js) registers 8 entrypoints (one css + one js per role, plus shared `app.css`/`app.js`); each layout `@vite`s its own pair.

## Repo hygiene

The last line of `.gitignore` is UTF-16-encoded bytes appended to an otherwise UTF-8 file, so the `.tmp.driveupload/` rule never matches. `git status` is permanently noisy with hundreds of deleted `.tmp.driveupload/*` entries (Google Drive sync residue) — ignore them, and stage explicit paths rather than using `git add -A`.

`phpunit.xml` sets `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`, so tests run against an empty in-memory database, not the real `pranata` MySQL. There is no `.env.testing`, and config must not be cached. Two things still leak from `.env` into tests, so fake them the way [HakAksesTest.php](tests/Feature/HakAksesTest.php) does:
- `HUGGINGFACE_API_URL` points at the live Space. Use `Http::fake()` and `Http::preventStrayRequests()`, or `/delete-all` would wipe the real knowledge base.
- `storage_path()` points at the real PDFs and ingest log. Call `$this->app->useStoragePath(<temp dir>)`.

That test also runs `migrate` itself, after a guard that confirms the connection is `:memory:`.
