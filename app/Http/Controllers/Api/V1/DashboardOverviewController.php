<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\ManualChangeRequest;
use App\Models\ManualSection;
use App\Models\ManualSectionRevision;
use App\Models\MessageRecipient;
use App\Models\System;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\ManualService;
use App\Support\DocsContext;
use App\Support\Modules;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The staff portal dashboard cards, for the modules the organisation has:
 * inbox (always), attendance, knowledge base and documentation library.
 * Work tracking keeps its own endpoint (dashboard/stats/weekly).
 */
class DashboardOverviewController extends Controller
{
    public function __invoke(Request $request, ManualService $manuals): JsonResponse
    {
        $user = $request->user();
        $organisationId = $user->organisation_id;

        $data = ['inbox' => $this->inbox($user)];

        if (Modules::organisationHas($organisationId, Modules::ATTENDANCE)) {
            $data['attendance'] = $this->attendance($user);
        }

        if (Modules::organisationHas($organisationId, Modules::KNOWLEDGE) && $user->can(Permissions::VIEW_KB)) {
            $data['knowledge'] = $this->knowledge($user, $manuals);
        }

        if (Modules::organisationHas($organisationId, Modules::DOCS_LIBRARY)) {
            $library = $this->library($user);
            if ($library !== []) {
                $data['library'] = $library;
            }
        }

        return response()->json(['data' => $data]);
    }

    private function inbox(User $user): array
    {
        $mine = MessageRecipient::query()->where('recipient_id', $user->id);

        return [
            'unread' => (clone $mine)->whereNull('read_at')->count(),
            'latest' => (clone $mine)
                ->with('message.sender:id,name')
                ->latest('id')
                ->limit(3)
                ->get()
                ->filter(fn ($r) => $r->message)
                ->map(fn ($r) => [
                    'id' => $r->message->id,
                    'subject' => $r->message->subject,
                    'from' => $r->message->sender?->name ?? 'System',
                    'at' => $r->message->created_at?->toIso8601String(),
                    'read' => $r->read_at !== null,
                ])
                ->values()
                ->all(),
        ];
    }

    /** This week (Monday to today): hours worked and days present, and today's clock-in. */
    private function attendance(User $user): array
    {
        $today = Carbon::parse($user->localToday());
        $sessions = WorkSession::query()
            ->where('user_id', $user->id)
            // Date-only comparisons: session dates may be stored with a time part.
            ->whereDate('date', '>=', $today->copy()->startOfWeek()->toDateString())
            ->whereDate('date', '<=', $today->toDateString())
            ->get();

        $minutes = $sessions->sum(fn (WorkSession $s) => $s->status === SessionStatus::Active && $s->clock_in
            ? (int) $s->clock_in->diffInMinutes(now())
            : (int) $s->total_minutes);
        $todaySession = $sessions->first(fn ($s) => Carbon::parse($s->date)->isSameDay($today));

        return [
            'week_minutes' => $minutes,
            'days_present' => $sessions->map(fn ($s) => Carbon::parse($s->date)->toDateString())->unique()->count(),
            'today_clock_in' => $todaySession?->clock_in?->toIso8601String(),
        ];
    }

    private function knowledge(User $user, ManualService $manuals): array
    {
        // Continue reading: manuals with something left to read, the ones already started first.
        $reading = System::query()
            ->where('organisation_id', $user->organisation_id)
            ->where('status', '!=', 'retired')
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->map(function (System $system) use ($user, $manuals) {
                $progress = $manuals->studyProgress($system, $user);
                if ($progress['total'] === 0 || $progress['percent'] === 100) {
                    return null;
                }
                $next = $manuals->publishedOutline($system)->first(fn (ManualSection $s) => !in_array($s->id, $progress['read_ids'], true));

                return [
                    'name' => $system->name,
                    'slug' => $system->slug,
                    'read' => $progress['read'],
                    'total' => $progress['total'],
                    'percent' => $progress['percent'],
                    'next_section_id' => $next?->id,
                ];
            })
            ->filter()
            ->sortByDesc(fn ($m) => [$m['percent'] > 0 ? 1 : 0, $m['percent']])
            ->take(4)
            ->values()
            ->all();

        $contributions = ManualChangeRequest::query()
            ->where('author_id', $user->id)
            ->whereIn('status', [ManualChangeRequest::STATUS_DRAFT, ManualChangeRequest::STATUS_SUBMITTED, ManualChangeRequest::STATUS_CHANGES_REQUESTED])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'continue_reading' => $reading,
            'my_contributions' => [
                'drafts' => (int) ($contributions[ManualChangeRequest::STATUS_DRAFT] ?? 0),
                'waiting' => (int) ($contributions[ManualChangeRequest::STATUS_SUBMITTED] ?? 0),
                'sent_back' => (int) ($contributions[ManualChangeRequest::STATUS_CHANGES_REQUESTED] ?? 0),
            ],
            // Owners and approvers: changes by others waiting for them.
            'to_review' => ManualChangeRequest::query()
                ->where('status', ManualChangeRequest::STATUS_SUBMITTED)
                ->where('author_id', '!=', $user->id)
                ->whereIn('system_id', $this->approvableSystemIds($user))
                ->count(),
        ];
    }

    /** For owners: their library products with approved changes not yet published. */
    private function library(User $user): array
    {
        $systems = System::query()
            ->whereIn('id', $this->approvableSystemIds($user))
            ->whereIn('library_visibility', [System::LIBRARY_PUBLIC, System::LIBRARY_RESTRICTED])
            ->with('organisation')
            ->orderBy('name')
            ->get();

        return $systems->map(function (System $system) {
            $waiting = ManualSectionRevision::query()
                ->whereNull('release_id')
                ->whereIn('manual_section_id', ManualSection::withTrashed()->where('system_id', $system->id)->select('id'))
                ->count();

            return $waiting === 0 ? null : [
                'name' => $system->name,
                'slug' => $system->slug,
                'waiting' => $waiting,
                'library_path' => DocsContext::pathOf(DocsContext::mainSite($system->organisation)->system($system)),
            ];
        })->filter()->values()->all();
    }

    /** @return array<int, int> systems whose manual this user may approve */
    private function approvableSystemIds(User $user): array
    {
        if ($user->can(Permissions::APPROVE_KB)) {
            return System::query()->where('organisation_id', $user->organisation_id)->pluck('id')->all();
        }

        return $user->ownedSystemIds();
    }
}
