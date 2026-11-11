# WorkTrack Pro — Knowledge & Readiness Module Implementation Plan

Turns company systems/projects from "knowledge in people's heads" into structured manuals, measures whether staff can actually use each system, and learns from support tickets.

## Implementation Status

| Phase | Status |
|---|---|
| 0 — Prerequisite fixes | ✅ Done (2026-11-11) |
| 1 — System registry | ✅ Done (2026-11-11) |
| 2 — Manuals | ✅ Done (2026-11-11) |
| 3 — Manual generation (PDF) | Not started |
| 4 — Competency & readiness | Not started |
| 5 — Tickets & FAQ | Not started |
| 6 — Insights | Not started |

Where the build differs from the plan below:
- **Owners' own changes publish immediately.** Someone who can approve a system's manual doesn't wait for themselves.
- **Moving sections is owner-only, in Filament** (Manual Structure: move up/down, move under, archive/restore, mark as still accurate). Contributors choose a position only when proposing a *new* section. Renaming is done by editing the section.
- **Import review:** owners can leave sections out when approving a Word import; renaming/reordering happens afterwards in Manual Structure.
- **Edit "diff":** the review page shows the published version and the proposed version one after the other, plus a warning if the section changed after the edit was started (not a line-by-line diff).
- **`qpdf` is optional in Phase 2:** without it, PDF page ranges are accepted but not checked against the page count.
- Tests: `tests/Feature/{TimerTest,ClockOutCarryOverTest,AuthorizationTest,KnowledgeBaseTest,KnowledgeAdminPagesTest}.php`.

## Locked Decisions

| Topic | Decision |
|---|---|
| Tickets | Built inside WorkTrack Pro. Raised and followed in the Vue portal; triaged, assigned and resolved in Filament. |
| Video evidence | External links only (YouTube, Google Drive, Loom, OneDrive). No video uploads. Screenshots are uploaded as images. |
| Who writes docs | Anyone with `contribute_kb`. Every change goes to the system owner (or backup owner) for approval. |
| Where things happen | Any activity (read, write, submit evidence, raise tickets) is available in the Vue portal for anyone with the permission, staff and admins alike. **All reviewing and approving happens in Filament.** |
| Certificates | Not included. |
| PDF engine | DomPDF (already installed) for rendering + `qpdf` CLI for merging uploaded PDFs and stamping page numbers. |

---

## Phase 0: Prerequisites (from the codebase audit)

This module relies on permissions, timers and activity logs, so these fixes come first:

- Fix permission/gate name mismatches (`manage-own-work`, `view-team-data`, `view-all-data`, `manage-users`).
- Scope roles per organisation (or block admins from editing the shared `admin` role), and stop workers reaching `/admin` (`User::canAccessPanel`).
- Add `work_type` to `daily_plans`; fix `TimerController::stop()` writing `task_name` into `work_type`; reset accumulated seconds on restart.
- Fix `StopAbandonedTimers` (renamed column + missing `work_type`).
- Fix the clock-out carry-over deadlock (carry over to the next working day).

---

## Phase 1: System Registry

### Database
- **`systems`**: `id`, `organisation_id`, `name`, `slug`, `summary` (what it does), `description` (longtext), `department_id` (nullable), `criticality` (`low|medium|high|critical`), `status` (`active|deprecated|retired`), `url` (nullable), `support_contact` (text: how to get help when it breaks), `escalation_notes` (text), `manual_version` (string, default `0.1`), `created_by`, timestamps, soft deletes. Unique `(organisation_id, slug)`.
- **`system_members`**: `id`, `system_id`, `user_id`, `role` (`owner|backup_owner|reviewer|contact`), timestamps. Unique `(system_id, user_id, role)`.

### Backend
- Models: `System`, `SystemMember` (+ `Auditable`).
- `System` helpers: `owners()`, `reviewers()`, `contacts()`, `isOwnedBy(User)`, `canBeReviewedBy(User)`.
- `SystemPolicy`: org-scoped; manage requires `manage_systems`; owners can edit their own system's details.

### Filament (group: **Knowledge**)
- `SystemResource`: CRUD, member repeater (user + role), filters by department/criticality/status. Org-scoped `getEloquentQuery()`.

### Vue portal
- `views/knowledge/SystemsView.vue`: searchable grid of systems (name, summary, owner, criticality, my readiness badge).
- `views/knowledge/SystemDetailView.vue`: tabs for **Overview** (what it does, who owns it, who to contact when it breaks), **Manual**, **FAQ**, **My Readiness**, **Tickets**.

### API
```
GET  /v1/systems
GET  /v1/systems/{system}
```

### Permissions
`view_kb` (all roles), `manage_systems` (admin, super_admin).

---

## Phase 2: Manuals (Structure, Writing, Uploads, Approval, Activity)

### Concept
Each system has **one manual**: an **ordered tree of sections** (Chapter → Section → Sub-section). Every piece of content, written or uploaded, sits at a fixed position in that tree, so the manual is always in order and can be generated whole or in parts.

Each section is one of three kinds:
| Kind | Content |
|---|---|
| `written` | Rich text with inline screenshots + captions (the "build it gradually" path; can start as just screenshots + short notes). |
| `uploaded_pdf` | An uploaded PDF (optionally a page range of it) placed at this position in the tree. |
| `imported` | Created by the DOCX import wizard: a finished Word manual split into editable `written` sections by its headings. |

### Database
- **`manual_sections`**: `id`, `system_id`, `parent_id` (nullable, self-ref), `position` (int), `title`, `kind` (`written|uploaded_pdf`), `status` (`published|archived`), `current_revision_id` (nullable), `last_verified_at`, `last_verified_by`, `created_by`, timestamps, soft deletes. Index `(system_id, parent_id, position)`.
- **`manual_section_revisions`**: `id`, `section_id`, `version` (int), `body` (longtext HTML, for `written`), `file_path` / `file_name` / `page_from` / `page_to` (for `uploaded_pdf`), `change_summary`, `author_id`, `published_at`, timestamps. Revisions are immutable once published.
- **`manual_change_requests`** (the approval queue): `id`, `system_id`, `section_id` (nullable for a new section), `type` (`create|edit|move|delete|import`), `proposed_parent_id`, `proposed_position`, `proposed_title`, `proposed_body`, `proposed_file_path`, `payload` (json, used by imports), `status` (`draft|submitted|changes_requested|approved|rejected`), `author_id`, `reviewer_id`, `review_comment`, `submitted_at`, `reviewed_at`, `daily_plan_id` (nullable, see Activity below), timestamps.
- **`manual_assets`**: `id`, `system_id`, `path`, `caption`, `uploaded_by`, `width`, `height`, `size`, timestamps. Screenshots referenced from section HTML.
- **`manual_reads`**: `id`, `user_id`, `section_id`, `revision_version`, `read_at`. Unique `(user_id, section_id, revision_version)`. A newer revision means "unread again".
- **`systems.manual_version`** is bumped on each approval: minor (`1.3 → 1.4`) by default, major (`1.x → 2.0`) when the owner ticks "major change" (which flags competencies for re-check in Phase 4).

### Backend
- `ManualService`:
  - `tree(System, publishedOnly = true)`: nested ordered tree with numbering (`1`, `1.2`, `1.2.3`).
  - `submitChange(...)`, `approve(ChangeRequest, User, major = false)`, `reject(...)`, `requestChanges(...)`, all in DB transactions. `approve()` writes a new revision, updates the tree position, bumps `manual_version`.
  - `reorder(System, array $tree)`: owner-only drag-and-drop ordering.
- `DocxImportService`: reads a `.docx` with **PHPWord**, splits on Heading 1/2/3 into a proposed section tree (HTML bodies + extracted images into `manual_assets`), and saves it as a single `import` change request so the owner reviews the whole structure before it goes live.
- `PdfUploadService`: stores the PDF, counts pages (`qpdf --show-npages`), validates the page range.
- HTML sanitising of all submitted bodies (allow-list of tags) before storing. Section HTML is rendered in the portal and in PDFs.
- Notifications: on submit, inbox message to the system owners/backup owners; on review, inbox message to the author with the comment.
- `NotifyStaleSections` command (weekly): sections whose `last_verified_at` is older than 90 days → inbox reminder to the owner.

### Filament (review happens here)
- `ManualChangeRequestResource`: "Pending Approvals" queue, scoped to systems where the user is owner/backup owner (super_admin sees all).
  - Side-by-side diff of current vs proposed content (proposed position shown in the tree).
  - Actions: **Approve** (with "major change" toggle), **Request changes** (comment required), **Reject**.
  - Import requests show the full proposed tree; the owner can rename/reorder/drop sections before approving.
  - Navigation badge with the pending count.
- `SystemResource` → **Manual** relation page: tree view with drag-and-drop ordering, archive section, "mark verified".

### Vue portal
- `views/knowledge/ManualReaderView.vue`: left TOC tree (numbered), content pane, prev/next, in-manual search, **Mark as read**, "Last verified" badge, **Download this section / Download full manual** (Phase 3).
- `views/knowledge/ManualEditorView.vue` (needs `contribute_kb`):
  - **Tiptap** editor (`@tiptap/vue-3`) with headings, lists, tables, code, links, and **paste/drag screenshots** (uploads to `manual_assets`, adds a caption).
  - Choose position: "Add section under…" / "Insert after…" picker.
  - Save draft → **Submit for approval**. Shows status and reviewer comments; can revise and resubmit.
- `views/knowledge/ManualUploadView.vue`: upload a PDF (pick tree position + optional page range) or a DOCX (import wizard preview).
- `views/knowledge/MyContributionsView.vue`: my drafts, submitted, approved, sent back.

### Activity tracking (documentation as work)
Uses the existing plan + timer + activity-log flow, so it respects clock-in:
- **Contributing**: from the editor, **"Track as work"** creates (or reuses) today's `DailyPlan` (`task_name = "Documentation: {System} – {Section}"`, `work_type = indirect`, `daily_plans.manual_change_request_id` set) and starts its timer. Stopping the timer produces the activity log as normal. When the owner **approves** the change request, the linked plan is marked `done` automatically.
- **Assigned documentation**: owners/admins can assign "Document X in System Y" as a plan (Filament plan form gets an optional system/section field). The worker opens the editor straight from the plan card.
- **Studying**: "Track study time" on the reader creates a `growth` plan (`"Study: {System} manual"`) with a timer. "Mark as read" updates study progress (Phase 4).
- New nullable FKs on `daily_plans`: `system_id`, `manual_change_request_id`, `competency_check_id` (Phase 4).

### API
```
GET    /v1/systems/{system}/manual                    (published tree)
GET    /v1/manual-sections/{section}                  (published content)
POST   /v1/manual-sections/{section}/read
POST   /v1/systems/{system}/manual/assets             (screenshot upload)
GET    /v1/manual-changes?mine=1
POST   /v1/systems/{system}/manual/changes            (create draft)
PATCH  /v1/manual-changes/{change}
POST   /v1/manual-changes/{change}/submit
POST   /v1/systems/{system}/manual/upload-pdf
POST   /v1/systems/{system}/manual/import-docx
POST   /v1/manual-changes/{change}/track-time         (create plan + start timer)
```

### Permissions
`contribute_kb` (all roles by default), `approve_kb` (system owners via `system_members`, plus admin/super_admin).

---

## Phase 3: Manual Generation (Full or Partial)

### Behaviour
- **Generate full manual**, or **download any part**: one section, a chapter with all its sub-sections, or a multi-select of sections.
- Only **published** content is included. Every export is stamped with the manual version and date.
- Output order follows the tree exactly; uploaded PDFs are merged in at their position.

### Document layout
1. Cover page: organisation letterhead (existing `CompanyLetterhead`), system name, summary, owner(s), manual version, generated date.
2. Table of contents with numbered headings and **page numbers**.
3. Sections in tree order, numbered `1`, `1.1`, `1.1.1`.
4. Optional appendices (checkboxes): **FAQ** for this system, **Contacts & escalation**, **Change log** (revision summaries).
5. Footer on every page: `System · v{version} · Page X of Y`.
6. Video links in content are rendered as the link text + URL so they still work in print.

### Pipeline (`ManualExportService` + queued `GenerateManualPdf` job)
1. Resolve the requested sections into an ordered list.
2. Render each `written` section (or run of consecutive written sections) to a PDF chunk with DomPDF (screenshots embedded as base64, as `ExportService` already does). For `uploaded_pdf` sections, use the stored file (page range extracted with `qpdf --pages`).
3. Count pages per chunk → compute the start page of every section.
4. Render the cover + TOC (with real page numbers) via DomPDF.
5. Merge everything with `qpdf --empty --pages ...`.
6. Stamp the page-number footer: render an N-page "footer only" PDF with DomPDF and apply it with `qpdf --overlay`.
7. Store under `manual_exports/{system}/{version}/{hash}.pdf`. Repeat requests for the same version + selection are served from this cache.
8. Small exports (one section) are generated directly; full manuals are queued and the user gets an inbox message with the download link when it's ready.

### Database
- **`manual_exports`**: `id`, `system_id`, `requested_by`, `manual_version`, `selection_hash`, `section_ids` (json), `options` (json), `status` (`queued|ready|failed`), `file_path`, `page_count`, timestamps.

### API / UI
```
POST /v1/systems/{system}/manual/export        { section_ids?: [], include: [faq, contacts, changelog] }
GET  /v1/manual-exports/{export}               (status)
GET  /v1/manual-exports/{export}/download
```
Portal: export dialog in the reader (whole manual / this section / pick sections + appendices). Filament: the same action on the System page.

### Server requirement
`qpdf` binary installed on the server (Windows build available for local dev). Path configurable via `config/knowledge.php`.

---

## Phase 4: Competency Checks & Readiness

### Database
- **`competency_checks`**: `id`, `system_id`, `title`, `scenario` (longtext, e.g. "A client says a payment failed but money left their account. Show how you'd resolve it."), `rubric` (json: `[{criterion, mandatory}]`), `allowed_formats` (json subset of `text|screenshots|video_link`), `weight` (1–3, difficulty), `is_required_default` (bool), `valid_for_days` (nullable), `linked_section_ids` (json), `is_active`, `created_by`, timestamps.
- **`competency_requirements`**: `id`, `competency_check_id`, `scope_type` (`role|department|user`), `scope_id`. Who must pass it.
- **`competency_submissions`**: `id`, `competency_check_id`, `user_id`, `attempt_no`, `answer_text` (longtext), `status` (`draft|submitted|changes_requested|approved`), `reviewer_id`, `reviewed_at`, `rubric_result` (json: per criterion `met|not_met|exceeds`), `review_comment`, `manual_version_at_approval`, `expires_at` (nullable), `needs_recheck` (bool), `spot_checked_by`, `spot_check_result`, timestamps.
- **`submission_evidence`**: `id`, `submission_id`, `type` (`screenshot|video_link`), `path` or `url`, `caption`, timestamps. Video URLs validated against an allow-list of hosts (YouTube, Google Drive, Loom, OneDrive/SharePoint), with a "make sure the link is shareable with reviewers" notice.
- **`system_readiness`** (cache): `user_id`, `system_id`, `score` (0–100), `level`, `study_pct`, `required_count`, `approved_count`, `computed_at`. Unique `(user_id, system_id)`.

### Readiness score
- Applies to a person + system when at least one check is **required** for them (by role, department or individually). Otherwise it shows **N/A**, unless they opt in.
- A check counts toward the score when its latest submission is **approved**, not expired, and not flagged `needs_recheck`.
- **Score** = `Σ weight(valid approved required checks) ÷ Σ weight(required checks) × 100`.
- **Levels**:
  | Level | Rule |
  |---|---|
  | Not started | No submissions and study progress 0% |
  | Learning | Score < 100 |
  | Ready | Score = 100 |
  | Expert | Ready **and** all optional checks approved **and** at least one rubric criterion marked `exceeds` |
- **Study progress** (`study_pct`) = published sections read at their current revision ÷ total published sections. Shown **next to** readiness but never part of it (reading isn't competence).
- Expiry: approvals with `valid_for_days` expire (nightly `RecalculateReadiness` command). A **major** manual version bump flags `needs_recheck` on that system's approvals.
- Recalculated on approve / expire / requirement change / major version; nightly full recompute as a safety net.

### Backend
- `ReadinessService::recalculate(User, System)`, `forUser()`, `forSystem()`, `coverage(System)` (number of people Ready).
- `CompetencyService`: submit, resubmit (new attempt), review (all mandatory rubric criteria must be `met` to approve), spot-check.
- Onboarding: on user create or role/department change, required checks are assigned automatically as `DailyPlan`s (`work_type = growth`, `competency_check_id` set). The plan auto-completes when the submission is approved.

### Filament (review)
- `CompetencyCheckResource`: CRUD with rubric repeater, requirements, linked manual sections.
- `CompetencySubmissionResource` "Review Queue": scoped to systems where the user is owner/backup owner/reviewer (`system_members`). Shows the scenario, answer, screenshots, embedded YouTube preview / Drive link, rubric checklist. Actions: **Approve**, **Send back with comments**. A reviewer cannot review their own submission.
- **Spot-check**: `spot_check_rate` organisation setting (e.g. 10%). That share of peer-approved submissions lands in a "Spot-check" tab for users with `spot_check_reviews`; a failed spot-check reverts the approval to `changes_requested` and notifies both people.
- Widgets: **Readiness heatmap** (people × systems, coloured by level, filter by department) and **Single point of knowledge** (systems with fewer than 2 Ready people).

### Vue portal
- `views/knowledge/MyReadinessView.vue`: per-system score, level, study progress, outstanding checks.
- `views/knowledge/CompetencyCheckView.vue`: scenario, linked sections to study first, answer editor (text + screenshot paste + video links), submit, review history and comments.
- System page → **My Readiness** tab. Team page shows readiness for people a user may see.

### API
```
GET  /v1/readiness                               (mine, all systems)
GET  /v1/systems/{system}/checks
GET  /v1/competency-checks/{check}
POST /v1/competency-checks/{check}/submissions
PATCH /v1/competency-submissions/{submission}
POST /v1/competency-submissions/{submission}/submit
POST /v1/competency-submissions/{submission}/evidence
```

### Permissions
`submit_competency` (all), `manage_competency_checks` (admin, owners), `review_competency` (granted per system via `system_members.role = reviewer`/owner), `spot_check_reviews` (admin, super_admin).

---

## Phase 5: Tickets & FAQ

### Database
- **`tickets`**: `id`, `organisation_id`, `reference` (e.g. `TCK-000123`), `system_id` (**required**), `manual_section_id` (nullable), `project_client_id` (nullable, the affected client), `title`, `description`, `priority`, `status` (`open|in_progress|waiting_on_reporter|resolved|closed`), `reporter_id`, `assignee_id`, `resolution` (longtext), `resolved_at`, `resolved_by`, `faq_entry_id` (nullable), timestamps.
- **`ticket_comments`**: `id`, `ticket_id`, `user_id`, `body`, `is_internal` (bool, internal notes hidden from the reporter), timestamps.
- **`ticket_attachments`**: screenshots / links.
- **`faq_entries`**: `id`, `system_id`, `manual_section_id` (nullable), `question`, `answer` (longtext), `status` (`draft|published|rejected|archived`), `occurrences` (int, number of tickets merged into it), `views`, `helpful_yes`, `helpful_no`, `created_from_ticket_id`, `published_by`, `published_at`, timestamps. FULLTEXT index on `(question, answer)`.
- **`faq_entry_tickets`**: pivot linking every ticket that was answered by this FAQ.

### Ticket → FAQ publish flow
1. **Resolve** (Filament ticket action): resolver writes the resolution; the "Suggest as FAQ" toggle is on by default.
2. **Draft**: a `faq_entries` row is created with `status = draft`, question from the ticket title/description, answer from the resolution. The system's owners get an inbox message: "FAQ draft awaiting review".
3. **Owner edits** in Filament (`FaqEntryResource`, Drafts tab, scoped to their systems):
   - rewrites the question in general terms and removes client names, account numbers and other personal details (a warning banner reminds them);
   - optionally links a manual section.
4. The owner then chooses one action:
   - **Publish**: status `published`, `published_by/at` set; it immediately shows in portal search, on the system's FAQ tab, in ticket-form suggestions and in the manual export appendix.
   - **Merge into existing FAQ**: for duplicates. Links the ticket to the existing entry and increments `occurrences`; the draft is discarded.
   - **Reject** (with a reason, e.g. "one-off issue").
5. Published entries can later be edited (kept in revision history), archived, or re-verified.

### Backend
- `TicketService`: create, assign, comment, change status, resolve (in a transaction, creating the FAQ draft).
- `FaqService`: search (MySQL FULLTEXT; can move to Laravel Scout + Meilisearch later), publish, merge, record views/helpful votes.
- Notifications via the inbox: new ticket → system contacts; status changes → reporter; FAQ draft → owners.

### Filament
- `TicketResource` (group **Support**): queue with filters (system, status, assignee, priority), assign, internal notes, resolve action.
- `FaqEntryResource`: Drafts / Published / Rejected tabs with Publish, Merge and Reject actions.
- Widget **Systems causing the most confusion**: tickets per system (last 30/90 days), average time to resolve, % deflected by FAQ.

### Vue portal
- `views/support/TicketsView.vue`: my tickets, raise a ticket (pick system **first**). While typing the title, **matching FAQ entries and manual sections are suggested** ("Did this answer your question?").
- `views/support/TicketDetailView.vue`: thread (non-internal comments), status, resolution.
- `views/knowledge/SearchView.vue`: one search box across manual sections, FAQ, and the user's own tickets.
- System page → **FAQ** tab (with helpful yes/no) and **Tickets** tab.

### API
```
GET  /v1/tickets            POST /v1/tickets
GET  /v1/tickets/{ticket}   POST /v1/tickets/{ticket}/comments
GET  /v1/faq?system=&q=     POST /v1/faq/{entry}/feedback
GET  /v1/knowledge/search?q=
```

### Permissions
`raise_tickets` (all), `handle_tickets` (admin + system contacts), `publish_faq` (owners via `system_members`, plus admin/super_admin).

---

## Phase 6: Insights & Maintenance

- Readiness heatmap + "single point of knowledge" alert (Phase 4 widgets) on the Filament dashboard.
- "Most confusing systems" report + export.
- Stale documentation reminders (90 days without verification).
- Onboarding view: a new hire's progress across all required systems.

---

## New Scheduled Commands

| Command | Schedule | Purpose |
|---|---|---|
| `worktrack:knowledge:recalculate-readiness` | Daily 01:00 | Expire approvals, recompute `system_readiness` |
| `worktrack:knowledge:stale-sections` | Weekly | Remind owners to verify old sections |
| `worktrack:knowledge:prune-exports` | Weekly | Delete cached exports of superseded manual versions |

## New Dependencies

| Package | Use |
|---|---|
| `phpoffice/phpword` | DOCX import → sections |
| `qpdf` (server binary) | PDF merge, page ranges, page counting, page-number overlay |
| `@tiptap/vue-3` + `@tiptap/starter-kit` + image/table extensions | Portal editor |
| HTML sanitiser (e.g. `symfony/html-sanitizer`) | Clean submitted HTML |

## Verification

- Feature tests per phase: permissions and org scoping for every endpoint; approval flow (submit → approve → revision + version bump); export ordering and page count; readiness formula (required/optional/expired/recheck cases); ticket → FAQ draft → publish/merge/reject.
- `php artisan migrate:fresh --seed` with a demo seeder: 3 systems, a manual with written + uploaded PDF sections, checks, submissions and tickets.
- Manual check: generate a full manual and a single-chapter export and confirm order, TOC page numbers and footers.
