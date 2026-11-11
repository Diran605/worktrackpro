<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\ManualSection;
use App\Models\System;
use Illuminate\Http\Request;

trait ResolvesSystem
{
    /**
     * Find a system by slug inside the user's organisation and check it may be viewed.
     */
    protected function resolveSystem(Request $request, string $slug): System
    {
        $system = System::query()
            ->forOrganisation($request->user()->organisation_id)
            ->where('slug', $slug)
            ->firstOrFail();

        $this->authorize('view', $system);

        return $system;
    }

    protected function resolveSection(System $system, int|string $sectionId, bool $publishedOnly = true): ManualSection
    {
        return ManualSection::query()
            ->where('system_id', $system->id)
            ->when($publishedOnly, fn ($q) => $q->published())
            ->with(['currentRevision.author:id,name', 'verifiedBy:id,name'])
            ->findOrFail($sectionId);
    }
}
