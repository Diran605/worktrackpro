<?php

namespace App\Jobs;

use App\Models\ManualExport;
use App\Services\ManualExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generates a large manual PDF in the background, then tells the requester via the inbox.
 */
class GenerateManualExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $exportId)
    {
    }

    public function handle(ManualExportService $exports): void
    {
        $export = ManualExport::find($this->exportId);

        if ($export && !$export->isReady()) {
            $exports->generate($export, notify: true);
        }
    }

    public function failed(?\Throwable $e): void
    {
        ManualExport::whereKey($this->exportId)->update([
            'status' => ManualExport::STATUS_FAILED,
            'error' => $e ? mb_substr($e->getMessage(), 0, 1000) : 'The job failed.',
        ]);
    }
}
