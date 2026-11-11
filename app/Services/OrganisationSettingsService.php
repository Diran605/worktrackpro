<?php

namespace App\Services;

use App\Models\OrganisationSetting;
use Illuminate\Support\Facades\Cache;

class OrganisationSettingsService
{
    public function forOrganisation(int $organisationId): OrganisationSetting
    {
        $attributes = Cache::remember(
            "org_settings:{$organisationId}",
            now()->addMinutes(10),
            fn () => OrganisationSetting::firstOrCreate(
                ['organisation_id' => $organisationId],
                [
                    'abandoned_timer_hours' => 3,
                    'carry_over_flag_threshold' => 3,
                    'inbox_max_attachment_kb' => 5120,
                    'inbox_allowed_mime_types' => null,
                    'abandoned_session_close_time' => '20:00',
                ]
            )->toArray()
        );

        return (new OrganisationSetting())->forceFill($attributes)->syncOriginal();
    }

    public function clearCache(int $organisationId): void
    {
        Cache::forget("org_settings:{$organisationId}");
    }
}

