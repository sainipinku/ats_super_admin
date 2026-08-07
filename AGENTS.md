# AGENTS.md

Laravel 12 + Inertia 2 + React 18 + Tailwind v3 construction-ERP admin app ("CadMax Consult"). Multiple distinct portals (superadmin, admin, member, calling-team) run inside one app; construction ERP is the active focus area.

## Commands

- Dev (server + queue worker + Vite, via concurrently): `composer dev`
- Backend tests: `composer test` (runs `config:clear` then `php artisan test`; PHPUnit, sqlite `:memory:` per `phpunit.xml`)
- Single test: `php artisan test tests/Feature/Construction/ConstructionExecutionServiceTest.php`
- Frontend: `npm run dev` / `npm run build` (Vite, entry `resources/js/app.jsx`)
- New construction schema: `php artisan migrate` then `php artisan db:seed --class=Database\\Seeders\\Construction\\FoundationSeeder`

CI (`ci.yml`) runs `composer test` + `npm run build` on PHP 8.3 / Node 20, and PRs to `main` are **auto-merged** by `pr-check.yml` when green (a `CHANGES_REQUESTED` review blocks it). Work happens on personal branches; keep PRs mergeable.

There is no frontend lint/typecheck configured. `jest.config.js` exists but no JS tests exist — don't try to run jest. `vendor/bin/pint` is available but not enforced by CI.

## Architecture

- **Portals = independent auth guards** (config in `config/auth.php`): `superadmin` (prefix `/super/*`), `admin` (`/admin/*`), `member`, `callingteam`. Middleware aliases registered in `bootstrap/app.php`: `auth.superadmin`, `admin`, `member`, `callingteam`, `authorized` (redirect-if-authenticated), `construction.permission`.
- **Inertia pages** live in `resources/js/Pages/` mirroring the portal (e.g. `Pages/Construction/...`); shared props come from `app/Http/Middleware/HandleInertiaRequests.php`, which resolves the active guard and merges permissions into `auth.permissions` / `auth.construction_permissions`. Use `route()` (Ziggy) for URLs.
- **Route names** are namespaced (`super.construction.dashboard`, `admin.construction.*`). All web routes are in `routes/web.php`; member/mobile APIs in `routes/api.php` (Sanctum).
- **Construction domain**: tables/models in `app/Models/Construction/*`, services in `app/Services/Construction/*`, controllers split across `app/Http/Controllers/{SuperAdmin,Admin,Api\Mobile}/Construction/*`. Global model helpers in `app/Helpers.php` (autoloaded, e.g. `shortUuid()`).
- **Construction permissions** are enforced with `construction.permission:slug1,slug2` middleware → `EnsureConstructionPermission` → `ConstructionAuthorizationService` (project-scoped). The authoritative permission slug map and role seeding is `database/seeders/Construction/FoundationSeeder.php` — update it when adding endpoints.

## Gotchas

- **CSRF validation is globally disabled** in `bootstrap/app.php` (`validateCsrfTokens(except: ['*'])`). POST routes work without tokens, but don't "fix" this without knowing why.
- **Scheduled task generation is defined in `routes/console.php`** (daily/weekly/monthly/... task-assign commands, Asia/Kolkata). `app/Console/Kernel.php` still has a legacy duplicate `schedule()` including a leftover `app:demo-cron-test` every-minute entry — treat it as stale.
- **Queue connection is `database`** and `composer dev` runs `queue:listen`; email jobs (`app/Jobs/*`) won't dispatch without the worker or `QUEUE_CONNECTION=sync`.
- **Deployed with docroot at repo root, not `public/`**: root `index.php` + `.htaccess` rewrite into `public/`. Don't delete them. `public/hot` will be present if Vite ran.
- **Legacy root scripts** `check_users.php`, `create_admin.php`, `fix_passwords.php` (plus `app/Console/Commands/FixPasswords.php`) are manual user/password maintenance tools — inspect before trusting, don't replicate.
- **`.git_disabled/` is a backup copy of the git metadata — never touch it.**
- Dev DB is MySQL (`.env.example` uses `DB_DATABASE=laravelpermissionpro`); tests force sqlite in-memory.
- Dev workflow docs for the construction modules live in `docs/construction-erp/` (roadmap + research). Read them before building new construction features.
