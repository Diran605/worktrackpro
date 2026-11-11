<?php

namespace App\Console\Commands;

use App\Models\ManualExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PruneManualExports extends Command
{
    protected $signature = 'worktrack:knowledge:prune-exports {--days=30}';

    protected $description = 'Delete generated manual PDFs that are old or failed (they are regenerated on request)';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $disk = Storage::disk(config('knowledge.disk'));
        $deleted = 0;

        ManualExport::query()
            ->where(fn ($q) => $q
                ->where('created_at', '<', $cutoff)
                ->orWhere(fn ($q) => $q->where('status', ManualExport::STATUS_FAILED)->where('created_at', '<', now()->subDay())))
            ->each(function (ManualExport $export) use ($disk, &$deleted) {
                if ($export->file_path) {
                    $disk->delete($export->file_path);
                }
                $export->delete();
                $deleted++;
            });

        // Leftover temporary files from interrupted renders
        $work = storage_path('app/manual-export-work');
        if (is_dir($work)) {
            foreach (File::directories($work) as $dir) {
                if (filemtime($dir) < now()->subDay()->getTimestamp()) {
                    File::deleteDirectory($dir);
                }
            }
        }

        $this->info("Deleted {$deleted} export(s).");

        return Command::SUCCESS;
    }
}
