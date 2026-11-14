<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesSystem;
use App\Http\Controllers\Controller;
use App\Http\Resources\DailyPlanResource;
use App\Http\Resources\ManualChangeResource;
use App\Models\ManualChangeRequest;
use App\Models\ManualSection;
use App\Models\System;
use App\Services\DocxImportService;
use App\Services\ManualService;
use App\Services\PdfImportService;
use App\Support\ManualContent;
use App\Support\PdfInfo;
use App\Support\Qpdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Proposing manual changes from the portal. Everything here is a draft until an owner approves it.
 */
class ManualChangeController extends Controller
{
    use ResolvesSystem;

    public function __construct(private readonly ManualService $manuals)
    {
    }

    /**
     * The user's own contributions, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $changes = ManualChangeRequest::query()
            ->where('author_id', $request->user()->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with(['system:id,name,slug', 'section', 'reviewer:id,name'])
            ->latest('updated_at')
            ->paginate(20);

        return ManualChangeResource::collection($changes);
    }

    public function show(Request $request, ManualChangeRequest $change): ManualChangeResource
    {
        $this->authorizeAuthor($request, $change);

        return new ManualChangeResource($change->load(['system:id,name,slug', 'section.currentRevision', 'reviewer:id,name']));
    }

    /**
     * Start a new section or an edit of an existing one.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $system = $this->resolveSystem($request, $slug);
        $this->authorize('contribute', $system);

        $data = $request->validate([
            'type' => ['required', Rule::in([ManualChangeRequest::TYPE_CREATE, ManualChangeRequest::TYPE_EDIT])],
            'kind' => ['sometimes', Rule::in([ManualSection::KIND_WRITTEN, ManualSection::KIND_UPLOADED_PDF])],
            'manual_section_id' => ['required_if:type,edit', 'nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000000'],
            'change_summary' => ['nullable', 'string', 'max:500'],
            'proposed_parent_id' => ['nullable', 'integer'],
            'proposed_after_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        if ($data['type'] === ManualChangeRequest::TYPE_EDIT) {
            $section = $this->resolveSection($system, $data['manual_section_id']);

            // One open edit per person per section: continue it instead of starting another.
            $open = ManualChangeRequest::query()
                ->where('author_id', $user->id)
                ->where('manual_section_id', $section->id)
                ->where('type', ManualChangeRequest::TYPE_EDIT)
                ->whereIn('status', [...ManualChangeRequest::EDITABLE_STATUSES, ManualChangeRequest::STATUS_SUBMITTED])
                ->first();

            if ($open) {
                return (new ManualChangeResource($open->load(['system:id,name,slug', 'section.currentRevision'])))
                    ->response()
                    ->setStatusCode(200);
            }

            $revision = $section->currentRevision;

            $change = ManualChangeRequest::create([
                'system_id' => $system->id,
                'manual_section_id' => $section->id,
                'type' => ManualChangeRequest::TYPE_EDIT,
                'kind' => $data['kind'] ?? $section->kind,
                'title' => $data['title'] ?? $section->title,
                'body' => array_key_exists('body', $data) ? ManualContent::toStorage($data['body'], $system) : $revision?->body,
                'file_path' => $revision?->file_path,
                'file_name' => $revision?->file_name,
                'file_size' => $revision?->file_size,
                'page_count' => $revision?->page_count,
                'page_from' => $revision?->page_from,
                'page_to' => $revision?->page_to,
                'change_summary' => $data['change_summary'] ?? null,
                'base_version' => $section->currentVersion(),
                'status' => ManualChangeRequest::STATUS_DRAFT,
                'author_id' => $user->id,
            ]);
        } else {
            [$parentId, $afterId] = $this->validatedPosition($system, $data['proposed_parent_id'] ?? null, $data['proposed_after_id'] ?? null);

            $change = ManualChangeRequest::create([
                'system_id' => $system->id,
                'type' => ManualChangeRequest::TYPE_CREATE,
                'kind' => $data['kind'] ?? ManualSection::KIND_WRITTEN,
                'title' => $data['title'] ?? 'Untitled section',
                'body' => ManualContent::toStorage($data['body'] ?? '', $system),
                'change_summary' => $data['change_summary'] ?? null,
                'proposed_parent_id' => $parentId,
                'proposed_after_id' => $afterId,
                'status' => ManualChangeRequest::STATUS_DRAFT,
                'author_id' => $user->id,
            ]);
        }

        return (new ManualChangeResource($change->load(['system:id,name,slug', 'section.currentRevision'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, ManualChangeRequest $change): ManualChangeResource
    {
        $this->authorizeAuthor($request, $change);
        $this->ensureEditable($change);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:2000000'],
            'kind' => ['sometimes', Rule::in([ManualSection::KIND_WRITTEN, ManualSection::KIND_UPLOADED_PDF])],
            'change_summary' => ['sometimes', 'nullable', 'string', 'max:500'],
            'proposed_parent_id' => ['sometimes', 'nullable', 'integer'],
            'proposed_after_id' => ['sometimes', 'nullable', 'integer'],
            'page_from' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'page_to' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        if (array_key_exists('body', $data)) {
            $data['body'] = ManualContent::toStorage($data['body'], $change->system);
        }

        if ($change->type === ManualChangeRequest::TYPE_CREATE
            && (array_key_exists('proposed_parent_id', $data) || array_key_exists('proposed_after_id', $data))) {
            [$data['proposed_parent_id'], $data['proposed_after_id']] = $this->validatedPosition(
                $change->system,
                $data['proposed_parent_id'] ?? $change->proposed_parent_id,
                $data['proposed_after_id'] ?? null,
            );
        } else {
            unset($data['proposed_parent_id'], $data['proposed_after_id']);
        }

        if (array_key_exists('page_from', $data) || array_key_exists('page_to', $data)) {
            $this->validatePageRange(
                $data['page_from'] ?? $change->page_from,
                $data['page_to'] ?? $change->page_to,
                $change->page_count,
            );
        }

        $change->update($data);

        return new ManualChangeResource($change->fresh(['system:id,name,slug', 'section.currentRevision']));
    }

    /**
     * Attach a PDF to a draft (turns it into an uploaded-PDF section).
     */
    public function uploadPdf(Request $request, ManualChangeRequest $change): ManualChangeResource
    {
        $this->authorizeAuthor($request, $change);
        $this->ensureEditable($change);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:' . config('knowledge.max_pdf_kb')],
            'page_from' => ['nullable', 'integer', 'min:1'],
            'page_to' => ['nullable', 'integer', 'min:1'],
        ]);

        $file = $request->file('file');

        if (!PdfInfo::looksLikePdf($file->getRealPath())) {
            throw ValidationException::withMessages(['file' => 'This file is not a valid PDF.']);
        }

        $pageCount = PdfInfo::pageCount($file->getRealPath());
        $this->validatePageRange($request->integer('page_from') ?: null, $request->integer('page_to') ?: null, $pageCount);

        $path = $file->storeAs(
            "manual-files/{$change->system_id}",
            Str::uuid() . '.pdf',
            config('knowledge.disk')
        );
        $pageFrom = $request->integer('page_from') ?: null;
        $pageTo = $request->integer('page_to') ?: null;
        $size = $file->getSize();

        // Only part of the PDF: keep just those pages, so readers see exactly that part.
        if (($pageFrom || $pageTo) && $pageCount && Qpdf::available()) {
            $disk = Storage::disk(config('knowledge.disk'));
            $full = $disk->path($path);
            $cut = $full . '.cut.pdf';
            Qpdf::extractPages($full, $pageFrom ?: 1, $pageTo ?: $pageCount, $cut);
            rename($cut, $full);
            $pageCount = ($pageTo ?: $pageCount) - ($pageFrom ?: 1) + 1;
            $pageFrom = $pageTo = null;
            $size = $disk->size($path);
        }

        $change->update([
            'kind' => ManualSection::KIND_UPLOADED_PDF,
            'file_path' => $path,
            'file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'file_size' => $size,
            'page_count' => $pageCount,
            'page_from' => $pageFrom,
            'page_to' => $pageTo,
            'title' => $change->title === 'Untitled section'
                ? Str::of($file->getClientOriginalName())->beforeLast('.')->limit(250, '')->toString()
                : $change->title,
        ]);

        return new ManualChangeResource($change->fresh(['system:id,name,slug', 'section.currentRevision']));
    }

    /**
     * Upload a finished Word manual: it is split into proposed sections for the owner to approve.
     */
    public function importDocx(Request $request, string $slug, DocxImportService $importer): JsonResponse
    {
        $system = $this->resolveSystem($request, $slug);
        $this->authorize('contribute', $system);

        $request->validate([
            'file' => ['required', 'file', 'mimes:docx', 'max:' . config('knowledge.max_docx_kb')],
            'proposed_parent_id' => ['nullable', 'integer'],
            'proposed_after_id' => ['nullable', 'integer'],
            'change_summary' => ['nullable', 'string', 'max:500'],
        ]);

        [$parentId, $afterId] = $this->validatedPosition($system, $request->integer('proposed_parent_id') ?: null, $request->integer('proposed_after_id') ?: null);

        $file = $request->file('file');
        $payload = $importer->parse($file, $system, $request->user());
        $path = $file->storeAs("manual-imports/{$system->id}", Str::uuid() . '.docx', config('knowledge.disk'));

        $change = ManualChangeRequest::create([
            'system_id' => $system->id,
            'type' => ManualChangeRequest::TYPE_IMPORT,
            'kind' => ManualSection::KIND_WRITTEN,
            'title' => Str::of($file->getClientOriginalName())->beforeLast('.')->limit(250, '')->toString(),
            'file_path' => $path,
            'file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'file_size' => $file->getSize(),
            'payload' => $payload,
            'proposed_parent_id' => $parentId,
            'proposed_after_id' => $afterId,
            'change_summary' => $request->input('change_summary'),
            'status' => ManualChangeRequest::STATUS_DRAFT,
            'author_id' => $request->user()->id,
        ]);

        return (new ManualChangeResource($change->load('system:id,name,slug')))->response()->setStatusCode(201);
    }

    /**
     * Upload a PDF that has several chapters: it is split into proposed sections by page range,
     * from its bookmarks. The author can change the split (updateImportSections) before submitting.
     */
    public function importPdf(Request $request, string $slug, PdfImportService $importer): JsonResponse
    {
        $system = $this->resolveSystem($request, $slug);
        $this->authorize('contribute', $system);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:' . config('knowledge.max_pdf_kb')],
            'proposed_parent_id' => ['nullable', 'integer'],
            'proposed_after_id' => ['nullable', 'integer'],
            'change_summary' => ['nullable', 'string', 'max:500'],
        ]);

        if (!Qpdf::available()) {
            throw ValidationException::withMessages(['file' => 'Splitting a PDF needs the qpdf tool on the server. Upload it as one section instead, or ask your administrator to install qpdf.']);
        }

        [$parentId, $afterId] = $this->validatedPosition($system, $request->integer('proposed_parent_id') ?: null, $request->integer('proposed_after_id') ?: null);

        $file = $request->file('file');
        if (!PdfInfo::looksLikePdf($file->getRealPath())) {
            throw ValidationException::withMessages(['file' => 'This file is not a valid PDF.']);
        }

        $pageCount = PdfInfo::pageCount($file->getRealPath());
        if (!$pageCount) {
            throw ValidationException::withMessages(['file' => 'This PDF could not be read.']);
        }

        $name = Str::of($file->getClientOriginalName())->beforeLast('.')->limit(250, '')->toString();
        $proposal = $importer->propose($file->getRealPath(), $pageCount, $name);
        $path = $file->storeAs("manual-imports/{$system->id}", Str::uuid() . '.pdf', config('knowledge.disk'));

        $change = ManualChangeRequest::create([
            'system_id' => $system->id,
            'type' => ManualChangeRequest::TYPE_IMPORT,
            'kind' => ManualSection::KIND_UPLOADED_PDF,
            'title' => $name,
            'file_path' => $path,
            'file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'file_size' => $file->getSize(),
            'page_count' => $pageCount,
            'payload' => ['sections' => $proposal['sections'], 'split_source' => $proposal['source']],
            'proposed_parent_id' => $parentId,
            'proposed_after_id' => $afterId,
            'change_summary' => $request->input('change_summary'),
            'status' => ManualChangeRequest::STATUS_DRAFT,
            'author_id' => $request->user()->id,
        ]);

        return (new ManualChangeResource($change->load('system:id,name,slug')))->response()->setStatusCode(201);
    }

    /**
     * The author's changes to an import before submitting it.
     * PDF: titles, levels and page ranges. Word: titles, and which sections to keep
     * (leaving one out leaves out its sub-sections too).
     */
    public function updateImportSections(Request $request, ManualChangeRequest $change, PdfImportService $importer): ManualChangeResource
    {
        $this->authorizeAuthor($request, $change);
        $this->ensureEditable($change);
        abort_unless($change->type === ManualChangeRequest::TYPE_IMPORT, 422, 'This change is not an import.');

        if (!$change->isPdfImport()) {
            return $this->updateWordImportSections($request, $change);
        }

        $request->validate([
            'sections' => ['required', 'array', 'max:' . PdfImportService::MAX_SECTIONS],
            'sections.*.title' => ['nullable', 'string', 'max:250'],
            'sections.*.depth' => ['required', 'integer', 'min:0', 'max:' . PdfImportService::MAX_DEPTH],
            'sections.*.page_from' => ['nullable', 'integer'],
            'sections.*.page_to' => ['nullable', 'integer'],
        ]);

        $change->update([
            'payload' => array_merge($change->payload ?? [], [
                'sections' => $importer->validated($request->input('sections'), (int) $change->page_count),
            ]),
        ]);

        return new ManualChangeResource($change->fresh()->load('system:id,name,slug'));
    }

    private function updateWordImportSections(Request $request, ManualChangeRequest $change): ManualChangeResource
    {
        $current = $change->importSections();

        $request->validate([
            'sections' => ['required', 'array', 'size:' . count($current)],
            'sections.*.title' => ['required', 'string', 'max:250'],
            'sections.*.keep' => ['required', 'boolean'],
        ]);

        $kept = [];
        $skippedDepth = null;
        foreach (array_values($request->input('sections')) as $i => $edit) {
            $depth = (int) $current[$i]['depth'];
            if ($skippedDepth !== null && $depth > $skippedDepth) {
                continue;
            }
            $skippedDepth = null;
            if (!$edit['keep']) {
                $skippedDepth = $depth;
                continue;
            }
            $kept[] = array_merge($current[$i], ['title' => trim($edit['title'])]);
        }

        if ($kept === []) {
            throw ValidationException::withMessages(['sections' => 'Keep at least one section.']);
        }

        // If a chapter was left out, the first kept section may be a sub-section: lift it up.
        $previous = -1;
        foreach ($kept as $i => $item) {
            $kept[$i]['depth'] = $previous = min((int) $item['depth'], $previous + 1);
        }

        $change->update(['payload' => array_merge($change->payload ?? [], ['sections' => $kept])]);

        return new ManualChangeResource($change->fresh()->load('system:id,name,slug'));
    }

    public function submit(Request $request, ManualChangeRequest $change): ManualChangeResource
    {
        $this->authorizeAuthor($request, $change);

        $change = $this->manuals->submit($change, $request->user());

        return new ManualChangeResource($change->load(['system:id,name,slug', 'section', 'reviewer:id,name']));
    }

    public function destroy(Request $request, ManualChangeRequest $change): JsonResponse
    {
        $this->authorizeAuthor($request, $change);
        $this->ensureEditable($change);

        $change->delete();

        return response()->json(null, 204);
    }

    /**
     * Track writing time for this change through the normal plan + timer flow.
     */
    public function trackTime(Request $request, ManualChangeRequest $change): JsonResponse
    {
        $this->authorizeAuthor($request, $change);

        $plan = $this->manuals->trackWriting($change, $request->user());

        return response()->json(['data' => new DailyPlanResource($plan)]);
    }

    private function authorizeAuthor(Request $request, ManualChangeRequest $change): void
    {
        abort_unless($change->author_id === $request->user()->id, 403);
    }

    private function ensureEditable(ManualChangeRequest $change): void
    {
        if (!$change->isEditable()) {
            throw ValidationException::withMessages(['status' => 'This change can no longer be edited.']);
        }
    }

    /**
     * Parent must be a published section of this system; "after" must be one of the parent's children.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function validatedPosition(System $system, ?int $parentId, ?int $afterId): array
    {
        if ($parentId) {
            $exists = ManualSection::query()->where('system_id', $system->id)->published()->whereKey($parentId)->exists();
            if (!$exists) {
                throw ValidationException::withMessages(['proposed_parent_id' => 'Choose a section from this manual.']);
            }
        }

        if ($afterId) {
            $exists = ManualSection::query()
                ->where('system_id', $system->id)
                ->where('parent_id', $parentId)
                ->whereKey($afterId)
                ->exists();
            if (!$exists) {
                throw ValidationException::withMessages(['proposed_after_id' => 'Choose a section at the same level.']);
            }
        }

        return [$parentId ?: null, $afterId ?: null];
    }

    private function validatePageRange(?int $from, ?int $to, ?int $pageCount): void
    {
        if ($from && $to && $from > $to) {
            throw ValidationException::withMessages(['page_from' => 'The first page must come before the last page.']);
        }

        if ($pageCount && (($from && $from > $pageCount) || ($to && $to > $pageCount))) {
            throw ValidationException::withMessages(['page_to' => "This PDF has {$pageCount} pages."]);
        }
    }
}
