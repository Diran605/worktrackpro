<?php

namespace App\Filament\Resources\Systems\Schemas;

use App\Models\System;
use App\Models\SystemMember;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SystemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('What it is')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('url')
                            ->label('Link to the product')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('summary')
                            ->label('One-line summary')
                            ->helperText('What does this product do, in one sentence?')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        RichEditor::make('description')
                            ->label('Description')
                            ->toolbarButtons([
                                ['bold', 'italic', 'link'],
                                ['h2', 'h3', 'bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ])
                            ->columnSpanFull(),
                        Select::make('department_id')
                            ->label('Department')
                            ->relationship(
                                name: 'department',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => self::scopeToOrganisation($query),
                            )
                            ->searchable()
                            ->preload(),
                        Select::make('criticality')
                            ->label('Business impact')
                            ->options(System::IMPACT_OPTIONS)
                            ->helperText('How badly the business is affected if this product stops working. Shown as a badge so people know how careful to be with it.')
                            ->default('medium')
                            ->required(),
                        Select::make('status')
                            ->options(System::STATUSES)
                            ->default('active')
                            ->required(),
                    ]),

                Section::make('When it breaks')
                    ->schema([
                        Textarea::make('support_contact')
                            ->label('How to get help')
                            ->placeholder('e.g. #payments-support on Slack, or call the on-call engineer')
                            ->rows(2),
                        Textarea::make('escalation_notes')
                            ->label('Escalation notes')
                            ->placeholder('Who to escalate to, vendor support details, known issues…')
                            ->rows(3),
                    ]),

                Section::make('Documentation library')
                    ->visible(fn () => \App\Support\Modules::allows(\App\Support\Modules::DOCS_LIBRARY))
                    ->description('Whether this manual appears in the public documentation library, and where.')
                    ->columns(2)
                    ->schema([
                        Select::make('library_visibility')
                            ->label('In the library')
                            ->options(System::LIBRARY_VISIBILITIES)
                            ->default(System::LIBRARY_HIDDEN)
                            ->required()
                            ->helperText('Public: anyone can read it. Restricted: listed, but only staff and people with an access code can open it. Not in library: staff read it in the staff portal only.'),
                        Select::make('category_id')
                            ->label('Category')
                            ->relationship(
                                name: 'category',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => self::scopeToOrganisation($query)->orderBy('position')->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Groups products on the library home page, e.g. "Finance" or "Customer service".')
                            ->createOptionForm([
                                TextInput::make('name')->required()->maxLength(100),
                                Textarea::make('description')->rows(2)->maxLength(500),
                            ])
                            ->createOptionUsing(fn (array $data, ?System $record) => \App\Models\SystemCategory::create($data + [
                                'organisation_id' => $record?->organisation_id ?? auth()->user()?->organisation_id,
                            ])->getKey()),
                    ]),

                Section::make('People')
                    ->description('Owners and backup owners approve changes to this product\'s manual.')
                    ->schema([
                        Repeater::make('members')
                            ->relationship()
                            ->hiddenLabel()
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add person')
                            ->schema([
                                Select::make('user_id')
                                    ->label('Person')
                                    ->options(fn () => self::scopeToOrganisation(User::query()->where('is_active', true))
                                        ->orderBy('name')
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                                Select::make('role')
                                    ->options(SystemMember::ROLES)
                                    ->default(SystemMember::ROLE_OWNER)
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    private static function scopeToOrganisation(Builder $query): Builder
    {
        $user = auth()->user();

        return $user?->hasRole('super_admin')
            ? $query
            : $query->where('organisation_id', $user?->organisation_id);
    }
}
