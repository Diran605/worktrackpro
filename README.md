# WorkTrack Pro

Work tracking for teams: daily plans with timers, activity logs, attendance (clock-in/out), recurring tasks, an internal inbox, HR letters, a knowledge base of internal systems with manuals, and a public documentation library for those manuals.

| Part | Technology | URL |
|---|---|---|
| Documentation library | Server-rendered Blade + Tailwind | `/` (default organisation), `/org/{slug}`, or an organisation's own domain |
| Staff portal | Vue 3 + Pinia SPA, Sanctum API tokens | `/staff` |
| Admin panel | Filament 5 | `/admin` |
| API | Laravel 13, JSON, versioned | `/api/v1` |

The staff portal used to live at `/`; old addresses such as `/login` and `/plans` redirect to `/staff/...`.

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
4. **Documentation Library.** Set "In the library" on each system (Public, Restricted or Not in library), create categories, and fill in the library title and announcements under **Configuration → Organisation Settings**.

## Modules and company mode

WorkTrack Pro is sold by module. A super admin chooses each organisation's modules (**Organisations → Modules**, or **Configuration → Platform Settings** for the main organisation):

| Module | Contains |
|---|---|
| Core (always on) | Users, departments, roles, organisation settings, inbox, audit log |
| Work Tracking | Daily plans, timers, activity logs, recurring tasks, task templates, work types, projects & clients, team view, Team Pulse |
| Attendance | Clock-in/out, sessions, reopen requests, Attendance Overview |
| HR Letters | Letter templates, letterheads, issued letters |
| Knowledge Base | Systems, manuals, approvals, structure, staff PDF manuals |
| Documentation Library | The public library (needs Knowledge Base) |

A switched-off module is invisible to that organisation (menus, pages, API, scheduled jobs, library); its data is kept. Super admins see every module.

With both Attendance and Work Tracking, **Organisation Settings → Link attendance to work tracking** decides whether people must clock in before plans and timers (and whether clocking out stops timers and asks about unfinished tasks). Off: attendance is only a record of working hours.

**Company mode** (Platform Settings): *single company* hides everything about choosing organisations (Organisations menu, organisation columns, `/org/{slug}` library addresses) and makes other organisations inactive; *multiple companies* hosts several separate businesses. The data model is the same in both, so switching is just a setting. From the command line:

```bash
php artisan worktrack:platform:mode single --main=your-org-slug
php artisan worktrack:platform:mode multi
```

## Documentation library

Each organisation has a public library of its manuals, laid out like a vendor documentation site: products (systems) by category, a product page with its guides grouped by audience, a reader with a contents tree, search, and "What's changed" per version. (PDF download is only offered in the staff portal.)

- **Addresses.** The main organisation (Platform Settings) is served at `/`. In multiple companies mode every organisation is also at `/org/{slug}`. A super admin can give an organisation its own domain (**Organisations → Documentation library address**, e.g. `docs.example.com`); point the domain's DNS at the server and make sure the web server answers for it.
- **Who can read what.** *Public* manuals are open to everyone; *Restricted* manuals are listed but need a staff sign-in or an access code; *Not in library* manuals stay in the staff portal. Individual chapters can be restricted under **Knowledge → Manual Structure**.
- **Audiences** (**Documentation Library → Audiences**) are groups of readers, e.g. *Teachers* or *Finance*. Mark chapters for one or more audiences under **Knowledge → Manual Structure → Who is it for…**; the chapter and everything under it can then only be read by staff and by access codes that open one of those audiences. Product pages list chapters as guide cards under "General" and each audience.
- **Access codes** (**Documentation Library → Access Codes**) are for clients and other readers outside the staff. Each code opens chosen manuals, optionally including restricted chapters, and can expire or be turned off at any time. Readers can also ask for access from a locked page; requests arrive in the owners' inbox and under **Access Requests**.
- **Versions.** Approved changes reach staff in the portal straight away. The library shows each manual **as of its last published version**: owners release changes with **Knowledge → Systems → Publish version** (minor or major, with a summary readers see), or tick **Publish to the library now** when approving an urgent fix. Archiving, moving and reordering sections apply to the library immediately.
- **Staff** signed in to the portal can use **Open in library** to view a manual there with full access.

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
| `DOCS_DEFAULT_ORGANISATION` | first active organisation | Fallback for the library at `/` if no main organisation is set in Platform Settings |
| `DOCS_CODE_SESSION_DAYS` | `30` | How long an access code stays active in a reader's browser |
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

Feature tests cover timers, clock-out and carry-over, authorization, timezones, work types, letters, the knowledge base API, the knowledge admin pages and the documentation library (addresses, access rules, codes, versions, search, feedback and its admin pages).

## Project notes

- `implementation_plan_knowledge_base.md`: Knowledge & Readiness module plan and status (Phases 0–3, 3B and 3C done)
- `implementation_plan_phase6.md`: earlier feature addendum plan (attendance, timers, recurring tasks, inbox, letters)
