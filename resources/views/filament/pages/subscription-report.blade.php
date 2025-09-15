<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filter Form --}}
        <x-filament::section>
            <x-slot name="heading" class="text-gray-900 dark:text-white">
                Report Filters
            </x-slot>

            {{ $this->form }}

            <x-slot name="footerActions">
                <x-filament::button
                    wire:click="applyFilters"
                    color="primary"
                    icon="heroicon-o-arrow-path">
                    Apply Filters
                </x-filament::button>
            </x-slot>
        </x-filament::section>

        {{-- Summary Statistics --}}
        @php
            $summary = $this->getReportSummary() ?? [
                'totalSubscriptions' => 0,
                'totalAmount' => 0,
                'statusCounts' => ['active' => 0, 'expired' => 0, 'archived' => 0, 'future' => 0],
                'branchStats' => [],
                'insuranceStats' => [],
            ];
        @endphp

        <div class="flex gap-6">
    <div class="flex-1 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-600 dark:text-gray-400">Total Subscriptions</div>
        <div class="text-2xl font-bold text-black dark:text-white">
            {{ number_format($summary['totalSubscriptions']) }}
        </div>
        <div class="text-xs text-gray-400">Across all members</div>
    </div>

    <div class="flex-1 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-600 dark:text-gray-400">Total Amount</div>
        <div class="text-2xl font-bold text-black dark:text-white">
            ₱{{ number_format($summary['totalAmount'], 2) }}
        </div>
        <div class="text-xs text-gray-400">Total revenue</div>
    </div>

    {{-- <div class="flex-1 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-600 dark:text-gray-400">Active</div>
        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
            {{ number_format($summary['statusCounts']['active']) }}
        </div>
        <div class="text-xs text-green-500">Currently active</div>
    </div>

    <div class="flex-1 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-600 dark:text-gray-400">Expired</div>
        <div class="text-2xl font-bold text-red-600 dark:text-red-400">
            {{ number_format($summary['statusCounts']['expired']) }}
        </div>
        <div class="text-xs text-red-500">Already expired</div>
    </div> --}}
</div>

        {{-- Branch Statistics Table --}}
        @if(count($summary['branchStats']) > 0)
            <x-filament::section class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <x-slot name="heading">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-2 h-8 bg-blue-500 rounded-full"></div>
                            <h2 class="text-xl font-bold text-black dark:text-white">Branch Statistics</h2>
                        </div>
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            {{ count($summary['branchStats']) }} branches
                        </div>
                    </div>
                </x-slot>

                <div class="overflow-hidden rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-100 dark:bg-gray-700 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-4 text-left text-xs font-semibold text-black dark:text-white uppercase tracking-wider">Branch</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-black dark:text-white uppercase tracking-wider">Total Subscription</th>
                                {{-- <th class="px-4 py-4 text-center text-xs font-semibold text-green-600 dark:text-green-400 uppercase tracking-wider">Active</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider">Expired</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-yellow-600 dark:text-yellow-400 uppercase tracking-wider">Archived</th> --}}
                                <th class="px-4 py-4 text-center text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Total Amount</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($summary['branchStats'] as $branch => $stats)
                                <tr class="dark:hover:bg-gray-700/50 transition-colors duration-200">
                                    <td class="px-4 py-4 text-sm font-semibold text-black dark:text-white">{{ $branch }}</td>
                                    <td class="px-4 py-4 text-center">{{ number_format($stats['totalSubscriptions']) }}</td>
                                    {{-- <td class="px-4 py-4 text-center">
                                        {{ number_format($stats['active']) }}
                                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1 mt-1">
                                            <div class="bg-green-500 h-1 rounded-full" style="width: {{ $stats['totalSubscriptions'] > 0 ? ($stats['active'] / $stats['totalSubscriptions']) * 100 : 0 }}%"></div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        {{ number_format($stats['expired']) }}
                                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1 mt-1">
                                            <div class="bg-red-500 h-1 rounded-full" style="width: {{ $stats['totalSubscriptions'] > 0 ? ($stats['expired'] / $stats['totalSubscriptions']) * 100 : 0 }}%"></div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        {{ number_format($stats['archived']) }}
                                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1 mt-1">
                                            <div class="bg-yellow-500 h-1 rounded-full" style="width: {{ $stats['totalSubscriptions'] > 0 ? ($stats['archived'] / $stats['totalSubscriptions']) * 100 : 0 }}%"></div>
                                        </div>
                                    </td> --}}
                                    <td class="px-4 py-4 text-center">₱{{ number_format($stats['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-filament::section>
        @endif


        {{-- Subscription Table --}}
        <x-filament::section class="bg-white dark:bg-gray-800">
            <x-slot name="heading" class="text-black dark:text-white">
                Subscription Details
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
