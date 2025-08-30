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
                    wire:click="$refresh"
                    color="primary"
                    icon="heroicon-o-arrow-path">
                    Apply Filters
                </x-filament::button>
            </x-slot>
        </x-filament::section>

        {{-- Summary Statistics --}}
        @php
            $summary = $this->getReportSummary();
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Subscriptions -->
            <x-filament::section class="bg-primary-50 dark:bg-gray-800">
                <div class="text-center">
                    <div class="text-2xl font-bold text-black dark:text-white">
                        {{ number_format($summary['totalSubscriptions']) }}
                    </div>
                    <div class="text-sm text-gray-800 dark:text-gray-300">Total Subscriptions</div>
                </div>
            </x-filament::section>

            <!-- Total Amount -->
            <x-filament::section class="bg-green-50 dark:bg-green-900/50">
                <div class="text-center">
                    <div class="text-2xl font-bold text-black dark:text-white">
                        ₱{{ number_format($summary['totalAmount'], 2) }}
                    </div>
                    <div class="text-sm text-gray-800 dark:text-gray-300">Total Amount</div>
                </div>
            </x-filament::section>

            <!-- Active Subscriptions -->
            <x-filament::section class="bg-yellow-50 dark:bg-yellow-900/50">
                <div class="text-center">
                    <div class="text-2xl font-bold text-black dark:text-white">
                        {{ number_format($summary['statusCounts']['active']) }}
                    </div>
                    <div class="text-sm text-gray-800 dark:text-gray-300">Active Subscriptions</div>
                </div>
            </x-filament::section>

            <!-- Expired Subscriptions -->
            <x-filament::section class="bg-red-50 dark:bg-red-900/50">
                <div class="text-center">
                    <div class="text-2xl font-bold text-black dark:text-white">
                        {{ number_format($summary['statusCounts']['expired']) }}
                    </div>
                    <div class="text-sm text-gray-800 dark:text-gray-300">Expired Subscriptions</div>
                </div>
            </x-filament::section>
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

                <div class="overflow-hidden rounded-lg ">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-100 dark:bg-gray-700 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-4 text-left text-xs font-semibold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">
                                    <div class="flex items-center space-x-2">
                                        <span>Branch</span>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[120px]">
                                    <div class="flex flex-col items-center space-y-1">
                                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                        <span>Total Subs</span>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-green-600 dark:text-green-400 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[100px]">
                                    <div class="flex flex-col items-center space-y-1">
                                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                        <span>Active</span>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[100px]">
                                    <div class="flex flex-col items-center space-y-1">
                                        <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                        <span>Expired</span>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-yellow-600 dark:text-yellow-400 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[100px]">
                                    <div class="flex flex-col items-center space-y-1">
                                        <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                                        <span>Archived</span>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider min-w-[140px]">
                                    <div class="flex flex-col items-center space-y-1">
                                        <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                        <span>Total Amount</span>
                                    </div>
                                </th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($summary['branchStats'] as $branch => $stats)
                                <tr class=" dark:hover:bg-gray-700/50 transition-colors duration-200 group">
                                    <td class="px-4 py-4 whitespace-nowrap border-r border-gray-200 dark:border-gray-600">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-2 h-8 bg-blue-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                            <span class="text-sm font-semibold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">{{ $branch }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center border-r border-gray-200 dark:border-gray-600">
                                        <div class="flex flex-col items-center">
                                            <span class="text-lg font-bold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">{{ number_format($stats['totalSubscriptions']) }}</span>
                                            <span class="text-xs text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">subscriptions</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center border-r border-gray-200 dark:border-gray-600">
                                        <div class="flex flex-col items-center">
                                            <span class="text-lg font-bold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">{{ number_format($stats['active']) }}</span>
                                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1 mt-1">
                                                <div class="bg-green-500 h-1 rounded-full" style="width: {{ $stats['totalSubscriptions'] > 0 ? ($stats['active'] / $stats['totalSubscriptions']) * 100 : 0 }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center border-r border-gray-200 dark:border-gray-600">
                                        <div class="flex flex-col items-center">
                                            <span class="text-lg font-bold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">{{ number_format($stats['expired']) }}</span>
                                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1 mt-1">
                                                <div class="bg-red-500 h-1 rounded-full" style="width: {{ $stats['totalSubscriptions'] > 0 ? ($stats['expired'] / $stats['totalSubscriptions']) * 100 : 0 }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center border-r border-gray-200 dark:border-gray-600">
                                        <div class="flex flex-col items-center">
                                            <span class="text-lg font-bold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">{{ number_format($stats['archived']) }}</span>
                                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1 mt-1">
                                                <div class="bg-yellow-500 h-1 rounded-full" style="width: {{ $stats['totalSubscriptions'] > 0 ? ($stats['archived'] / $stats['totalSubscriptions']) * 100 : 0 }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <div class="flex flex-col items-center">
                                            <span class="text-lg font-bold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">₱{{ number_format($stats['amount'], 2) }}</span>
                                            <span class="text-xs text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">total revenue</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Insurance Statistics Table --}}
        @if(count($summary['insuranceStats']) > 0)
            <x-filament::section class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 mt-6">
                <x-slot name="heading">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-2 h-8 bg-emerald-500 rounded-full"></div>
                            <h2 class="text-xl font-bold text-black dark:text-white">Insurance Statistics</h2>
                        </div>
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            {{ count($summary['insuranceStats']) }} insurance types
                        </div>
                    </div>
                </x-slot>

                <div class="overflow-hidden rounded-lg ">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-100 dark:bg-gray-700 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-4 text-left text-xs font-semibold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[160px]">
                                    <div class="flex items-center space-x-2">
                                        <span>Insurance Name</span>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[120px]">
                                    <div class="flex flex-col items-center space-y-1">
                                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                        <span>Total Subs</span>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-green-600 dark:text-green-400 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[100px]">
                                    <div class="flex flex-col items-center space-y-1">
                                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                        <span>Active</span>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[100px]">
                                    <div class="flex flex-col items-center space-y-1">
                                        <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                        <span>Expired</span>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-yellow-600 dark:text-yellow-400 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[100px]">
                                    <div class="flex flex-col items-center space-y-1">
                                        <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                                        <span>Archived</span>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider min-w-[140px]">
                                    <div class="flex flex-col items-center space-y-1">
                                        <div class="w-3 h-3 bg-emerald-500 rounded-full"></div>
                                        <span>Total Amount</span>
                                    </div>
                                </th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($summary['insuranceStats'] as $insurance => $stats)
                                <tr class=" dark:hover:bg-gray-700/50 transition-colors duration-200 group">
                                    <td class="px-4 py-4 whitespace-nowrap border-r border-gray-200 dark:border-gray-600">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-2 h-8 bg-emerald-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                            <span class="text-sm font-semibold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">{{ $insurance }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center border-r border-gray-200 dark:border-gray-600">
                                        <div class="flex flex-col items-center">
                                            <span class="text-lg font-bold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">{{ number_format($stats['totalSubscriptions']) }}</span>
                                            <span class="text-xs text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">subscriptions</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center border-r border-gray-200 dark:border-gray-600">
                                        <div class="flex flex-col items-center">
                                            <span class="text-lg font-bold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">{{ number_format($stats['active']) }}</span>
                                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1 mt-1">
                                                <div class="bg-green-500 h-1 rounded-full" style="width: {{ $stats['totalSubscriptions'] > 0 ? ($stats['active'] / $stats['totalSubscriptions']) * 100 : 0 }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center border-r border-gray-200 dark:border-gray-600">
                                        <div class="flex flex-col items-center">
                                            <span class="text-lg font-bold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">{{ number_format($stats['expired']) }}</span>
                                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1 mt-1">
                                                <div class="bg-red-500 h-1 rounded-full" style="width: {{ $stats['totalSubscriptions'] > 0 ? ($stats['expired'] / $stats['totalSubscriptions']) * 100 : 0 }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center border-r border-gray-200 dark:border-gray-600">
                                        <div class="flex flex-col items-center">
                                            <span class="text-lg font-bold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">{{ number_format($stats['archived']) }}</span>
                                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1 mt-1">
                                                <div class="bg-yellow-500 h-1 rounded-full" style="width: {{ $stats['totalSubscriptions'] > 0 ? ($stats['archived'] / $stats['totalSubscriptions']) * 100 : 0 }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <div class="flex flex-col items-center">
                                            <span class="text-lg font-bold text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">₱{{ number_format($stats['amount'], 2) }}</span>
                                            <span class="text-xs text-black dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600 min-w-[140px]">total revenue</span>
                                        </div>
                                    </td>
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
