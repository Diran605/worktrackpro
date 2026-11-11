<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesSystem;
use App\Http\Controllers\Controller;
use App\Models\ManualExport;
use App\Services\ManualExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * PDF downloads of a manual (whole or selected sections) from the portal.
 */
class ManualExportController extends Controller
{
    use ResolvesSystem;

    public function store(Request $request, string $slug, ManualExportService $exports): JsonResponse
    {
        $system = $this->resolveSystem($request, $slug);

        $data = $request->validate([
            'section_ids' => ['nullable', 'array', 'max:500'],
            'section_ids.*' => ['integer'],
            'appendices' => ['nullable', 'array'],
            'appendices.*' => [Rule::in(ManualExport::APPENDICES)],
        ]);

        $export = $exports->request($system, $request->user(), $data['section_ids'] ?? null, $data['appendices'] ?? []);

        return response()->json(['data' => $this->present($export)], $export->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, ManualExport $export): JsonResponse
    {
        abort_unless($export->system && $export->system->organisation_id === $request->user()->organisation_id, 404);
        $this->authorize('view', $export->system);

        return response()->json(['data' => $this->present($export)]);
    }

    private function present(ManualExport $export): array
    {
        return [
            'id' => $export->id,
            'status' => $export->status,
            'scope' => $export->isFullManual() ? 'full' : 'extract',
            'manual_version' => $export->manual_version,
            'page_count' => $export->page_count,
            'download_url' => $export->isReady() ? $export->signedDownloadUrl() : null,
            'file_name' => $export->downloadName(),
            'error' => $export->status === ManualExport::STATUS_FAILED
                ? 'The PDF could not be generated. Please try again, and tell your administrator if it keeps happening.'
                : null,
            'created_at' => $export->created_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
        ];
    }
}
