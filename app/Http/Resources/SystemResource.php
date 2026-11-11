<?php

namespace App\Http\Resources;

use App\Models\SystemMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->when($request->routeIs('api.systems.show'), $this->description),
            'criticality' => $this->criticality,
            'status' => $this->status,
            'url' => $this->url,
            'support_contact' => $this->support_contact,
            'escalation_notes' => $this->escalation_notes,
            'manual_version' => $this->manual_version,
            'department' => $this->whenLoaded('department', fn () => $this->department
                ? ['id' => $this->department->id, 'name' => $this->department->name]
                : null),
            'people' => $this->whenLoaded('members', fn () => $this->members
                ->filter(fn ($m) => $m->user)
                ->map(fn ($m) => [
                    'id' => $m->user->id,
                    'name' => $m->user->name,
                    'email' => $m->user->email,
                    'role' => $m->role,
                    'role_label' => SystemMember::ROLES[$m->role] ?? $m->role,
                ])
                ->values()),
            'sections_count' => $this->whenCounted('manualSections'),
            'can' => [
                'contribute' => $user?->can('contribute', $this->resource) ?? false,
                'approve' => $user?->can('approve', $this->resource) ?? false,
            ],
        ];
    }
}
