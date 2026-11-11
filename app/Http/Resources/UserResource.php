<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'organisation_id' => $this->organisation_id,
            'department_id' => $this->department_id,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'organisation' => $this->whenLoaded('organisation', function() {
                return [
                    'id' => $this->organisation->id,
                    'name' => $this->organisation->name,
                    'slug' => $this->organisation->slug,
                    'primary_color' => $this->organisation->primary_color,
                    'secondary_color' => $this->organisation->secondary_color,
                    'logo' => $this->organisation->logo ? asset('storage/' . $this->organisation->logo) : null,
                ];
            }),
            'department' => $this->whenLoaded('department', function() {
                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                ];
            }),
            'roles' => $this->whenLoaded('roles', function() {
                return $this->roles->pluck('name');
            }),
            'permissions' => $this->whenLoaded('permissions', function() {
                return $this->getAllPermissions()->pluck('name');
            }),
        ];
    }
}
