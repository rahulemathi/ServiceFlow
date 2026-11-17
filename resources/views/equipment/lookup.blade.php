<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $equipment->name }} — Equipment Lookup</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body { font-family: 'Instrument Sans', sans-serif; }</style>
</head>
<body class="min-h-full py-10 px-4 sm:px-6 lg:px-8 text-slate-800 dark:text-slate-200">
    <div class="max-w-3xl mx-auto space-y-6">
        <!-- Top bar -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <a href="/admin/{{ $tenant->slug }}/equipment" class="text-xs font-semibold text-blue-600 hover:text-blue-500">
                    &larr; Back to Equipment
                </a>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                    {{ $equipment->name }}
                </h1>
                <div class="text-xs text-slate-500">
                    Organization: <span class="font-medium text-slate-700 dark:text-slate-300">{{ $tenant->name }}</span>
                    @if ($equipment->branch)
                        &bull; Branch: <span class="font-medium text-slate-700 dark:text-slate-300">{{ $equipment->branch->name }}</span>
                    @endif
                </div>
            </div>
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-{{ $equipment->status?->color() ?? 'gray' }}-50 text-{{ $equipment->status?->color() ?? 'gray' }}-700 dark:bg-{{ $equipment->status?->color() ?? 'gray' }}-950/40 dark:text-{{ $equipment->status?->color() ?? 'gray' }}-300">
                    {{ $equipment->status?->label() ?? 'Unknown' }}
                </span>
            </div>
        </div>

        <!-- Specifications & Warranty Card -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Specifications</h2>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Manufacturer</span> <span class="font-semibold text-slate-900 dark:text-white">{{ $equipment->manufacturer ?: '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Model</span> <span class="font-semibold text-slate-900 dark:text-white">{{ $equipment->model ?: '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Serial Number</span> <span class="font-mono font-semibold text-slate-900 dark:text-white">{{ $equipment->serial_number ?: '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Installed Date</span> <span class="font-medium text-slate-700 dark:text-slate-300">{{ $equipment->installed_at ? $equipment->installed_at->format('M d, Y') : '—' }}</span></div>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Customer & Warranty</h2>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Customer</span> <span class="font-semibold text-slate-900 dark:text-white">{{ $equipment->customer?->name ?: '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Location</span> <span class="font-medium text-slate-700 dark:text-slate-300 truncate max-w-[180px]">{{ $equipment->address ? $equipment->address->city : '—' }}</span></div>
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div class="text-xs font-semibold {{ $equipment->isUnderWarranty() ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500' }}">
                            {{ $equipment->warrantyStatusLabel() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service History Section -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Service History (Work Orders)</h2>
            @if ($equipment->workOrders->isEmpty())
                <p class="text-xs text-slate-500">No service work orders recorded for this equipment.</p>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($equipment->workOrders as $wo)
                        <div class="py-3 flex items-center justify-between">
                            <div>
                                <div class="font-semibold text-xs text-slate-900 dark:text-white">{{ $wo->work_order_number }} — {{ $wo->title }}</div>
                                <div class="text-[11px] text-slate-500">Assigned: {{ $wo->assignedTo?->name ?? 'Unassigned' }} &bull; {{ $wo->created_at->format('M d, Y') }}</div>
                            </div>
                            <span class="text-xs font-medium px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                {{ ucfirst($wo->status->value ?? (string) $wo->status) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</body>
</html>
