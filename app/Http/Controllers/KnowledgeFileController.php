<?php

namespace App\Http\Controllers;

use App\Models\ManualAsset;
use App\Models\ManualChangeRequest;
use App\Models\ManualSectionRevision;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves knowledge-base files. Every route is behind the "signed" middleware: links are only
 * handed out by the API (or Filament) to people allowed to see them, and expire after a while.
 */
class KnowledgeFileController extends Controller
{
    public function asset(ManualAsset $asset): StreamedResponse
    {
        return $this->inline($asset->path, $asset->original_name ?: 'image', $asset->mime);
    }

    public function revisionFile(ManualSectionRevision $revision): StreamedResponse
    {
        abort_unless($revision->file_path, 404);

        return $this->inline($revision->file_path, $revision->file_name ?: 'document.pdf', 'application/pdf');
    }

    public function changeFile(ManualChangeRequest $change): StreamedResponse
    {
        abort_unless($change->file_path, 404);

        $isPdf = str_ends_with(strtolower($change->file_path), '.pdf');

        return $isPdf
            ? $this->inline($change->file_path, $change->file_name ?: 'document.pdf', 'application/pdf')
            : Storage::disk(config('knowledge.disk'))->download($change->file_path, $change->file_name ?: 'document.docx');
    }

    public function export(\App\Models\ManualExport $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($export->isReady(), 404);
        $disk = Storage::disk(config('knowledge.disk'));
        abort_unless($disk->exists($export->file_path), 404);

        return $disk->download($export->file_path, $export->downloadName(), ['Content-Type' => 'application/pdf']);
    }

    private function inline(string $path, string $name, string $mime): StreamedResponse
    {
        $disk = Storage::disk(config('knowledge.disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $name, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
