<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesSystem;
use App\Http\Controllers\Controller;
use App\Http\Resources\DailyPlanResource;
use App\Models\ManualAsset;
use App\Models\ManualSection;
use App\Services\ManualService;
use App\Support\ManualContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Reading a system's manual in the portal.
 */
class ManualController extends Controller
{
    use ResolvesSystem;

    public function __construct(private readonly ManualService $manuals)
    {
    }

    /**
     * Table of contents, reading progress and what the user may do.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $system = $this->resolveSystem($request, $slug);
        $progress = $this->manuals->studyProgress($system, $request->user());
        $readIds = array_flip($progress['read_ids']);

        $mapTree = function (array $nodes) use (&$mapTree, $readIds) {
            return array_map(fn (array $node) => [
                'id' => $node['section']->id,
                'number' => $node['section']->number,
                'title' => $node['section']->title,
                'kind' => $node['section']->kind,
                'depth' => $node['section']->depth,
                'read' => isset($readIds[$node['section']->id]),
                'children' => $mapTree($node['children']),
            ], $nodes);
        };

        unset($progress['read_ids']);

        return response()->json([
            'data' => [
                'system' => ['id' => $system->id, 'name' => $system->name, 'slug' => $system->slug],
                'manual_version' => $system->manual_version,
                'tree' => $mapTree($this->manuals->publishedTree($system)),
                'progress' => $progress,
                'can' => [
                    'contribute' => $request->user()->can('contribute', $system),
                    'approve' => $request->user()->can('approve', $system),
                ],
            ],
        ]);
    }

    public function section(Request $request, string $slug, int $sectionId): JsonResponse
    {
        $system = $this->resolveSystem($request, $slug);
        $section = $this->resolveSection($system, $sectionId);
        $revision = $section->currentRevision;

        $outline = $this->manuals->publishedOutline($system)->values();
        $index = $outline->search(fn ($s) => $s->id === $section->id);

        $read = \App\Models\ManualRead::query()
            ->where('user_id', $request->user()->id)
            ->where('manual_section_id', $section->id)
            ->where('version', $section->currentVersion())
            ->exists();

        return response()->json([
            'data' => [
                'id' => $section->id,
                'number' => $section->number,
                'title' => $section->title,
                'kind' => $section->kind,
                'version' => $section->currentVersion(),
                'body' => $section->kind === ManualSection::KIND_WRITTEN ? ManualContent::toDisplay($revision?->body) : null,
                'file' => $section->kind === ManualSection::KIND_UPLOADED_PDF && $revision?->file_path ? [
                    'name' => $revision->file_name,
                    'url' => URL::temporarySignedRoute('kb.revision-file', now()->addHours(2), ['revision' => $revision->id]),
                    'page_from' => $revision->page_from,
                    'page_to' => $revision->page_to,
                    'page_count' => $revision->page_count,
                ] : null,
                'updated_at' => $revision?->published_at?->toIso8601String(),
                'updated_by' => $revision?->author?->name,
                'last_verified_at' => $section->last_verified_at?->toIso8601String(),
                'last_verified_by' => $section->verifiedBy?->name,
                'is_stale' => !$section->last_verified_at
                    || $section->last_verified_at->lt(now()->subDays(config('knowledge.stale_after_days'))),
                'read' => $read,
                'previous_id' => $index > 0 ? $outline[$index - 1]->id : null,
                'next_id' => $index !== false && $index < $outline->count() - 1 ? $outline[$index + 1]->id : null,
            ],
        ]);
    }

    public function markRead(Request $request, string $slug, int $sectionId): JsonResponse
    {
        $system = $this->resolveSystem($request, $slug);
        $section = $this->resolveSection($system, $sectionId);

        $this->manuals->markRead($section, $request->user());

        return response()->json(['data' => $this->progressWithoutIds($system, $request)]);
    }

    /**
     * Search section titles and text within one manual.
     */
    public function search(Request $request, string $slug): JsonResponse
    {
        $system = $this->resolveSystem($request, $slug);
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $results = $this->manuals->publishedOutline($system)
            ->map(function (ManualSection $s) use ($term) {
                $text = html_entity_decode(strip_tags((string) $s->currentRevision?->body));
                $inTitle = Str::contains($s->title, $term, ignoreCase: true);
                $pos = mb_stripos($text, $term);

                if (!$inTitle && $pos === false) {
                    return null;
                }

                return [
                    'id' => $s->id,
                    'number' => $s->number,
                    'title' => $s->title,
                    'excerpt' => $pos !== false ? Str::excerpt($text, $term, ['radius' => 80]) : null,
                    'score' => $inTitle ? 2 : 1,
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->values()
            ->take(25);

        return response()->json(['data' => $results]);
    }

    /**
     * Upload a screenshot or image for use in the editor.
     */
    public function uploadAsset(Request $request, string $slug): JsonResponse
    {
        $system = $this->resolveSystem($request, $slug);
        $this->authorize('contribute', $system);

        $request->validate([
            'file' => ['required', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:' . config('knowledge.max_image_kb')],
        ]);

        $file = $request->file('file');
        $path = $file->store("manual-assets/{$system->id}", config('knowledge.disk'));
        $size = @getimagesize($file->getRealPath()) ?: [null, null];

        $asset = ManualAsset::create([
            'system_id' => $system->id,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $size[0],
            'height' => $size[1],
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json(['data' => ['id' => $asset->id, 'url' => $asset->signedUrl()]], 201);
    }

    /**
     * Start (or resume) a "Study: <system> manual" plan timer.
     */
    public function trackStudy(Request $request, string $slug): JsonResponse
    {
        $system = $this->resolveSystem($request, $slug);
        $plan = $this->manuals->trackStudy($system, $request->user());

        return response()->json(['data' => new DailyPlanResource($plan)]);
    }

    private function progressWithoutIds($system, Request $request): array
    {
        $progress = $this->manuals->studyProgress($system, $request->user());
        unset($progress['read_ids']);

        return $progress;
    }
}
