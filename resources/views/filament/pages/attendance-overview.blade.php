<x-filament::page>
    <div class="space-y-4">
        <form method="get" class="flex items-end gap-3">
            <div>
                <label class="text-sm font-semibold">Date</label>
                <input type="date" name="date" value="{{ request('date', $date) }}" class="filament-forms-input w-48" />
            </div>
            <x-filament::button type="submit">View</x-filament::button>
        </form>

        @php
            $selectedDate = request('date', $date);
            $statusColor = function (string $status) use ($SessionStatus) {
                return match ($status) {
                    $SessionStatus::Closed->value => 'success',
                    $SessionStatus::SystemClosed->value => 'danger',
                    $SessionStatus::Active->value => 'warning',
                    'absent' => 'danger',
                    default => 'gray',
                };
            };
        @endphp

        <x-filament::section>
            <x-slot name="heading">Attendance for {{ $selectedDate }}</x-slot>
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-2 pr-4">Worker</th>
                            <th class="py-2 pr-4">Department</th>
                            <th class="py-2 pr-4">Clock In</th>
                            <th class="py-2 pr-4">Clock Out</th>
                            <th class="py-2 pr-4">Total</th>
                            <th class="py-2 pr-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($rows as $row)
                            @php
                                $u = $row['user'];
                                $s = $row['session'];
                                $status = $row['status'];
                                $total = $s?->total_minutes;
                                $totalFmt = $total === null ? '—' : (intdiv((int)$total, 60) ? intdiv((int)$total, 60) . 'h ' : '') . ((int)$total % 60) . 'm';
                            @endphp
                            <tr>
                                <td class="py-2 pr-4 font-semibold">{{ $u->name }}</td>
                                <td class="py-2 pr-4">{{ $u->department?->name ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $s?->clock_in?->format('H:i') ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $s?->clock_out?->format('H:i') ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $totalFmt }}</td>
                                <td class="py-2 pr-4">
                                    <x-filament::badge :color="$statusColor($status)">
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament::page>

