<?php

namespace App\Filament\Pages;

use App\Models\LetterTemplate;
use App\Models\User;
use App\Services\LetterService;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;

class GenerateLetter extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected string $view = 'filament.pages.generate-letter';

    protected static \UnitEnum|string|null $navigationGroup = 'Letters';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('manage_letters') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        $user = auth()->user();

        $updateFields = function (callable $get, callable $set) use ($user) {
            $workerId = $get('worker_id');
            $templateId = $get('letter_template_id');

            if (!$templateId) return;

            $template = LetterTemplate::find($templateId);
            if (!$template) return;

            $subject = $template->subject_template;
            $body = $template->body_template;

            if ($workerId) {
                $worker = User::with('department')->find($workerId);
                if ($worker) {
                    $letterhead = \App\Models\CompanyLetterhead::where('organisation_id', $user->organisation_id)->where('is_active', true)->orderByDesc('id')->first();

                    $placeholders = [
                        '{{worker_name}}' => $worker->name,
                        '{{worker_department}}' => $worker->department?->name ?? '',
                        '{{date}}' => now()->toDateString(),
                        '{{admin_name}}' => $user->name,
                        '{{company_name}}' => $letterhead?->company_name ?? ($user->organisation?->name ?? ''),
                    ];

                    $subject = strtr($subject, $placeholders);
                    $body = strtr($body, $placeholders);
                }
            }
            $set('subject', $subject);
            $set('body', $body);
        };

        return $form
            ->schema([
                Section::make('Step 1: Selection')
                    ->description('Select who you are writing to and which template to use.')
                    ->aside()
                    ->schema([
                        Select::make('worker_id')
                            ->label('Which employee is this for?')
                            ->options(function () use ($user) {
                                $q = User::query()->where('is_active', true);
                                if (!$user->hasRole('super_admin')) {
                                    $q->where('organisation_id', $user->organisation_id);
                                }
                                return $q->orderBy('name')->pluck('name', 'id');
                            })
                            ->searchable()
                            ->loadingMessage('Loading employees...')
                            ->required()
                            ->live()
                            ->afterStateUpdated($updateFields),
                        Select::make('letter_template_id')
                            ->label('Starting Template')
                            ->options(function () use ($user) {
                                $q = LetterTemplate::query();
                                if ($user->hasRole('super_admin')) {
                                    return $q->orderBy('name')->pluck('name', 'id');
                                }
                                return $q->whereNull('organisation_id')
                                    ->orWhere('organisation_id', $user->organisation_id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated($updateFields),
                    ]),

                Section::make('Step 2: Edit Content')
                    ->description('The template text has been loaded below. Edit it freely before generating the PDF.')
                    ->aside()
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('subject')
                            ->label('Letter Subject')
                            ->required(),
                        \Filament\Forms\Components\RichEditor::make('body')
                            ->label('Letter Body')
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(LetterService $letterService): void
    {
        $state = $this->form->getState();

        $template = LetterTemplate::findOrFail($state['letter_template_id']);
        $worker = User::with('department')->findOrFail($state['worker_id']);
        $generatedBy = auth()->user();

        $letterService->generateAndDeliver($template, $worker, $generatedBy, $state['subject'], $state['body']);

        \Filament\Notifications\Notification::make()
            ->title('Letter generated and delivered to inbox.')
            ->success()
            ->send();

        $this->form->fill();
    }
}

