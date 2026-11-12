{{-- Inline styles: the admin panel's stylesheet only contains Filament's own classes. --}}
@php
    $muted = 'opacity:.65;font-size:.8rem';
    $cell = 'padding:.5rem .75rem;vertical-align:top;word-break:break-word;border-top:1px solid rgba(128,128,128,.2)';
@endphp
<div style="font-size:.875rem;display:flex;flex-direction:column;gap:1rem">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:.75rem 1.5rem">
        <div>
            <div style="{{ $muted }}">When</div>
            <div style="font-weight:600">{{ $record->created_at?->setTimezone($timezone)->format('j M Y H:i:s') }} <span style="{{ $muted }}">({{ $timezone }})</span></div>
        </div>
        <div>
            <div style="{{ $muted }}">By</div>
            <div style="font-weight:600">{{ $record->user?->name ?? 'System (no one signed in)' }}</div>
        </div>
        <div>
            <div style="{{ $muted }}">Record</div>
            <div style="font-weight:600">{{ \App\Filament\Resources\AuditLogs\Tables\AuditLogsTable::typeLabel($record->auditable_type) }} #{{ $record->auditable_id }}@if ($name) · {{ $name }}@endif</div>
        </div>
        <div>
            <div style="{{ $muted }}">IP address</div>
            <div style="font-weight:600">{{ $record->ip_address ?: '—' }}</div>
        </div>
    </div>
    @if ($record->user_agent)
        <div>
            <div style="{{ $muted }}">Browser</div>
            <div style="font-size:.75rem;word-break:break-all">{{ $record->user_agent }}</div>
        </div>
    @endif

    @if (count($rows))
        <div style="overflow-x:auto;border:1px solid rgba(128,128,128,.25);border-radius:.5rem">
            <table style="width:100%;border-collapse:collapse;text-align:left">
                <thead>
                    <tr style="background:rgba(128,128,128,.08)">
                        <th style="padding:.5rem .75rem;font-weight:600">Field</th>
                        @if ($record->action !== 'created')<th style="padding:.5rem .75rem;font-weight:600">Before</th>@endif
                        @if ($record->action !== 'deleted')<th style="padding:.5rem .75rem;font-weight:600">After</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td style="{{ $cell }};font-weight:600;white-space:nowrap">{{ $row['field'] }}</td>
                            @if ($record->action !== 'created')
                                <td style="{{ $cell }}{{ $record->action === 'updated' ? ';color:#dc2626' : '' }}">{{ $row['old'] ?? '' }}</td>
                            @endif
                            @if ($record->action !== 'deleted')
                                <td style="{{ $cell }}{{ $record->action === 'updated' ? ';color:#16a34a' : '' }}">{{ $row['new'] ?? '' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p style="{{ $muted }}">No field values were recorded.</p>
    @endif
</div>
