<?php

namespace App\Filament\Support;

use App\Support\Modules;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;

/**
 * The "Modules" checklist used on the Organisations form and on Platform Settings.
 * The state is a list of module keys under `modules`; the page saves it with Modules::setFor().
 */
final class ModuleFields
{
    public static function section(string $description = 'What this organisation has bought. Switched-off modules are invisible to its people; their data is kept, so switching back on restores it.'): Section
    {
        return Section::make('Modules')
            ->description($description)
            ->headerActions(collect(Modules::PRESETS)->map(
                fn (array $preset, string $key) => Action::make("preset_{$key}")
                    ->label($preset['label'])
                    ->link()
                    ->action(fn ($set) => $set('modules', $preset['modules']))
            )->values()->all())
            ->schema([
                CheckboxList::make('modules')
                    ->hiddenLabel()
                    ->options(collect(Modules::ALL)->mapWithKeys(fn ($m, $key) => [$key => $m['label']])->all())
                    ->descriptions(collect(Modules::ALL)->mapWithKeys(fn ($m, $key) => [$key => $m['description']
                        . ($m['requires'] ? ' Needs ' . collect($m['requires'])->map(fn ($r) => Modules::label($r))->join(', ') . '.' : '')])->all())
                    ->columns(2)
                    ->live()
                    // Ticking a module also ticks what it needs (e.g. the library needs the knowledge base).
                    ->afterStateUpdated(fn ($state, $set) => $set('modules', Modules::withRequirements((array) $state)))
                    ->helperText('Core features (users, departments, roles, settings, inbox, audit log) are always included.'),
            ]);
    }
}
