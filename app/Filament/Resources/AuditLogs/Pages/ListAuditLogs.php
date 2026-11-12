<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    public function getSubheading(): ?string
    {
        return 'Every change to key records: who made it, when, and what it was before and after. Entries cannot be edited or deleted.';
    }

    // Read-only: no "New audit log" button.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
