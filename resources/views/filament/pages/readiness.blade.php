@php
    $colours = [
        'ready' => 'background:#dcfce7;color:#166534',
        'learning' => 'background:#fef3c7;color:#92400e',
        'not_started' => 'background:#fee2e2;color:#991b1b',
        'not_required' => 'color:#9ca3af',
    ];
@endphp
<x-filament-panels::page>
    @if ($risks->isNotEmpty())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <x-slot name="heading">Products that depend on too few people</x-slot>
            <x-slot name="description">Fewer than two people are Ready. If they are away, nobody else can do the job.</x-slot>
            <ul style="display:grid;gap:.5rem">
                @foreach ($risks as $risk)
                    <li>
                        <strong>{{ $risk['system']->name }}</strong>:
                        @if ($risk['ready']->isEmpty())
                            nobody is Ready yet
                        @else
                            only {{ $risk['ready']->join(', ') }}
                        @endif
                        <span style="opacity:.6">({{ $risk['required'] }} {{ \Illuminate\Support\Str::plural('person', $risk['required']) }} must pass its checks)</span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <div style="display:flex;flex-wrap:wrap;gap:.75rem;font-size:.8rem">
                @foreach (['ready', 'learning', 'not_started'] as $level)
                    <span style="padding:.15rem .6rem;border-radius:999px;{{ $colours[$level] }}">{{ $levels[$level] }}</span>
                @endforeach
                <span style="{{ $colours['not_required'] }}">— not required</span>
            </div>
            @if ($departments->isNotEmpty())
                <x-filament::input.wrapper style="min-width:14rem">
                    <x-filament::input.select wire:model.live="department">
                        <option value="">All departments</option>
                        @foreach ($departments as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            @endif
        </div>

        @if ($systems->isEmpty())
            <p style="opacity:.7">No competency checks yet. Add one under Competency › Competency Checks.</p>
        @elseif ($rows->isEmpty())
            <p style="opacity:.7">Nobody here has to pass a check yet. Choose who must pass each check under Competency › Competency Checks.</p>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:separate;border-spacing:4px;font-size:.875rem">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:.25rem .5rem">Person</th>
                            @foreach ($systems as $system)
                                <th style="text-align:center;padding:.25rem .5rem;font-weight:600">{{ $system->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td style="padding:.25rem .5rem;white-space:nowrap">
                                    {{ $row['user']->name }}
                                    @if ($row['user']->department)
                                        <span style="display:block;font-size:.75rem;opacity:.6">{{ $row['user']->department->name }}</span>
                                    @endif
                                </td>
                                @foreach ($systems as $system)
                                    @php $cell = $row['cells'][$system->id]; @endphp
                                    <td style="text-align:center;padding:.4rem .5rem;border-radius:.375rem;{{ $colours[$cell['level']] }}">
                                        @if ($cell['level'] === 'not_required')
                                            —
                                        @else
                                            {{ $levels[$cell['level']] }}
                                            @if ($cell['level'] === 'learning')
                                                <span style="opacity:.75">{{ $cell['score'] }}%</span>
                                            @endif
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
