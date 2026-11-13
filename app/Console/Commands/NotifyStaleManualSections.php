<?php

namespace App\Console\Commands;

use App\Models\ManualSection;
use App\Models\System;
use App\Services\InboxService;
use Illuminate\Console\Command;

class NotifyStaleManualSections extends Command
{
    protected $signature = 'worktrack:knowledge:stale-sections {--days=}';

    protected $description = 'Remind system owners about manual sections not verified recently';

    public function handle(InboxService $inbox): int
    {
        $days = (int) ($this->option('days') ?: config('knowledge.stale_after_days'));
        $cutoff = now()->subDays($days);
        $notified = 0;

        // Only organisations with the Knowledge Base module.
        System::query()->where('status', '!=', 'retired')->whereIn('organisation_id', \Illuminate\Support\Facades\DB::table('organisation_modules')->where('module', \App\Support\Modules::KNOWLEDGE)->select('organisation_id'))->each(function (System $system) use ($inbox, $cutoff, $days, &$notified) {
            $stale = ManualSection::query()
                ->where('system_id', $system->id)
                ->published()
                ->where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', $cutoff))
                ->orderBy('sort_key')
                ->get();

            $owners = $system->owners()->pluck('id')->all();

            if ($stale->isEmpty() || $owners === []) {
                return;
            }

            $list = $stale->take(20)->map(fn ($s) => "• {$s->number} {$s->title}")->join("\n");
            $more = $stale->count() > 20 ? "\n…and " . ($stale->count() - 20) . ' more.' : '';

            $inbox->sendMessage(
                organisationId: (int) $system->organisation_id,
                senderId: null,
                recipientIds: $owners,
                subject: "{$stale->count()} manual section(s) to re-check: {$system->name}",
                body: "These sections of the {$system->name} manual haven't been verified in over {$days} days:\n\n{$list}{$more}\n\n"
                    . 'If they are still accurate, use "Mark as still accurate" under Knowledge > Manual Structure. Otherwise, update them in the portal.',
                messageType: 'system',
            );

            $notified++;
        });

        $this->info("Sent reminders for {$notified} system(s).");

        return Command::SUCCESS;
    }
}
