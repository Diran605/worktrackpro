<?php

namespace App\Filament\Resources\Letters\Pages;

use App\Filament\Resources\Letters\IssuedLetterResource;
use Filament\Resources\Pages\ListRecords;

class ListIssuedLetters extends ListRecords
{
    protected static string $resource = IssuedLetterResource::class;
}

