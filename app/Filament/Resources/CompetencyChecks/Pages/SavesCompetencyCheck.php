<?php

namespace App\Filament\Resources\CompetencyChecks\Pages;

use App\Filament\Resources\CompetencyChecks\CompetencyCheckResource;
use App\Models\CompetencyCheck;
use App\Services\CompetencyService;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Shared by the create and edit pages: only products the person may write checks for, a tidy
 * "who must pass it", and plan tasks for the people who must pass it.
 */
trait SavesCompetencyCheck
{
    protected function cleanCheckData(array $data): array
    {
        if (!CompetencyCheckResource::productQuery()->whereKey($data['system_id'] ?? 0)->exists()) {
            throw ValidationException::withMessages(['data.system_id' => 'Choose one of your products.']);
        }

        $for = $data['required_for'] ?? [];
        $data['required_for'] = [
            'everyone' => (bool) ($for['everyone'] ?? false),
            'roles' => array_values(array_map('strval', $for['roles'] ?? [])),
            'departments' => array_values(array_map('intval', $for['departments'] ?? [])),
            'users' => array_values(array_map('intval', $for['users'] ?? [])),
        ];
        $data['criteria'] = array_values(array_filter(array_map(
            fn ($c) => trim(is_array($c) ? (string) ($c['criterion'] ?? '') : (string) $c),
            $data['criteria'] ?? []
        ), fn ($c) => $c !== ''));
        $data['section_ids'] = array_values(array_map('intval', $data['section_ids'] ?? []));
        $data['valid_for_days'] = filled($data['valid_for_days'] ?? null) ? (int) $data['valid_for_days'] : null;

        return $data;
    }

    protected function assignPlans(CompetencyCheck $check): void
    {
        $added = app(CompetencyService::class)->assignPlansForCheck($check->fresh('system'));

        if ($added > 0) {
            Notification::make()
                ->title("Added to {$added} " . ($added === 1 ? 'person\'s' : 'people\'s') . ' daily plan')
                ->success()
                ->send();
        }
    }
}
