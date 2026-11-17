<div class="space-y-4">
    @if ($timeline->isEmpty())
        <div class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
            No service or financial activity recorded for this customer.
        </div>
    @else
        <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-gray-200 dark:before:bg-gray-800">
            @foreach ($timeline as $event)
                <div class="relative">
                    <div class="absolute -left-6 top-1.5 w-3 h-3 rounded-full border-2 border-white dark:border-gray-900 
                        {{ match($event['type']) {
                            'Work Order' => 'bg-blue-600',
                            'Service Request' => 'bg-amber-500',
                            'Invoice' => 'bg-emerald-600',
                            'Estimate' => 'bg-purple-600',
                            default => 'bg-gray-400',
                        } }}">
                    </div>

                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold uppercase tracking-wider 
                                {{ match($event['type']) {
                                    'Work Order' => 'text-blue-600 dark:text-blue-400',
                                    'Service Request' => 'text-amber-600 dark:text-amber-400',
                                    'Invoice' => 'text-emerald-600 dark:text-emerald-400',
                                    'Estimate' => 'text-purple-600 dark:text-purple-400',
                                    default => 'text-gray-500',
                                } }}">
                                {{ $event['type'] }}
                            </span>
                            <span class="text-gray-500 dark:text-gray-400">
                                {{ $event['date'] ? $event['date']->format('M d, Y h:i A') : '—' }}
                            </span>
                        </div>

                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $event['title'] }}
                        </div>

                        <div class="text-xs text-gray-600 dark:text-gray-400 flex items-center justify-between">
                            <span>{{ $event['subtitle'] }}</span>
                            @if ($event['amount'] !== null)
                                <span class="font-semibold text-gray-900 dark:text-white">₹{{ number_format($event['amount'], 2) }}</span>
                            @endif
                        </div>

                        @if ($event['status'])
                            <div class="pt-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-200/60 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                    {{ ucfirst($event['status']) }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
