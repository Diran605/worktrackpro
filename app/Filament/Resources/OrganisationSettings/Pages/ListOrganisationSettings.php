<?php

namespace App\Filament\Resources\OrganisationSettings\Pages;

use App\Filament\Resources\OrganisationSettings\OrganisationSettingResource;
use App\Models\Organisation;
use App\Models\OrganisationSetting;
use App\Services\OrganisationSettingsService;
use Filament\Resources\Pages\ListRecords;

/**
 * Each organisation has exactly one settings record, created with defaults when first needed.
 * Organisation admins go straight to theirs; super admins see one row per organisation.
 */
class ListOrganisationSettings extends ListRecords
{
    protected static string $resource = OrganisationSettingResource::class;

    public function mount(): void
    {
        $user = auth()->user();
        $service = app(OrganisationSettingsService::class);

        if (!$user->hasRole('super_admin')) {
            $service->forOrganisation((int) $user->organisation_id); // makes sure the record exists

            $record = OrganisationSetting::where('organisation_id', $user->organisation_id)->firstOrFail();
            $this->redirect(OrganisationSettingResource::getUrl('edit', ['record' => $record]));

            return;
        }

        // Super admin: every organisation gets a row to edit.
        Organisation::query()
            ->whereNotIn('id', OrganisationSetting::query()->select('organisation_id'))
            ->pluck('id')
            ->each(fn ($id) => $service->forOrganisation((int) $id));

        parent::mount();
    }

    public function getSubheading(): ?string
    {
        return 'One settings record per organisation. Click Edit to change an organisation\'s settings.';
    }
}
