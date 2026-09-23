# NEP Programme API

Backend API for the National Education Programme (NEP) System — a Laravel 12 application that tracks NGO/organisation programme entries across Cambodia's administrative geography (province → district → commune → village), routes them through an advisory review workflow, and exposes reporting/mapping data to a separate frontend.

## Tech stack

- **PHP 8.2+ / Laravel 12**
- **Auth:** Laravel Sanctum (SPA cookie + token auth), with a custom permission/role system (`app/Http/Middleware/PermissionMiddleware.php`, `RoleMiddleware.php`)
- **Database:** MySQL (SQLite supported for local dev)
- **Cache & Queue:** Redis (`predis/predis`)
- **Realtime:** Laravel Reverb (WebSocket broadcasting)
- **AI providers:** Groq (default) or Anthropic Claude — used for programme-activity suggestions, URL/document parsing, and advisory note generation
- **File storage:** local disk, with optional AWS S3 and ImageKit (organisation logos)
- **PDF/Docs:** `barryvdh/laravel-dompdf`, `phpoffice/phpword`, `smalot/pdfparser`
- **API docs:** `darkaonline/l5-swagger` (OpenAPI/Swagger UI)
- **App server:** Laravel Octane

## Requirements

- PHP 8.2+
- Composer
- MySQL 8+ (or SQLite for local dev)
- Redis
- Node.js (only needed for the `composer serve` convenience script, via `npx concurrently`)

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env` (see [Environment configuration](#environment-configuration) below), then:

```bash
php artisan migrate
php artisan db:seed
```

Run the app. Either start each process yourself:

```bash
php artisan serve
php artisan queue:listen --tries=1 --sleep=1
php artisan reverb:start
```

or use the bundled script, which runs all three concurrently:

```bash
composer serve
```

The API is served at `http://localhost:8000`. Sanctum expects a frontend running on one of `SANCTUM_STATEFUL_DOMAINS` (defaults to `localhost:5173` / `127.0.0.1:5173`).

## Environment configuration

Key `.env` sections (see `.env.example` for the full list and inline comments):

- **Database** — `DB_CONNECTION=mysql` by default; switch to `sqlite` for zero-setup local dev.
- **Cache/Queue** — Redis-backed (`CACHE_STORE`, `QUEUE_CONNECTION`).
- **Broadcasting** — Reverb credentials (`REVERB_APP_ID/KEY/SECRET`); the `VITE_REVERB_*` mirrors exist so the frontend's Echo client can read the same values.
- **Mail** — SMTP (Gmail by default). `MAIL_FROM_ADDRESS` must share a domain with `MAIL_USERNAME`, or mail sent from it is likely to be flagged as spam by non-Gmail providers.
- **AI provider** — required for advisory-note generation and programme-activity AI features. Only one provider should be active:
  - **Groq** (default): set `GROQ_API_KEY` (`config/services.php` → `groq`).
  - **Claude**: set `CLAUDE_API_KEY` (`config/services.php` → `claude`). Controllers currently resolve `App\Services\AI\GroqService` directly via `App::make()`; switching the active provider means updating those call sites (or introducing an interface binding in `AppServiceProvider`) in addition to the env vars.
- **ImageKit** — required for organisation logo uploads (`IMAGEKIT_PUBLIC_KEY`, `IMAGEKIT_PRIVATE_KEY`, `IMAGEKIT_URL_ENDPOINT`).
- **AWS S3** — optional alternative file storage.
- **Sanctum** — `SANCTUM_STATEFUL_DOMAINS` must list every frontend origin using cookie-based auth.

## Project structure

```
app/
  Console/Commands/      Scheduled/maintenance commands (e.g. flagging stale programme entries)
  Http/Controllers/Api/  REST API controllers, including Admin/ (user, role, org management)
  Http/Middleware/       Permission and role gating
  Http/Requests/         Form request validation
  Http/Resources/        API resource transformers
  Models/                Eloquent models (programmes, geography, taxonomy, users, advisory notes, ...)
  Services/AI/           Groq/Claude client wrappers + prompt building
  Services/Adviser/      Advisory workflow logic (profile extraction, map overlap matching)
  Services/              AuditLogger, ImageKitService, description extraction
  Notifications/         Email/in-app notifications
database/
  migrations/, factories/, seeders/
routes/
  api.php     All authenticated + public API routes (Sanctum-protected group)
  web.php, channels.php, console.php
tests/
  Feature/    Route- and workflow-level tests (RBAC, programme lifecycle, adviser flow, etc.)
  Unit/
```

## Core domains

- **Programme entries** — the central record an organisation submits: activities, geography (province/district/commune/village), keywords, government agreements, budget bands. Supports draft/submit/verify lifecycle and PDF export.
- **Advisory workflow** — coordinators/advisers review submissions, extract profiles from uploaded documents (AI-assisted), and generate/deliver advisory notes.
- **Taxonomy** — hierarchical category/subcategory/item classification for programme activities, with a review queue for "other" (unclassified) entries.
- **Mapping & reporting** — GeoJSON and tabular map endpoints, province/category counts, dashboard stats, CSV/PDF export.
- **Admin** — organisations, users, roles, and fine-grained permissions (`permission:<name>` route middleware), plus audit logging.
- **Policy library** — uploaded policy documents with role-gated CRUD.

## Authorization model

Access is permission-based, not just role-based: routes are gated with `permission:<name>` (see `routes/api.php`), and permissions are assigned to roles (seeded in `database/seeders/RolePermissionSeeder.php`) or granted individually. This lets an admin create custom roles without code changes. Several routes accept an "either" permission list (e.g. `programmes.view,programmes.view-own`), where the controller further scopes results based on which permission was actually granted — see `App\Http\Controllers\Api\Concerns\ScopesProgrammeEntryAccess`.

## API documentation

Swagger/OpenAPI docs are generated from controller annotations:

```bash
php artisan l5-swagger:generate
```

View them at `/api/documentation` once the app is running.

## Testing

```bash
php artisan test
```

Feature tests cover RBAC/permission gating, the programme-entry lifecycle, adviser submissions, taxonomy, policy documents, and more (see `tests/Feature/`).

## Code style

```bash
./vendor/bin/pint
```

## Useful commands

```bash
php artisan programme-entries:flag-stale   # Console\Commands\FlagStaleProgrammeEntries
php artisan queue:listen --tries=1 --sleep=1
php artisan reverb:start
```
