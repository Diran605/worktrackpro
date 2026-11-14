<?php

namespace App\Console\Commands;

use App\Models\CompetencySubmission;
use App\Models\User;
use App\Services\CompetencyService;
use App\Services\InboxService;
use App\Support\Modules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily: tell people whose passes expired in the last day, and (with Work Tracking) add plan tasks
 * for required checks nobody is working on yet, e.g. after a role change. Safe to run again.
 */
class RefreshCompetencyPlans extends Command
{
    protected $signature = 'worktrack:competency:refresh';

    protected $description = 'Notify people about expired competency passes and add plan tasks for required checks';

    public function handle(CompetencyService $competency, InboxService $inbox): int
    {
        $organisations = DB::table('organisation_modules')->where('module', Modules::COMPETENCY)->pluck('organisation_id');

        // Passes that expired since yesterday's run.
        $expired = CompetencySubmission::query()
            ->where('status', CompetencySubmission::STATUS_APPROVED)
            ->whereBetween('expires_at', [now()->subDay(), now()])
            ->whereHas('user', fn ($q) => $q->whereIn('organisation_id', $organisations)->where('is_active', true))
            ->with(['check.system', 'user'])
            ->get();

        foreach ($expired->groupBy('user_id') as $userSubmissions) {
            $user = $userSubmissions->first()->user;
            $titles = $userSubmissions->map(fn ($s) => '- ' . $s->check->title . ' (' . $s->check->system?->name . ')')->join("\n");

            $inbox->sendMessage(
                organisationId: (int) $user->organisation_id,
                senderId: null,
                recipientIds: [$user->id],
                subject: 'Competency checks to renew',
                body: "These competency checks have expired. Please answer them again:\n\n{$titles}\n\nOpen them from Knowledge > My readiness.",
                messageType: 'system',
            );
        }

        $added = 0;
        User::query()
            ->whereIn('organisation_id', $organisations)
            ->where('is_active', true)
            ->with('roles')
            ->each(function (User $user) use ($competency, &$added) {
                $added += $competency->assignPlans($user);
            });

        $this->info("Expired passes: {$expired->count()}. Plan tasks added: {$added}.");

        return self::SUCCESS;
    }
}
