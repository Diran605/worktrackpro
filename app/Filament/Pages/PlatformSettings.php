<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Support\ModuleFields;
use App\Models\Organisation;
use App\Services\PlatformService;
use App\Support\Modules;
use App\Support\Platform;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Settings for the whole installation (super admin): single or multiple companies, the main
 * organisation, and the main organisation's modules.
 */
class PlatformSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static \UnitEnum|string|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = -10;

    protected static ?string $title = 'Platform Settings';

    protected string $view = 'filament.pages.platform-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function mount(): void
    {
        $main = Platform::mainOrganisation();

        $this->form->fill([
            'company_mode' => Platform::mode(),
            'main_organisation_id' => $main?->id,
            'modules' => $main ? Modules::enabledFor($main) : Modules::keys(),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('Companies')
                    ->description('Whether this installation serves one business or several. Each business\'s data is always kept separate, so you can switch at any time; only what people see changes.')
                    ->schema([
                        Radio::make('company_mode')
                            ->label('Company mode')
                            ->options([
                                Platform::SINGLE => 'Single company',
                                Platform::MULTI => 'Multiple companies',
                            ])
                            ->descriptions([
                                Platform::SINGLE => 'This installation is for one business: the main organisation below. The Organisations menu, "Organisation" columns and /org/… library addresses are hidden, and other organisations are made inactive (not deleted).',
                                Platform::MULTI => 'Host several separate businesses. Add them under Organisations and choose each one\'s modules there.',
                            ])
                            ->required()
                            ->live(),
                        Select::make('main_organisation_id')
                            ->label('Main organisation')
                            ->options(fn () => Organisation::query()->orderBy('name')->get()
                                ->mapWithKeys(fn (Organisation $o) => [$o->id => $o->name . ($o->is_active ? '' : ' (inactive)')]))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) => $set('modules', $state ? Modules::enabledFor((int) $state) : []))
                            ->helperText('In single company mode, the business this installation is for. In both modes, its documentation library is the one shown at the main address (/).'),
                        TextEntry::make('deactivated')
                            ->label('Will be made inactive')
                            ->visible(fn ($get) => $get('company_mode') === Platform::SINGLE && $this->othersActive($get('main_organisation_id'))->isNotEmpty())
                            ->state(fn ($get) => $this->othersActive($get('main_organisation_id'))->pluck('name')->join(', '))
                            ->color('warning')
                            ->helperText('Their people can no longer sign in until you switch back to multiple companies and make them active again. Nothing is deleted.'),
                        TextEntry::make('company_details')
                            ->label('Company details')
                            ->state('Name, logo, colours, timezone and documentation web address')
                            ->url(fn ($get) => $get('main_organisation_id') ? OrganisationResource::getUrl('edit', ['record' => $get('main_organisation_id')]) : null)
                            ->color('primary')
                            ->visible(fn ($get) => filled($get('main_organisation_id'))),
                    ]),

                ModuleFields::section('Modules the main organisation has. Switched-off modules are invisible to its people; their data is kept, so switching back on restores it. In multiple companies mode, each organisation\'s modules are also set under Organisations.'),
            ]);
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label('Save')
            ->requiresConfirmation(fn () => $this->willDeactivate()->isNotEmpty())
            ->modalHeading('Switch to single company mode?')
            ->modalDescription(fn () => 'These organisations will be made inactive: ' . $this->willDeactivate()->pluck('name')->join(', ')
                . '. Their data is kept. You can switch back to multiple companies at any time.')
            ->action(fn () => $this->save());
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $main = Organisation::query()->findOrFail($data['main_organisation_id']);

        app(PlatformService::class)->apply($data['company_mode'], $main, $data['modules'] ?? []);

        Notification::make()
            ->title('Platform settings saved')
            ->body($data['company_mode'] === Platform::SINGLE
                ? "Single company mode: this installation is for {$main->name}."
                : 'Multiple companies mode.')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }

    private function willDeactivate()
    {
        return ($this->data['company_mode'] ?? null) === Platform::SINGLE
            ? $this->othersActive($this->data['main_organisation_id'] ?? null)
            : collect();
    }

    private function othersActive($mainId)
    {
        $main = $mainId ? Organisation::query()->find($mainId) : null;

        return $main ? app(PlatformService::class)->othersActive($main) : collect();
    }
}
