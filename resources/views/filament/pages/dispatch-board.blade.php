<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Top Controls & Metrics -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-gray-800 p-4 rounded-xl shadow-xs border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Filter Branch:</span>
                <select wire:model.live="selectedBranchId" class="text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">All Branches</option>
                    @foreach (\App\Filament\Resources\Support\FilamentOptions::branches() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-4">
                <div class="text-xs text-gray-500">
                    <span class="font-bold text-gray-800 dark:text-gray-200">{{ $this->unassignedWorkOrders->count() }}</span> unassigned jobs
                </div>
                <div>
                    {{ $this->assignWorkOrderAction() }}
                </div>
            </div>
        </div>

        <!-- Dispatch Grid: Technicians Workload & Schedules -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @forelse ($this->technicianSchedules as $schedule)
                @php
                    $tech = $schedule['technician'];
                    $todayCount = $schedule['todayCount'];
                    $weekCount = $schedule['weekCount'];
                    $availabilities = $schedule['availabilities'];
                    $orders = $schedule['activeOrders'];
                    $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs flex flex-col justify-between overflow-hidden">
                    <div class="p-5 space-y-4">
                        <!-- Tech Header -->
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">{{ $tech->name }}</h3>
                                <p class="text-xs text-gray-500">{{ $tech->email }}</p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $todayCount > 3 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $todayCount }} today
                                </span>
                                <span class="block text-[11px] text-gray-400 mt-0.5">{{ $weekCount }} this week</span>
                            </div>
                        </div>

                        <!-- Weekly Availability Badges -->
                        <div>
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block mb-1.5">Availability</span>
                            @if ($availabilities->isEmpty())
                                <span class="text-xs text-gray-400 italic">No custom working hours set (default 9-5)</span>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($availabilities as $av)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            {{ $days[$av->day_of_week] ?? $av->day_of_week }}: {{ substr($av->starts_at, 0, 5) }}–{{ substr($av->ends_at, 0, 5) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Scheduled Active Orders -->
                        <div class="space-y-2">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Upcoming Active Jobs ({{ $orders->count() }})</span>
                            @if ($orders->isEmpty())
                                <p class="text-xs text-gray-400 italic bg-gray-50 dark:bg-gray-900/50 p-2.5 rounded-lg text-center">No active jobs scheduled.</p>
                            @else
                                <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                    @foreach ($orders->take(5) as $order)
                                        <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 text-xs flex items-center justify-between">
                                            <div>
                                                <span class="font-bold text-gray-800 dark:text-gray-200 block">{{ $order->work_order_number }}</span>
                                                <span class="text-gray-500 text-[11px]">{{ $order->title }}</span>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-[10px] text-gray-500 block">{{ $order->scheduled_start_at?->format('M d, g:i A') ?? 'Unscheduled' }}</span>
                                                @if ($order->has_overlap_override)
                                                    <span class="text-[9px] text-amber-600 font-semibold">Overridden</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-12 text-center bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-2">
                    <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">No technicians found for this branch filter.</p>
                    <p class="text-xs text-gray-400">Assign technicians to branches in Team Members to populate the dispatch board.</p>
                </div>
            @endforelse
        </div>

        <!-- Unassigned Work Orders Queue -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Unassigned Work Orders Queue</h3>
                    <p class="text-xs text-gray-500">Orders requiring technician assignment</p>
                </div>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                    {{ $this->unassignedWorkOrders->count() }} Pending
                </span>
            </div>

            @if ($this->unassignedWorkOrders->isEmpty())
                <p class="text-xs text-gray-400 italic py-6 text-center">All work orders are currently assigned.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 uppercase text-[10px] font-bold text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="p-3">Order Number</th>
                                <th class="p-3">Title</th>
                                <th class="p-3">Customer</th>
                                <th class="p-3">Branch</th>
                                <th class="p-3">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($this->unassignedWorkOrders as $wo)
                                <tr>
                                    <td class="p-3 font-mono font-bold text-gray-800 dark:text-gray-200">{{ $wo->work_order_number }}</td>
                                    <td class="p-3 font-medium text-gray-900 dark:text-gray-100">{{ $wo->title }}</td>
                                    <td class="p-3">{{ $wo->customer?->name }}</td>
                                    <td class="p-3">{{ $wo->branch?->name ?? 'None' }}</td>
                                    <td class="p-3">{{ $wo->created_at->format('M d, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
