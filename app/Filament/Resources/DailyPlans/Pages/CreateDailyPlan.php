<?php

namespace App\Filament\Resources\DailyPlans\Pages;

use App\Filament\Resources\DailyPlans\DailyPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDailyPlan extends CreateRecord
{
        
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if ($user && $user->organisation_id && !isset($data['organisation_id'])) {
            $data['organisation_id'] = $user->organisation_id;
        }
        return $data;
    }
protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

protected static string $resource = DailyPlanResource::class;

    protected static bool $canCreateAnother = false;

    protected function afterCreate(): void
    {
        $plan = $this->record;
        
        if ($plan->user_id !== auth()->id()) {
            $plan->user->notify(new \App\Notifications\DailyPlanAssigned($plan));
        }
    }
}


