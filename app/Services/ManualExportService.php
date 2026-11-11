<?php

namespace App\Services;

use App\Jobs\GenerateManualExport;
use App\Models\CompanyLetterhead;
use App\Models\ManualExport;
use App\Models\ManualSection;
use App\Models\System;
use App\Models\User;
use App\Support\Qpdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Generating manual PDFs: what to include, reusing unchanged exports, and running the renderer
 * (straight away for small exports, in the queue for large ones).
 */
class ManualExportService
{
    /** Exports with up to this many sections are generated during the request. */
    public const GENERATE_NOW_MAX_SECTIONS = 25;

    public function __construct(
        private readonly ManualService $manuals,
        private readonly ManualPdfRenderer $renderer,
        private readonly InboxService $inbox,
    ) {
    }

    /**
     * Ask for a PDF of the whole manual ($sectionIds = null) or of chosen sections
     * (each with everything under it).
     *
     * @param array<int>|null $sectionIds
     * @param array<string> $appendices
     */
    public function request(System $system, User $user, ?array $sectionIds, array $appendices = []): ManualExport
    {
        $sections = $this->resolveSections($system, $sectionIds);

        if ($sections->isEmpty()) {
            throw ValidationException::withMessages(['section_ids' => 'This manual has no published sections yet.']);
        }

        $options = ['appendices' => collect($appendices)->intersect(ManualExport::APPENDICES)->unique()->sort()->values()->all()];
        $selection = $sectionIds ? collect($sectionIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all() : null;
        $fingerprint = $this->fingerprint($system, $sections, $selection, $options);

        // Reuse a finished (or in-progress) export of exactly the same content.
        $existing = ManualExport::query()
            ->where('system_id', $system->id)
            ->where('fingerprint', $fingerprint)
            ->whereIn('status', [ManualExport::STATUS_READY, ManualExport::STATUS_QUEUED, ManualExport::STATUS_PROCESSING])
            ->latest('id')
            ->first();

        if ($existing?->isReady() && Storage::disk(config('knowledge.disk'))->exists($existing->file_path)) {
            return $existing;
        }

        if ($existing && !$existing->isReady() && $existing->created_at->gt(now()->subMinutes(30))) {
            return $existing;
        }

        $export = ManualExport::create([
            'system_id' => $system->id,
            'requested_by' => $user->id,
            'manual_version' => $system->manual_version,
            'fingerprint' => $fingerprint,
            'section_ids' => $selection,
            'options' => $options,
            'status' => ManualExport::STATUS_QUEUED,
        ]);

        if ($sections->count() <= self::GENERATE_NOW_MAX_SECTIONS) {
            $this->generate($export);

            // refresh() (not fresh()) keeps wasRecentlyCreated, so the API can answer 201.
            return $export->refresh();
        }

        GenerateManualExport::dispatch($export->id);

        return $export;
    }

    /**
     * Render the PDF and store it. When $notify is set (queued exports), the requester gets an
     * inbox message once it's ready (or if it failed).
     */
    public function generate(ManualExport $export, bool $notify = false): void
    {
        $export->update(['status' => ManualExport::STATUS_PROCESSING, 'error' => null]);
        $system = $export->system;
        $workDir = null;

        try {
            $sections = $this->resolveSections($system, $export->section_ids);
            $result = $this->renderer->render($system, $sections, $export->options ?? [], !$export->isFullManual());
            $workDir = dirname($result['path']);

            $path = "manual-exports/{$system->id}/{$export->id}.pdf";
            Storage::disk(config('knowledge.disk'))->put($path, file_get_contents($result['path']));

            $export->update([
                'status' => ManualExport::STATUS_READY,
                'file_path' => $path,
                'page_count' => $result['page_count'],
                'meta' => $result['meta'],
                'completed_at' => now(),
            ]);

            if ($notify) {
                $this->notifyRequester($export, true);
            }
        } catch (Throwable $e) {
            report($e);

            $export->update([
                'status' => ManualExport::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            if ($notify) {
                $this->notifyRequester($export, false);
            }
        } finally {
            if ($workDir && is_dir($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }

    /**
     * Published sections in manual order. For a selection, each chosen section comes with
     * everything below it.
     *
     * @return Collection<int, ManualSection>
     */
    public function resolveSections(System $system, ?array $sectionIds): Collection
    {
        $outline = $this->manuals->publishedOutline($system);

        if (empty($sectionIds)) {
            return $outline;
        }

        $chosen = $outline->whereIn('id', array_map('intval', $sectionIds));

        if ($chosen->count() !== count(array_unique($sectionIds))) {
            throw ValidationException::withMessages(['section_ids' => 'Some of the chosen sections are not in this manual.']);
        }

        $prefixes = $chosen->pluck('sort_key');

        return $outline->filter(fn (ManualSection $s) => $prefixes->contains(
            fn ($prefix) => $s->sort_key === $prefix || str_starts_with($s->sort_key, $prefix . '.')
        ))->values();
    }

    /**
     * Everything that affects the PDF. If none of it changed, the existing file is reused.
     */
    private function fingerprint(System $system, Collection $sections, ?array $selection, array $options): string
    {
        $letterhead = CompanyLetterhead::query()
            ->where('organisation_id', $system->organisation_id)
            ->where('is_active', true)
            ->latest('id')
            ->first(['id', 'updated_at']);

        return hash('sha256', json_encode([
            'layout' => ManualPdfRenderer::LAYOUT_VERSION,
            'system' => [$system->id, $system->name, $system->summary, $system->url, $system->manual_version,
                $system->support_contact, $system->escalation_notes, $system->updated_at?->timestamp],
            'members' => $system->members()->orderBy('id')->get(['user_id', 'role'])->toArray(),
            'letterhead' => [$letterhead?->id, $letterhead?->updated_at?->timestamp],
            'sections' => $sections->map(fn (ManualSection $s) => [$s->id, $s->number, $s->title, $s->kind, $s->current_revision_id])->all(),
            'selection' => $selection,
            'options' => $options,
            'qpdf' => Qpdf::available(),
            'date' => now()->toDateString(), // the cover shows the generation date
        ]));
    }

    private function notifyRequester(ManualExport $export, bool $ready): void
    {
        if (!$export->requested_by) {
            return;
        }

        $system = $export->system;
        $what = $export->isFullManual() ? 'manual' : 'manual extract';

        $this->inbox->sendMessage(
            organisationId: (int) $system->organisation_id,
            senderId: null,
            recipientIds: [$export->requested_by],
            subject: $ready ? "Your {$system->name} {$what} is ready" : "Your {$system->name} {$what} could not be generated",
            body: $ready
                ? "The PDF ({$export->page_count} pages, manual v{$export->manual_version}) is ready. Open the {$system->name} manual in Knowledge and choose Download PDF again to get it."
                : 'Something went wrong while generating the PDF. Please try again, and tell your administrator if it keeps happening.',
            messageType: 'system',
        );
    }
}
