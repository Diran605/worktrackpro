<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\GenerateLetter;
use App\Filament\Resources\Letters\IssuedLetterResource;
use App\Filament\Resources\Letters\LetterTemplateResource;
use App\Models\GeneratedLetter;
use App\Models\LetterTemplate;
use App\Support\Modules;
use App\Support\Permissions;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * HR Letters module: letters issued.
 */
class LettersOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 40;

    protected ?string $heading = 'HR letters';

    public static function canView(): bool
    {
        return Modules::allows(Modules::LETTERS) && (auth()->user()?->can(Permissions::MANAGE_LETTERS) ?? false);
    }

    protected function getStats(): array
    {
        $organisationId = auth()->user()->organisation_id;
        $letters = GeneratedLetter::query()->where('organisation_id', $organisationId);
        $latest = (clone $letters)->with('worker:id,name')->latest('id')->first();

        return [
            Stat::make('Issued this month', (clone $letters)->where('created_at', '>=', now()->startOfMonth())->count())
                ->description($latest ? 'Latest: ' . str($latest->subject)->limit(30) . ($latest->worker ? ' → ' . $latest->worker->name : '') : 'None issued yet')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->url(IssuedLetterResource::canAccess() ? IssuedLetterResource::getUrl('index') : null),
            Stat::make('Issued in total', (clone $letters)->count())
                ->description('All time')
                ->icon('heroicon-o-archive-box')
                ->color('gray'),
            Stat::make('Templates', LetterTemplate::query()->where('organisation_id', $organisationId)->count())
                ->description('Write a new letter')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->url(GenerateLetter::canAccess() ? GenerateLetter::getUrl() : (LetterTemplateResource::canAccess() ? LetterTemplateResource::getUrl('index') : null)),
        ];
    }
}
