# WorkTrack Pro

Work tracking for teams: daily plans with timers, activity logs, attendance (clock-in/out), recurring tasks, an internal inbox, HR letters, and a knowledge base of internal systems with manuals.

| Part | Technology | URL |
|---|---|---|
| Worker portal | Vue 3 + Pinia SPA, Sanctum API tokens | `/` |
| Admin panel | Filament 5 | `/admin` |
| API | Laravel 13, JSON, versioned | `/api/v1` |

## Requirements

- PHP 8.3+ with `gd`, `zip`, `dom`, `xml`, `pdo_mysql` (Laravel Herd works)
- Composer 2
- MySQL 8 (tests use in-memory SQLite)
- Node.js 20+
- [qpdf](https://qpdf.sourceforge.io), to merge uploaded PDF sections into generated manuals. Without it, those sections appear as a page pointing to WorkTrack Pro. On Windows: `winget install --id QPDF.QPDF -e`, then set `QPDF_PATH` in `.env` (e.g. `C:/Program Files/qpdf 12.4.2/bin/qpdf.exe`).

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# set DB_DATABASE / DB_USERNAME / DB_PASSWORD in .env, then:
php artisan migrate --seed
php artisan storage:link

npm install
npm run build
```

Run everything for development (web server, queue worker, logs, Vite):

```bash
composer dev
```

### Demo accounts

`php artisan migrate --seed` creates a demo organisation. Every demo password is `password`.

| Role | Email |
|---|---|
| Admin (Engineering) | `admin.eng@worktrackpro.test` |
| Admin (Design) | `admin.design@worktrackpro.test` |
| Workers | `worker1@worktrackpro.test` … `worker5@worktrackpro.test` |

A super admin is created too; see `database/seeders/DatabaseSeeder.php`. Change all of these before any real use.

### First things to set in the admin panel

1. **Organisations → your organisation → Timezone.** "Today" for clock-in, plans, recurring tasks and the session close time follows this. New organisations default to UTC.
2. **Configuration → Work Types.** Name your own types and choose which category each counts as (Direct, Indirect or Growth).
3. **Knowledge → Systems.** Add your internal systems and their owners.

## Background jobs

Several features rely on Laravel's scheduler and the queue.

| Command | When | What it does |
|---|---|---|
| `worktrack:sessions:close-abandoned` | every 15 min | Closes sessions left open after each organisation's close time |
| `worktrack:timers:stop-abandoned` | every 30 min | Stops timers running past the threshold (logged as unverified) |
| `worktrack:plans:generate-recurring` | hourly | Creates today's plans from recurring tasks, per organisation's date |
| `worktrack:knowledge:stale-sections` | Mondays 08:00 | Reminds owners about manual sections not verified for 90 days |
| `worktrack:knowledge:prune-exports` | Sundays 03:00 | Deletes generated manual PDFs older than 30 days (they're regenerated on request) |

On a server, run the scheduler every minute:

```cron
* * * * * cd /path/to/worktrackpro && php artisan schedule:run >> /dev/null 2>&1
```

On Windows, create a Task Scheduler task that runs `php artisan schedule:run` in the project folder every minute, or run `php artisan schedule:work` in a terminal while developing.

Run a queue worker as well (`composer dev` already starts one). Large manual PDFs (over 25 sections) are generated in the queue:

```bash
php artisan queue:work
```

## Configuration

Besides the standard Laravel settings, `.env` supports:

| Variable | Default | Meaning |
|---|---|---|
| `SANCTUM_EXPIRATION` | `10080` | Portal login lifetime in minutes (7 days) |
| `QPDF_PATH` | `qpdf` | Path to the qpdf binary |
| `KNOWLEDGE_STALE_AFTER_DAYS` | `90` | When manual sections count as needing a re-check |
| `KNOWLEDGE_MAX_IMAGE_KB` / `_PDF_KB` / `_DOCX_KB` | 5 MB / 50 MB / 20 MB | Upload limits for the knowledge base |

## Times and dates

All timestamps are stored in UTC. Calendar dates (a plan's date, a session's date, "today") use the **organisation's timezone** (`App\Support\LocalTime`, `$user->localToday()`). Use those helpers rather than `now()->toDateString()` or `today()` for anything date-based.

## Permissions

Permission names live in `App\Support\Permissions`; always use the constants. Roles:

- **worker**: portal only
- **admin**: manages their department
- **super_admin**: everything

`super_admin`, `admin` and `worker` are shared by all organisations, so only a super admin can change them. Admins can create custom roles for their own organisation, but can't grant permissions they don't hold. After changing the permission list, run:

```bash
php artisan db:seed --class=RoleAndPermissionSeeder
```

## Tests

```bash
php artisan test
```

Feature tests cover timers, clock-out and carry-over, authorization, timezones, work types, letters, the knowledge base API and the knowledge admin pages.

## Project notes

- `implementation_plan_knowledge_base.md`: Knowledge & Readiness module plan and status (Phases 0–3 done)
- `implementation_plan_phase6.md`: earlier feature addendum plan (attendance, timers, recurring tasks, inbox, letters)
