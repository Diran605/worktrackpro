<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use App\Support\LocalTime;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,name'))
            // Newest first; entries in the same second keep the order they were written.
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('j M Y H:i:s')
                    ->timezone(fn () => LocalTime::timezoneFor(auth()->user()?->organisation_id))
                    ->description(fn (AuditLog $record) => $record->created_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System')
                    ->tooltip(fn (AuditLog $record) => $record->user_id ? null : 'No one was signed in: done by setup, a scheduled job or the system itself.')
                    ->searchable(),
                TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('auditable_type')
                    ->label('Record')
                    ->formatStateUsing(fn ($state, AuditLog $record) => self::typeLabel($state) . ' #' . $record->auditable_id)
                    ->description(fn (AuditLog $record) => self::recordName($record))
                    ->searchable(),
                TextColumn::make('changes')
                    ->label('What changed')
                    ->state(fn (AuditLog $record) => self::changedFields($record))
                    ->wrap()
                    ->limit(80)
                    ->placeholder('—'),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('organisation.name')
                    ->label('Organisation')
                    ->visible(fn () => auth()->user()?->hasRole('super_admin'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')->options([
                    'created' => 'Created',
                    'updated' => 'Updated',
                    'deleted' => 'Deleted',
                ]),
                SelectFilter::make('auditable_type')
                    ->label('Record type')
                    ->options(fn () => AuditLog::query()
                        ->distinct()
                        ->orderBy('auditable_type')
                        ->pluck('auditable_type')
                        ->mapWithKeys(fn ($type) => [$type => self::typeLabel($type)])
                        ->all()),
                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name', fn (Builder $query) => auth()->user()?->hasRole('super_admin')
                        ? $query
                        : $query->where('organisation_id', auth()->user()?->organisation_id))
                    ->searchable(),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'From ' . $data['from'] : null,
                        ($data['until'] ?? null) ? 'Until ' . $data['until'] : null,
                    ])),
                SelectFilter::make('organisation_id')
                    ->relationship('organisation', 'name')
                    ->label('Organisation')
                    ->hidden(fn () => !auth()->user()?->hasRole('super_admin')),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (AuditLog $record) => ucfirst($record->action) . ' ' . self::typeLabel($record->auditable_type) . ' #' . $record->auditable_id)
                    ->modalContent(fn (AuditLog $record) => view('filament.audit-log-details', [
                        'record' => $record,
                        'rows' => self::rows($record),
                        'name' => self::recordName($record),
                        'timezone' => LocalTime::timezoneFor(auth()->user()?->organisation_id),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([
                // Read-only: audit entries are never created, edited or deleted by hand.
            ]);
    }

    /** "ManualSection" → "Manual section" */
    public static function typeLabel(?string $type): string
    {
        return Str::ucfirst(Str::lower(Str::headline(class_basename((string) $type))));
    }

    /** A readable name for the record, taken from the logged values (works after deletion too). */
    public static function recordName(AuditLog $record): ?string
    {
        $values = ($record->new_values ?? []) + ($record->old_values ?? []);

        foreach (['name', 'title', 'subject', 'email', 'version', 'date'] as $key) {
            if (!empty($values[$key]) && is_scalar($values[$key])) {
                return Str::limit((string) $values[$key], 60);
            }
        }

        // An update only logs changed fields; fall back to the current record.
        $model = $record->auditable_type && class_exists($record->auditable_type)
            ? $record->auditable_type::query()->find($record->auditable_id)
            : null;

        foreach (['name', 'title', 'subject', 'email'] as $key) {
            if ($model && !empty($model->{$key}) && is_scalar($model->{$key})) {
                return Str::limit((string) $model->{$key}, 60);
            }
        }

        return null;
    }

    public static function changedFields(AuditLog $record): ?string
    {
        if ($record->action !== 'updated') {
            return null;
        }

        return collect(array_keys($record->new_values ?? []))
            ->map(fn ($field) => Str::headline($field))
            ->join(', ') ?: null;
    }

    /**
     * Field / before / after rows for the details window.
     *
     * @return array<int, array{field: string, old: ?string, new: ?string}>
     */
    public static function rows(AuditLog $record): array
    {
        $old = $record->old_values ?? [];
        $new = $record->new_values ?? [];

        // Updates: only the fields that changed (older entries logged the whole previous row).
        $fields = $record->action === 'updated' ? array_keys($new) : array_unique([...array_keys($new), ...array_keys($old)]);

        return collect($fields)
            ->reject(fn ($field) => in_array($field, ['created_at', 'updated_at'], true) && $record->action !== 'deleted')
            ->map(fn ($field) => [
                'field' => Str::headline($field),
                'old' => array_key_exists($field, $old) ? self::display($old[$field]) : null,
                'new' => array_key_exists($field, $new) ? self::display($new[$field]) : null,
            ])
            ->values()
            ->all();
    }

    private static function display(mixed $value): string
    {
        return match (true) {
            $value === null => '(empty)',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => Str::limit(strip_tags((string) $value), 500),
        };
    }
}
