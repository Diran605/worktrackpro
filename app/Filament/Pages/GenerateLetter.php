<?php

namespace App\Filament\Pages;

use App\Models\LetterTemplate;
use App\Models\User;
use App\Services\LetterService;
use App\Support\LetterPlaceholders;
use App\Support\Permissions;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class GenerateLetter extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected string $view = 'filament.pages.generate-letter';

    protected static \UnitEnum|string|null $navigationGroup = 'Letters';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return \App\Support\Modules::allows(\App\Support\Modules::LETTERS) && (auth()->user()?->can(Permissions::MANAGE_LETTERS) ?? false);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        $issuer = auth()->user();

        // Load the template text with the placeholders filled in for the chosen employee.
        $loadTemplate = function (callable $get, callable $set) use ($issuer) {
            $template = $this->templateQuery()->find($get('letter_template_id'));
            if (!$template) {
                return;
            }

            $worker = $this->workerQuery()->with('department')->find($get('worker_id'));

            $set('subject', $worker ? LetterPlaceholders::fill($template->subject_template, $worker, $issuer) : $template->subject_template);
            $set('body', $worker ? LetterPlaceholders::fill($template->body_template, $worker, $issuer, html: true) : $template->body_template);
        };

        return $form
            ->schema([
                Section::make('Step 1: Who and which template')
                    ->description('Choose the employee and a starting template. The template text is loaded into Step 2 with the employee\'s details filled in. Changing either choice reloads the text.')
                    ->aside()
                    ->schema([
                        Select::make('worker_id')
                            ->label('Employee')
                            ->options(fn () => $this->workerQuery()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated($loadTemplate),
                        Select::make('letter_template_id')
                            ->label('Template')
                            ->options(fn () => $this->templateQuery()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated($loadTemplate)
                            ->helperText('Manage templates under Letters → Letter Templates.'),
                    ]),

                Section::make('Step 2: Check and edit the letter')
                    ->description('Edit freely. Replace any remaining markers such as [SALARY] or {{custom_field_1}}; the letter cannot be sent while a {{…}} placeholder is left in it.')
                    ->aside()
                    ->schema([
                        TextInput::make('subject')
                            ->label('Subject')
                            ->required()
                            ->maxLength(255),
                        RichEditor::make('body')
                            ->label('Letter')
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(LetterService $letterService): void
    {
        $state = $this->form->getState();

        $leftovers = LetterPlaceholders::unfilled($state['subject'] . ' ' . $state['body']);
        if ($leftovers !== []) {
            throw ValidationException::withMessages([
                'data.body' => 'Replace these placeholders before sending: ' . implode(', ', $leftovers),
            ]);
        }

        $template = $this->templateQuery()->findOrFail($state['letter_template_id']);
        $worker = $this->workerQuery()->with('department')->findOrFail($state['worker_id']);

        $letterService->generateAndDeliver($template, $worker, auth()->user(), $state['subject'], $state['body']);

        Notification::make()
            ->title('Letter generated')
            ->body("It has been delivered to {$worker->name}'s inbox. You can download it from Issued Letters.")
            ->success()
            ->send();

        $this->form->fill();
    }

    /** Letters go to active employees of your own organisation (the letterhead is per organisation). */
    private function workerQuery(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->where('organisation_id', auth()->user()->organisation_id);
    }

    /** System templates plus your organisation's own. */
    private function templateQuery(): Builder
    {
        return LetterTemplate::query()->where(fn (Builder $query) => $query
            ->whereNull('organisation_id')
            ->orWhere('organisation_id', auth()->user()->organisation_id));
    }
}
