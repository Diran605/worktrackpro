# WorkTrack Pro — Feature Addendum v3.0 Implementation Plan

This plan covers the implementation of all 8 major features from the Feature Addendum v3.0 document. The work is organized into 6 implementation phases, ordered by dependency — each phase builds on the one before it.

## Existing Codebase Summary

| Layer | What exists today |
|-------|------------------|
| **Backend** | Laravel 13 + Filament v3 admin panel, Sanctum API auth |
| **Frontend** | Vue 3 + Pinia SPA (worker portal) at `/` |
| **Models** | User, Organisation, Department, DailyPlan, ActivityLog, WorkType, ProjectClient, AuditLog, WeeklyStatsCache |
| **Enums** | PlanStatus (`pending`, `done`, `carried_over`, `cancelled`), Priority, WorkType, CompletionType |
| **API** | RESTful v1 — auth, plans CRUD, logs CRUD, dashboard stats, team, lookups, notifications |
| **Admin Resources** | Organisations, Departments, Users, DailyPlans, Roles, WorkTypes, ProjectClients, AuditLogs |
| **Reports** | TeamPulseReport Filament page + PDF export via DomPDF |

---

## Phase 1: Clock-In / Clock-Out (Attendance) — Foundation

> [!IMPORTANT]
> This is the **most critical phase** — nearly every subsequent feature depends on `work_sessions`. It must be built and tested first.

### Database

#### [NEW] Migration: `create_work_sessions_table`
- Columns: `id`, `user_id` (FK), `organisation_id` (FK), `date`, `clock_in` (timestamp), `clock_out` (timestamp nullable), `total_minutes` (int nullable), `status` (enum: `active`, `closed`, `system_closed`), `clock_in_ip` (varchar 45), `clock_out_ip` (varchar 45 nullable), `user_agent` (text), `notes` (text nullable), `created_at`, `updated_at`
- Unique constraint: `(user_id, date)`

#### [NEW] Migration: `create_session_reopen_requests_table`
- Columns: `id`, `work_session_id` (FK), `requested_by` (FK → users), `reason` (text), `status` (enum: `pending`, `approved`, `declined`), `reviewed_by` (FK → users nullable), `review_note` (text nullable), `reviewed_at` (timestamp nullable), `created_at`, `updated_at`

### Backend

#### [NEW] `app/Models/WorkSession.php`
- Relationships: `belongsTo(User)`, `belongsTo(Organisation)`, `hasMany(DailyPlan)`, `hasMany(ActivityLog)`, `hasMany(SessionReopenRequest)`
- Scopes: `scopeForDate()`, `scopeActive()`, `scopeSystemClosed()`

#### [NEW] `app/Models/SessionReopenRequest.php`
- Relationships: `belongsTo(WorkSession)`, `belongsTo(User, 'requested_by')`, `belongsTo(User, 'reviewed_by')`

#### [NEW] `app/Enums/SessionStatus.php`
- Cases: `Active`, `Closed`, `SystemClosed`

#### [NEW] `app/Http/Controllers/Api/V1/WorkSessionController.php`
- `clockIn()` — Creates session, captures IP + user agent, returns session ID
- `clockOut()` — Validates no running timers, calculates total_minutes, seals session
- `currentSession()` — Returns today's session (or null if not clocked in)
- `requestReopen($sessionId)` — Worker submits reopen request with reason

#### [NEW] `app/Console/Commands/CloseAbandonedSessions.php`
- Scheduled job: find all `active` sessions with no clock-out, auto-close as `system_closed`, stop running timers, flag incomplete plans

#### [NEW] Filament Admin Resource: `app/Filament/Resources/Attendance/`
- `AttendanceResource.php` — Table of all workers with clock-in/out times, total hours, status badges (green/amber/red)
- Scoped: Admin → own department, Super Admin → all
- Actions: Reopen Session, View Session Detail
- Filters: date range, status, department
- Reopen requests priority queue at top

### Frontend (Vue Worker Portal)

#### [MODIFY] `resources/js/views/DashboardView.vue`
- Add clock-in/clock-out banner at top
- Lock daily plans and timers until clocked in
- Show "session auto-closed" notice with Request Reopen button if applicable

#### [NEW] `resources/js/stores/session.js`
- Pinia store: `currentSession`, `isLockedOut`, `clockIn()`, `clockOut()`, `requestReopen()`

#### [MODIFY] `resources/js/router.js`
- Add session check middleware — redirect to dashboard with lock notice if not clocked in

#### API Routes additions in `routes/api.php`:
```
POST   /v1/sessions/clock-in
POST   /v1/sessions/clock-out
GET    /v1/sessions/current
POST   /v1/sessions/{id}/request-reopen
```

---

## Phase 2: Activity Timer on Daily Plans

### Database

#### [NEW] Migration: `add_timer_columns_to_daily_plans`
- Add: `timer_status` (enum: `idle`, `running`, `paused`, `stopped`; default `idle`), `timer_started_at` (timestamp nullable), `timer_accumulated_minutes` (int default 0), `work_session_id` (FK → work_sessions nullable), `is_assigned` (boolean default false), `task_template_id` (FK nullable), `personal_recurring_task_id` (FK nullable), `carried_from_plan_id` (FK self-ref nullable), `carry_over_count` (int default 0)

#### [NEW] Migration: `add_session_columns_to_activity_logs`
- Add: `work_session_id` (FK → work_sessions nullable), `stop_reason` (enum: `manual`, `clock_out`, `system_timeout`; default `manual`), `is_verified` (boolean default true)

#### [NEW] Migration: `create_timer_pauses_table`
- Columns: `id`, `daily_plan_id` (FK), `paused_at` (timestamp), `resumed_at` (timestamp nullable), `duration_minutes` (int nullable), `created_at`

### Backend

#### [NEW] `app/Models/TimerPause.php`
- Relationship: `belongsTo(DailyPlan)`

#### [NEW] `app/Enums/TimerStatus.php`
- Cases: `Idle`, `Running`, `Paused`, `Stopped`

#### [NEW] `app/Enums/StopReason.php`
- Cases: `Manual`, `ClockOut`, `SystemTimeout`

#### [MODIFY] `app/Models/DailyPlan.php`
- Add fillable fields, casts for new enums, relationships: `belongsTo(WorkSession)`, `hasMany(TimerPause)`, `belongsTo(DailyPlan, 'carried_from_plan_id')`

#### [MODIFY] `app/Models/ActivityLog.php`
- Add fillable fields, casts for `stop_reason`, `is_verified`

#### [NEW] `app/Http/Controllers/Api/V1/TimerController.php`
- `start($planId)` — Sets `timer_status = running`, records `timer_started_at`. Only one timer can run at a time per user.
- `pause($planId)` — Creates `TimerPause` record, accumulates elapsed time
- `resume($planId)` — Updates `TimerPause.resumed_at`, resumes counting
- `stop($planId)` — Calculates final duration, creates ActivityLog entry automatically, sets `timer_status = stopped`

#### [NEW] `app/Console/Commands/StopAbandonedTimers.php`
- Runs every 30 mins. Finds timers running > threshold (default 3hrs). Auto-stops, creates unverified activity log, notifies worker + admin.

### Frontend

#### [MODIFY] `resources/js/views/plans/DailyPlansView.vue`
- Add Start/Pause/Resume/Stop timer buttons per plan card
- Live elapsed time counter display
- Only one running timer at a time — disable Start on other plans while one is running

#### [NEW] `resources/js/stores/timer.js`
- Pinia store: `activeTimer`, `elapsedSeconds`, `startTimer()`, `pauseTimer()`, `resumeTimer()`, `stopTimer()`
- Uses `setInterval` for live counter, syncs with API

#### API Routes:
```
POST   /v1/timers/{plan}/start
POST   /v1/timers/{plan}/pause
POST   /v1/timers/{plan}/resume
POST   /v1/timers/{plan}/stop
```

---

## Phase 3: Recurring Tasks (Admin-Assigned + Worker Personal)

### Database

#### [NEW] Migration: `create_task_templates_table`
- Columns: `id`, `organisation_id` (FK), `title`, `work_type` (enum), `expected_duration_minutes`, `recurrence_type` (enum: `daily`, `weekly`, `one_time`), `recurrence_day` (tinyint nullable), `department_id` (FK nullable), `created_by` (FK), `is_active` (boolean), `created_at`, `updated_at`

#### [NEW] Migration: `create_task_template_assignments_table`
- Columns: `id`, `task_template_id` (FK), `user_id` (FK), `created_at`

#### [NEW] Migration: `create_personal_recurring_tasks_table`
- Columns: `id`, `user_id` (FK), `title`, `work_type` (enum), `priority` (enum), `expected_duration_minutes`, `recurrence_type` (enum: `daily`, `weekly`), `recurrence_day` (tinyint nullable), `is_active` (boolean), `created_at`, `updated_at`

### Backend

#### [NEW] Models: `TaskTemplate`, `TaskTemplateAssignment`, `PersonalRecurringTask`

#### [NEW] `app/Console/Commands/GenerateRecurringPlans.php`
- Runs at midnight. Checks all active `task_templates` and `personal_recurring_tasks`. Generates `daily_plans` entries for assigned workers on matching days. Sets `is_assigned = true` for admin templates, `personal_recurring_task_id` for personal ones.

#### [NEW] Filament Admin Resource: `app/Filament/Resources/TaskTemplates/`
- CRUD for task templates with worker/department assignment UI
- Compliance tracking view (who completed, who didn't)

#### [NEW] `app/Http/Controllers/Api/V1/PersonalRecurringTaskController.php`
- Full CRUD for worker's own recurring tasks

#### API Routes:
```
GET/POST/PUT/DELETE   /v1/recurring-tasks
```

---

## Phase 4: Daily Plan Carry-Over

### Backend

#### [NEW] `app/Http/Controllers/Api/V1/CarryOverController.php`
- `getPendingCarryOvers()` — Returns all incomplete plans from closed/system_closed sessions that haven't been resolved
- `resolveCarryOver($planId, $decision)` — Handles "carry_over", "cancel", or "leave" decisions
  - **carry_over**: Creates new plan for today with `carried_from_plan_id` set, incremented `carry_over_count`, optional new priority. Marks original as `carried_over`.
  - **cancel**: Marks plan as `cancelled`.
  - **leave**: Keeps plan as `pending` (no action).

#### [MODIFY] `app/Http/Controllers/Api/V1/WorkSessionController.php`
- `clockOut()` must check for incomplete plans and return them to frontend for carry-over decision before completing

### Frontend

#### [NEW] `resources/js/components/CarryOverModal.vue`
- Full-screen modal appearing at clock-out or login (for auto-closed sessions)
- Shows each incomplete plan individually with task name, completion status, priority (editable)
- Three buttons per task: Carry Over, Cancel, Leave for Now
- Clock-out doesn't complete until all plans are resolved

#### [MODIFY] `resources/js/views/DashboardView.vue`
- Show "Pending from previous days" section for plans left as "leave for now"
- Show carry-over badge with count on carried tasks

#### API Routes:
```
GET    /v1/carry-overs/pending
POST   /v1/carry-overs/{plan}/resolve
```

---

## Phase 5: Inbox — Internal Communication Module

### Database

#### [NEW] Migration: `create_messages_table`
- Columns: `id`, `sender_id` (FK nullable — null for system), `subject`, `body` (text), `message_type` (enum: `direct`, `broadcast`, `system`, `letter`, `reopen_request`, `reopen_response`), `organisation_id` (FK), `created_at`, `updated_at`

#### [NEW] Migration: `create_message_recipients_table`
- Columns: `id`, `message_id` (FK), `recipient_id` (FK), `read_at` (timestamp nullable), `created_at`

#### [NEW] Migration: `create_message_attachments_table`
- Columns: `id`, `message_id` (FK), `file_path`, `file_name`, `file_type` (varchar 100), `file_size` (int unsigned), `created_at`

### Backend

#### [NEW] Models: `Message`, `MessageRecipient`, `MessageAttachment`

#### [NEW] `app/Http/Controllers/Api/V1/InboxController.php`
- `index()` — Paginated inbox for current user, with unread count
- `show($id)` — View message, mark as read
- `send()` — Send direct message with optional attachments
- `unreadCount()` — Returns badge count
- Broadcast sending restricted to admin/super_admin roles

#### [NEW] Filament Admin Resource: `app/Filament/Resources/Inbox/`
- Admin inbox page with compose, reply, broadcast to department
- Unread badge in sidebar navigation
- Reopen request messages show inline Approve/Decline buttons

#### [NEW] `app/Services/InboxService.php`
- Centralised helper: `sendSystemMessage()`, `sendReopenRequest()`, `sendReopenResponse()`, `sendLetterMessage()`
- Used by all other modules (clock-out, timer auto-stop, carry-over, letters) to send notifications

### Frontend

#### [NEW] `resources/js/views/inbox/InboxView.vue`
- Message list with unread indicators
- Message detail view with attachments
- Compose message to admin

#### [NEW] `resources/js/stores/inbox.js`
- Pinia store: `messages`, `unreadCount`, `fetchMessages()`, `sendMessage()`, `markAsRead()`

#### [MODIFY] `resources/js/App.vue`
- Add inbox icon with unread badge in navigation bar

#### [MODIFY] `resources/js/router.js`
- Add `/inbox` route

#### API Routes:
```
GET    /v1/inbox
GET    /v1/inbox/{id}
POST   /v1/inbox/send
GET    /v1/inbox/unread-count
```

---

## Phase 6: Letters Module (Letterhead + Templates + Generation)

### Database

#### [NEW] Migration: `create_company_letterheads_table`
- Columns: `id`, `organisation_id` (FK), `company_name`, `header_image_path`, `footer_image_path`, `header_height_px` (int), `footer_height_px` (int), `accent_color` (varchar 7), `is_active` (boolean), `created_at`, `updated_at`

#### [NEW] Migration: `create_letter_templates_table`
- Columns: `id`, `organisation_id` (FK nullable — null = system default), `letter_type` (enum: `appointment`, `warning`, `query`, `confirmation`, `custom`), `name`, `subject_template`, `body_template` (longtext), `is_system_default` (boolean), `created_by` (FK), `last_edited_by` (FK nullable), `created_at`, `updated_at`

#### [NEW] Migration: `create_generated_letters_table`
- Columns: `id`, `organisation_id` (FK), `worker_id` (FK), `generated_by` (FK), `letter_template_id` (FK), `letter_type` (enum), `subject`, `body_snapshot` (longtext), `pdf_path`, `custom_fields` (json), `generated_at`, `acknowledged_at` (nullable), `acknowledged_by` (FK nullable), `created_at`, `updated_at`

### Backend

#### [NEW] Models: `CompanyLetterhead`, `LetterTemplate`, `GeneratedLetter`

#### [NEW] Filament Admin Pages — Letters Navigation Group:
1. **Letterhead Settings** (`app/Filament/Pages/LetterheadSettings.php`)
   - Upload header/footer images, auto-detect dimensions via `getimagesize()`
   - Live preview of sample letter
   - Accent color picker
2. **Letter Templates** (`app/Filament/Resources/LetterTemplates/`)
   - CRUD with rich text editor + placeholder sidebar
   - System defaults seeded; per-org customization
3. **Generate Letter** (`app/Filament/Pages/GenerateLetter.php`)
   - Worker selector, template selector, custom field inputs
   - Live preview with actual header/footer images
   - Generate button → renders Blade view via Browsershot → stores PDF → sends to worker inbox
4. **Issued Letters** (`app/Filament/Resources/IssuedLetters/`)
   - History table, filterable by worker/type/date
   - Acknowledgement status column
   - Re-download / resend actions

#### [NEW] `app/Services/LetterService.php`
- `generatePdf($template, $worker, $customFields)` — Renders Blade view with letterhead, replaces placeholders, converts via Browsershot
- `deliverToInbox($letter)` — Creates letter-type message with PDF attachment

#### [NEW] Blade template: `resources/views/exports/letter.blade.php`
- Full letter layout with header image, body, footer image, accent color styling

### Frontend

#### [MODIFY] Worker inbox — Letter messages show "Acknowledge Receipt" button for warning/query types

#### API Routes:
```
POST   /v1/inbox/{id}/acknowledge
```

---

## Scheduler Registration

All new commands must be registered in `app/Console/Kernel.php` (or `routes/console.php`):

| Command | Schedule | Purpose |
|---------|----------|---------|
| `CloseAbandonedSessions` | Daily at configurable time (default 20:00) | Auto-close forgotten active sessions |
| `StopAbandonedTimers` | Every 30 minutes | Stop timers running > threshold |
| `GenerateRecurringPlans` | Daily at 00:00 | Create daily plan entries from templates + personal recurrences |

---

## Permission Seeds

New permissions to seed:

| Permission | Who gets it |
|------------|------------|
| `manage_attendance` | admin, super_admin |
| `reopen_sessions` | admin, super_admin |
| `manage_task_templates` | admin, super_admin |
| `manage_letters` | admin, super_admin |
| `manage_letterheads` | super_admin |
| `view_inbox` | worker, admin, super_admin |
| `send_broadcast` | admin, super_admin |

---

## User Review Required

> [!IMPORTANT]
> **Browsershot dependency**: Phase 6 (Letter Generation) requires [Browsershot](https://github.com/spatie/browsershot) + a Chrome/Chromium binary on the server for high-fidelity PDF rendering. The current system uses DomPDF. Do you want to:
> - **(A)** Install Browsershot for the Letters module (best quality, needs Chrome on server)
> - **(B)** Use DomPDF for everything (simpler, already installed, slightly lower fidelity)

> [!IMPORTANT]
> **Implementation order**: The 6 phases are ordered by dependency. Do you want to implement them all sequentially, or would you like to prioritize specific phases?

> [!WARNING]
> **Breaking migration**: Phase 2 adds 9 new columns to `daily_plans` and 3 to `activity_logs`. This will require a `migrate:fresh --seed` or careful additive migrations. Since you're in development, I recommend `migrate:fresh --seed` after each phase.

## Verification Plan

### Automated Tests
- After each phase: `php artisan migrate:fresh --seed` to verify migrations
- Manual API testing via browser dev tools or Postman for each new endpoint
- Admin panel verification via browser for each new Filament resource

### Manual Verification
- Clock in/out flow tested end-to-end in the Vue worker portal
- Timer start/pause/resume/stop with live counter verified visually
- Carry-over modal tested at clock-out
- Inbox messages verified for delivery and read tracking
- Letter PDF generation verified with letterhead images
- Scheduled commands tested via `php artisan schedule:test`
