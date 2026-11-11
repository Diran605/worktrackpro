<?php

namespace App\Filament\Resources\Inbox\Pages;

use App\Filament\Resources\Inbox\MessageResource;
use Filament\Resources\Pages\ListRecords;

class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Compose Message')
                ->createAnother(false)
                ->modalHeading('Compose a new message')
                ->modalSubmitActionLabel('Send')
                ->form([
                    \Filament\Forms\Components\Select::make('recipient_ids')
                        ->label('Recipients')
                        ->multiple()
                        ->options(function () {
                            $user = auth()->user();
                            if ($user->hasRole('super_admin')) {
                                return \App\Models\User::pluck('name', 'id');
                            }
                            return \App\Models\User::where('organisation_id', $user->organisation_id)->pluck('name', 'id');
                        })
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('subject')
                        ->label('Subject')
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('body')
                        ->label('Message Body')
                        ->rows(10)
                        ->required(),
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $data['sender_id'] = auth()->id();
                    $data['organisation_id'] = auth()->user()->organisation_id;
                    $data['message_type'] = 'standard';
                    return $data;
                })
                ->after(function (\App\Models\Message $record, array $data) {
                    if (!empty($data['recipient_ids'])) {
                        foreach ($data['recipient_ids'] as $recipientId) {
                            \App\Models\MessageRecipient::create([
                                'message_id' => $record->id,
                                'recipient_id' => $recipientId,
                            ]);
                        }
                    }
                })
        ];
    }
}

