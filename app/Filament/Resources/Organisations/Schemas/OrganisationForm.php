<?php

namespace App\Filament\Resources\Organisations\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OrganisationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organisation Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        \Filament\Forms\Components\Select::make('timezone')
                            ->label('Timezone')
                            ->options(fn () => \App\Support\LocalTime::options())
                            ->searchable()
                            ->default(config('app.timezone'))
                            ->required()
                            ->helperText('Decides what "today" means for clock-in, daily plans, recurring tasks and the session close time.'),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ]),

                Section::make('Documentation library address')
                    ->description('Where this organisation\'s public documentation library is served.')
                    ->schema([
                        TextInput::make('docs_domain')
                            ->label('Own web address (optional)')
                            ->placeholder('docs.example.com')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->regex('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i')
                            ->validationMessages(['regex' => 'Enter a domain name only, like docs.example.com — no https:// and no slashes.'])
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? strtolower(trim($state)) : null)
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
                                if ($value && strcasecmp($value, (string) $appHost) === 0) {
                                    $fail('This is the main address of WorkTrack Pro. Use a separate domain or subdomain.');
                                }
                            })
                            ->helperText(fn ($record) => 'Point this domain (a DNS CNAME or A record) at this server first, then enter it here. '
                                . 'The library is always available at ' . url('/org/' . ($record?->slug ?? '{slug}'))
                                . ', and the default organisation\'s library is also at ' . url('/') . '.'),
                    ]),

                \App\Filament\Support\ModuleFields::section(),

                Section::make('Branding')
                    ->description('Customize colors and assets for this organisation')
                    ->schema([
                        ColorPicker::make('primary_color')
                            ->label('Primary Color')
                            ->default('#0d9488')
                            ->helperText('Used throughout the frontend and reports'),
                        ColorPicker::make('secondary_color')
                            ->label('Secondary Color')
                            ->default('#6366f1'),
                        FileUpload::make('logo')
                            ->label('Organisation Logo')
                            ->image()
                            ->directory('org-logos')
                            ->maxSize(2048)
                            ->helperText('Appears in the sidebar and reports. Max 2MB.'),
                        FileUpload::make('letterhead')
                            ->label('Report Letterhead')
                            ->image()
                            ->directory('org-letterheads')
                            ->maxSize(5120)
                            ->helperText('Used as the header in exported PDF reports. Max 5MB.'),
                    ]),
            ]);
    }
}
