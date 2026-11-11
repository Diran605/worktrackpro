<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SystemResource;
use App\Models\System;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SystemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', System::class);

        $systems = System::query()
            ->forOrganisation($request->user()->organisation_id)
            ->where('status', '!=', 'retired')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->string('search') . '%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('summary', 'like', $term));
            })
            ->with(['department', 'members.user'])
            ->withCount(['manualSections' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('name')
            ->get();

        return SystemResource::collection($systems);
    }

    public function show(Request $request, string $slug): SystemResource
    {
        $system = System::query()
            ->forOrganisation($request->user()->organisation_id)
            ->where('slug', $slug)
            ->with(['department', 'members.user'])
            ->firstOrFail();

        $this->authorize('view', $system);

        return new SystemResource($system);
    }
}
