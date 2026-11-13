<?php

namespace App\Http\Resources;

use App\Models\ManualChangeRequest;
use App\Support\ManualContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin ManualChangeRequest
 */
class ManualChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detailed = $request->routeIs('api.manual-changes.show', 'api.manual-changes.update', 'api.manual-changes.store', 'api.manual-changes.pdf');

        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->resource->typeLabel(),
            'kind' => $this->kind,
            'title' => $this->title,
            'status' => $this->status,
            'status_label' => ManualChangeRequest::STATUS_LABELS[$this->status] ?? $this->status,
            'editable' => $this->isEditable(),
            'change_summary' => $this->change_summary,
            'review_comment' => $this->review_comment,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'system' => $this->whenLoaded('system', fn () => [
                'id' => $this->system->id,
                'name' => $this->system->name,
                'slug' => $this->system->slug,
            ]),
            'section' => $this->whenLoaded('section', fn () => $this->section ? [
                'id' => $this->section->id,
                'number' => $this->section->number,
                'title' => $this->section->title,
                'current_version' => $this->section->currentVersion(),
            ] : null),
            'base_version' => $this->base_version,
            'proposed_parent_id' => $this->proposed_parent_id,
            'proposed_after_id' => $this->proposed_after_id,
            'body' => $this->when($detailed, fn () => ManualContent::toDisplay($this->body)),
            'file' => $this->file_path ? [
                'name' => $this->file_name,
                'size' => $this->file_size,
                'page_count' => $this->page_count,
                'page_from' => $this->page_from,
                'page_to' => $this->page_to,
                'url' => URL::temporarySignedRoute('kb.change-file', now()->addHours(2), ['change' => $this->id]),
            ] : null,
            'import_sections' => $this->when(
                $this->type === ManualChangeRequest::TYPE_IMPORT,
                fn () => collect($this->importSections())->map(fn ($s) => [
                    'title' => $s['title'],
                    'depth' => $s['depth'],
                    'page_from' => $s['page_from'] ?? null,
                    'page_to' => $s['page_to'] ?? null,
                ])->all()
            ),
            'from_bookmarks' => $this->when($this->type === ManualChangeRequest::TYPE_IMPORT, fn () => (bool) ($this->payload['from_bookmarks'] ?? false)),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
