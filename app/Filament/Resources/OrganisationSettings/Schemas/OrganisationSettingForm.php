<?php

namespace App\Filament\Resources\OrganisationSettings\Schemas;

use App\Support\LocalTime;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganisationSettingForm
{
    /** File types offered for inbox attachments: MIME type => label. */
    public const FILE_TYPES = [
        'application/pdf' => 'PDF (.pdf)',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word (.docx)',
        'application/msword' => 'Word 97–2003 (.doc)',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel (.xlsx)',
        'application/vnd.ms-excel' => 'Excel 97–2003 (.xls)',
        'text/csv' => 'CSV (.csv)',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint (.pptx)',
        'image/png' => 'PNG image (.png)',
        'image/jpeg' => 'JPEG image (.jpg, .jpeg)',
        'image/gif' => 'GIF image (.gif)',
        'image/webp' => 'WebP image (.webp)',
        'text/plain' => 'Text (.txt)',
        'application/zip' => 'ZIP archive (.zip)',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // Saved on the organisation itself (organisations.timezone).
                Section::make('Time zone')
                    ->description('Where your organisation works. This decides what "today" means across the app.')
                    ->relationship('organisation')
                    ->schema([
                        Select::make('timezone')
                            ->label('Organisation time zone')
                            ->options(fn () => LocalTime::options())
                            ->searchable()
                            ->required()
                            ->helperText('Used for the clock-in date, the date of daily plans, when recurring tasks are created (just after midnight), the session close time below, and times shown in reports. Pick the city in your own time zone, e.g. "Africa/Lagos (UTC+01:00)" or "Europe/London (UTC+00:00)". Type to search.'),
                    ]),

                Section::make('Attendance & timers')
                    ->description('Safety nets for when people forget to clock out or stop a timer.')
                    ->columns(2)
                    ->schema([
                        TimePicker::make('abandoned_session_close_time')
                            ->label('Close forgotten sessions at')
                            ->seconds(false)
                            ->format('H:i')
                            ->required()
                            ->helperText('Anyone still clocked in at this time (in the time zone above) is clocked out automatically. Their session ends at their last recorded activity, not at this time, and they can ask an admin to reopen it. Example: 20:00.'),
                        TextInput::make('abandoned_timer_hours')
                            ->label('Stop timers running longer than')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(24)
                            ->suffix('hours')
                            ->required()
                            ->helperText('A task timer left running longer than this is stopped. Only this many hours are logged, and the entry is marked "unverified" so you can check it. The worker and their admin get an inbox message. Example: 3.'),
                    ]),

                Section::make('Daily plans')
                    ->schema([
                        TextInput::make('carry_over_flag_threshold')
                            ->label('Flag a task after it has been carried over')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(30)
                            ->suffix('times')
                            ->required()
                            ->helperText('When an unfinished task is carried over to another day this many times, it is highlighted in red in Task Plans (where you can filter for it) and on the worker\'s plan. A task that keeps getting pushed back usually needs help or a re-plan. Example: 3.'),
                    ]),

                Section::make('Inbox attachments')
                    ->description('Limits for files people attach to inbox messages.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('inbox_max_attachment_kb')
                            ->label('Largest file allowed')
                            ->numeric()
                            ->integer()
                            ->minValue(256)
                            ->maxValue(51200)
                            ->suffix('KB')
                            ->required()
                            ->helperText('1024 KB = 1 MB. Example: 5120 allows files up to 5 MB. Allowed range: 256 KB to 51200 KB (50 MB).'),
                        CheckboxList::make('inbox_allowed_mime_types')
                            ->label('Allowed file types')
                            ->options(self::FILE_TYPES)
                            ->columns(2)
                            ->bulkToggleable()
                            ->helperText('Tick the kinds of files people may attach. If nothing is ticked, any file type is accepted, which is not recommended.'),
                    ]),

                // Saved on the organisation itself (organisations.docs_*).
                Section::make('Documentation library')
                    ->description('How your public documentation library looks. Choose which manuals appear in it under Knowledge > Systems.')
                    ->relationship('organisation')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('docs_address')
                            ->label('Library address')
                            ->state(fn ($record) => $record ? \App\Support\DocsContext::for($record)->home() : null)
                            ->url(fn ($state) => $state, shouldOpenInNewTab: true)
                            ->helperText('Share this link with clients and staff. A super admin can give the library its own web address (for example docs.yourcompany.com) under Organisations.'),
                        TextInput::make('docs_title')
                            ->label('Library title')
                            ->maxLength(120)
                            ->placeholder(fn ($record) => $record ? $record->name . ' Documentation' : null)
                            ->helperText('The big heading on the library home page and in browser tabs. Leave empty to use "<organisation name> Documentation".'),
                        TextInput::make('docs_tagline')
                            ->label('Tagline')
                            ->maxLength(255)
                            ->placeholder('Guides and manuals for our systems')
                            ->helperText('One line under the title, also used as the description search engines show.'),
                        Repeater::make('docs_banners')
                            ->label('Home page announcements')
                            ->helperText('Up to three cards shown under the search box, e.g. "New billing manual" or "Planned maintenance on Saturday".')
                            ->maxItems(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add announcement')
                            ->collapsible()
                            ->itemLabel(fn (array $state) => $state['title'] ?? null)
                            ->columns(2)
                            ->schema([
                                TextInput::make('title')->required()->maxLength(80),
                                TextInput::make('link_label')->label('Link text')->maxLength(40)->placeholder('Learn more'),
                                Textarea::make('text')->rows(2)->maxLength(240)->columnSpanFull(),
                                TextInput::make('url')
                                    ->label('Link')
                                    ->maxLength(500)
                                    ->regex('#^(https?://|/)#i')
                                    ->validationMessages(['regex' => 'Use a full web address (https://…) or a path on this site starting with /.'])
                                    ->placeholder('https://… or /docs/billing')
                                    ->columnSpanFull(),
                            ]),
                        Repeater::make('docs_footer_links')
                            ->label('Footer links')
                            ->helperText('Links at the bottom of every library page, e.g. your website or support portal.')
                            ->maxItems(8)
                            ->defaultItems(0)
                            ->addActionLabel('Add link')
                            ->columns(2)
                            ->schema([
                                TextInput::make('label')->required()->maxLength(60),
                                TextInput::make('url')->label('Web address')->required()->url()->maxLength(500),
                            ]),
                    ]),
            ]);
    }
}
