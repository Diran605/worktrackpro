<?php

namespace App\Filament\Resources\CompetencyReviews\Pages;

use App\Filament\Resources\CompetencyReviews\CompetencyReviewResource;
use App\Models\CompetencySubmission;
use App\Services\CompetencyService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCompetencyReview extends ViewRecord
{
    protected static string $resource = CompetencyReviewResource::class;

    protected function getHeaderActions(): array
    {
        $criteria = $this->record->check->criteriaList();
        $options = collect($criteria)->mapWithKeys(fn ($c, $i) => [$i => $c])->all();

        return [
            Action::make('pass')
                ->label('Pass')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->canReviewRecord())
                ->modalHeading('Pass this answer')
                ->modalDescription('Tick every item the answer meets. All of them must be met to pass.')
                ->schema([
                    CheckboxList::make('criteria')
                        ->label('The answer…')
                        ->options($options)
                        ->bulkToggleable()
                        ->required()
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) use ($criteria) {
                            if (count(array_unique((array) $value)) < count($criteria)) {
                                $fail('Every item must be met to pass. Use "Send back" if something is missing.');
                            }
                        }),
                    Textarea::make('comment')
                        ->label('Comment (optional)')
                        ->rows(3)
                        ->maxLength(5000),
                ])
                ->action(function (array $data) {
                    app(CompetencyService::class)->approve($this->record, auth()->user(), $data['criteria'] ?? [], $data['comment'] ?? null);

                    Notification::make()->title('Passed')->body('They have been told.')->success()->send();
                    $this->redirect(CompetencyReviewResource::getUrl('index'));
                }),

            Action::make('sendBack')
                ->label('Send back')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $this->canReviewRecord())
                ->modalDescription('They can update the answer and submit it again.')
                ->schema([
                    CheckboxList::make('criteria')
                        ->label('What the answer already meets')
                        ->options($options),
                    Textarea::make('comment')
                        ->label('What is missing or wrong?')
                        ->required()
                        ->rows(4)
                        ->maxLength(5000),
                ])
                ->action(function (array $data) {
                    app(CompetencyService::class)->sendBack($this->record, auth()->user(), $data['criteria'] ?? [], $data['comment']);

                    Notification::make()->title('Sent back')->body('They have been told what to change.')->success()->send();
                    $this->redirect(CompetencyReviewResource::getUrl('index'));
                }),
        ];
    }

    private function canReviewRecord(): bool
    {
        $user = auth()->user();

        return $this->record->status === CompetencySubmission::STATUS_SUBMITTED
            && $this->record->user_id !== $user->id
            && $this->record->check->system->canReviewCompetencyBy($user);
    }
}
