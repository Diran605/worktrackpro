<x-filament-panels::page>
    <style>
        .custom-report-table th, .custom-report-table td {
            padding: 1.25rem 1.5rem !important;
            border-bottom: 1px solid rgba(100, 100, 100, 0.2);
            vertical-align: middle;
        }
        .custom-report-container {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            align-items: flex-end;
        }
        .custom-report-col {
            flex: 1;
            min-width: 250px;
        }
    </style>
    <div class="space-y-6">
        
        <!-- Filters Header -->
        <x-filament::section>
            <x-slot name="heading">
                Reporting Date & Period Filter
            </x-slot>
            <x-slot name="description">
                Select a period (Day, Week, Month, Year) and a target date to generate the reports.
            </x-slot>
            
            <div class="custom-report-container">
                <div class="custom-report-col">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" style="margin-bottom: 8px;">Select Period</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="reportPeriod">
                            <option value="day">By Day</option>
                            <option value="week">By Week</option>
                            <option value="month">By Month</option>
                            <option value="year">By Year</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                <div class="custom-report-col">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" style="margin-bottom: 8px;">Target Date</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="date" wire:model.live="reportDate" />
                    </x-filament::input.wrapper>
                </div>
                <div class="custom-report-col" style="display: flex; justify-content: flex-end; padding-top: 10px;">
                    <x-filament::button tag="a" href="{{ route('reports.team-pdf', ['date' => $reportDate, 'period' => $reportPeriod]) }}" target="_blank" color="primary" size="lg" icon="heroicon-o-document-chart-bar">
                        Preview Team Summary (PDF)
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        <!-- Personnel Reports Table -->
        <x-filament::section>
            <x-slot name="heading">
                Individual Worker Productivity
            </x-slot>
            
            <div class="overflow-x-auto shadow-sm ring-1 ring-gray-950/5 rounded-xl dark:ring-white/10 mt-6" style="margin-top: 1.5rem;">
                <table class="w-full text-left table-auto border-collapse custom-report-table">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="text-sm font-semibold text-gray-700 dark:text-gray-300 tracking-wide uppercase">Worker Name</th>
                            <th class="text-sm font-semibold text-gray-700 dark:text-gray-300 tracking-wide uppercase">Department</th>
                            <th class="text-sm font-semibold text-gray-700 dark:text-gray-300 text-right tracking-wide uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                        @foreach($users as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="text-base font-medium text-gray-900 dark:text-gray-100">
                                {{ $user->name }}
                            </td>
                            <td class="text-base text-gray-500 dark:text-gray-400">
                                <span class="px-4 py-1.5 bg-gray-100 dark:bg-gray-800 rounded-full text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                    {{ $user->department?->name ?? 'None' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end">
                                    <x-filament::button tag="a" href="{{ route('reports.worker-pdf', ['user' => $user->id, 'date' => $reportDate, 'period' => $reportPeriod]) }}" target="_blank" color="success" size="sm" icon="heroicon-o-document-magnifying-glass">
                                        Preview Worker PDF
                                    </x-filament::button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                        @if($users->isEmpty())
                        <tr>
                            <td colspan="3" class="text-center text-gray-500 dark:text-gray-400 font-medium tracking-wide">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <x-heroicon-o-users class="w-8 h-8 text-gray-400" />
                                    <span>No active workers found in your purview for this period.</span>
                                </div>
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
