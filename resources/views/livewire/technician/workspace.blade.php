<div class="space-y-6">
    <!-- Notifications / Alerts -->
    @if ($feedbackMessage)
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800 flex items-center justify-between shadow-xs">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>{{ $feedbackMessage }}</span>
            </div>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-600 hover:text-emerald-900 font-bold">&times;</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800 flex items-center justify-between shadow-xs">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <span>{{ $errorMessage }}</span>
            </div>
            <button wire:click="$set('errorMessage', null)" class="text-rose-600 hover:text-rose-900 font-bold">&times;</button>
        </div>
    @endif

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
        <div wire:click="setTab('today')" class="cursor-pointer bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs hover:border-blue-400 hover:shadow-md transition {{ $activeTab === 'today' ? 'ring-2 ring-blue-500 bg-blue-50/20' : '' }}">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Today</div>
            <div class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900">{{ $metrics['today'] }}</div>
            <div class="text-xs text-slate-500 mt-1">Scheduled for today</div>
        </div>

        <div wire:click="setTab('in_progress')" class="cursor-pointer bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs hover:border-amber-400 hover:shadow-md transition {{ $activeTab === 'in_progress' ? 'ring-2 ring-amber-500 bg-amber-50/20' : '' }}">
            <div class="text-xs font-semibold uppercase tracking-wider text-amber-600">In Progress</div>
            <div class="mt-2 text-2xl sm:text-3xl font-extrabold text-amber-700">{{ $metrics['in_progress'] }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $metrics['on_hold'] }} on hold</div>
        </div>

        <div wire:click="setTab('all')" class="cursor-pointer bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs hover:border-blue-400 hover:shadow-md transition {{ $activeTab === 'all' ? 'ring-2 ring-blue-500 bg-blue-50/20' : '' }}">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Active Jobs</div>
            <div class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900">{{ $metrics['total'] - $metrics['completed'] }}</div>
            <div class="text-xs text-slate-500 mt-1">Total open assignments</div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider {{ $metrics['overdue'] > 0 ? 'text-rose-600' : 'text-slate-500' }}">Overdue</div>
            <div class="mt-2 text-2xl sm:text-3xl font-extrabold {{ $metrics['overdue'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $metrics['overdue'] }}</div>
            <div class="text-xs text-slate-500 mt-1">Past scheduled end</div>
        </div>

        <div wire:click="setTab('completed')" class="cursor-pointer bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs hover:border-emerald-400 hover:shadow-md transition {{ $activeTab === 'completed' ? 'ring-2 ring-emerald-500 bg-emerald-50/20' : '' }}">
            <div class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Completed</div>
            <div class="mt-2 text-2xl sm:text-3xl font-extrabold text-emerald-700">{{ $metrics['completed'] }}</div>
            <div class="text-xs text-slate-500 mt-1">Finished reports</div>
        </div>
    </div>

    <!-- Navigation Tab Bar -->
    <div class="flex items-center space-x-2 border-b border-slate-200 pb-2 overflow-x-auto text-sm">
        <button wire:click="setTab('all')" class="px-3.5 py-2 rounded-lg font-medium transition {{ $activeTab === 'all' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-200/60' }}">
            All Assigned
        </button>
        <button wire:click="setTab('today')" class="px-3.5 py-2 rounded-lg font-medium transition {{ $activeTab === 'today' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-200/60' }}">
            Today's Schedule
        </button>
        <button wire:click="setTab('in_progress')" class="px-3.5 py-2 rounded-lg font-medium transition {{ $activeTab === 'in_progress' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-200/60' }}">
            In Progress & On Hold
        </button>
        <button wire:click="setTab('completed')" class="px-3.5 py-2 rounded-lg font-medium transition {{ $activeTab === 'completed' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-200/60' }}">
            Completed History
        </button>
        <button wire:click="setTab('availability')" class="px-3.5 py-2 rounded-lg font-medium transition {{ $activeTab === 'availability' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-200/60' }}">
            My Availability
        </button>
    </div>

    @if ($activeTab === 'availability')
        <!-- Availability View -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Weekly Availability Schedule</h3>
                <p class="text-sm text-slate-500">Your scheduled working hours used by dispatchers for job coordination.</p>
            </div>

            @if ($availabilities->isEmpty())
                <div class="text-center py-10 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-slate-500 text-sm">
                    No weekly availability schedule currently recorded. Contact your dispatcher to set your schedule.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    @php
                        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
                    @endphp
                    @foreach ($availabilities as $avail)
                        <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 flex items-center justify-between">
                            <div>
                                <span class="font-bold text-slate-800 text-sm block">{{ $days[$avail->day_of_week] ?? "Day {$avail->day_of_week}" }}</span>
                                <span class="text-xs text-slate-500">
                                    {{ \Illuminate\Support\Carbon::parse($avail->start_time)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($avail->end_time)->format('g:i A') }}
                                </span>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                                Available
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <!-- Work Orders & Detail Workspace -->
        <div class="grid grid-cols-1 {{ $selectedWorkOrder ? 'lg:grid-cols-12' : '' }} gap-6 items-start">
            <!-- Work Orders List Column -->
            <div class="{{ $selectedWorkOrder ? 'lg:col-span-5' : 'w-full' }} space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ $activeTab === 'today' ? "Today's Work Orders" : ($activeTab === 'completed' ? 'Completed Work Orders' : 'Assigned Work Orders') }}
                    </h3>
                    <span class="text-xs font-semibold text-slate-500 bg-slate-200/70 px-2.5 py-1 rounded-full">
                        {{ $workOrders->count() }} jobs
                    </span>
                </div>

                @if ($workOrders->isEmpty())
                    <div class="text-center py-12 bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-2">
                        <svg class="w-12 h-12 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h4 class="text-sm font-semibold text-slate-700">No jobs found in this tab</h4>
                        <p class="text-xs text-slate-400">All work orders assigned to you will appear here.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($workOrders as $wo)
                            <div wire:click="selectWorkOrder({{ $wo->id }})" class="cursor-pointer bg-white rounded-2xl p-4 border transition shadow-xs hover:shadow-md {{ $selectedWorkOrderId === $wo->id ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50/10' : 'border-slate-200 hover:border-slate-300' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="text-xs font-mono font-bold text-slate-500 block">{{ $wo->work_order_number }}</span>
                                        <h4 class="text-sm font-bold text-slate-900 mt-0.5">{{ $wo->title }}</h4>
                                    </div>
                                    @php
                                        $badgeColor = match ($wo->status->value) {
                                            'new' => 'bg-slate-100 text-slate-800 border-slate-200',
                                            'scheduled' => 'bg-blue-50 text-blue-700 border-blue-200',
                                            'in_progress' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            'on_hold' => 'bg-orange-50 text-orange-700 border-orange-200',
                                            'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            default => 'bg-slate-100 text-slate-700 border-slate-200'
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $badgeColor }}">
                                        {{ str_replace('_', ' ', ucfirst($wo->status->value)) }}
                                    </span>
                                </div>

                                <div class="mt-3 pt-3 border-t border-slate-100 flex flex-col gap-1 text-xs text-slate-600">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        <span class="font-medium text-slate-800">{{ $wo->customer?->name }}</span>
                                    </div>
                                    @if ($wo->address)
                                        <div class="flex items-center gap-1.5 text-slate-500">
                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span class="truncate">{{ $wo->address->address_line_1 }}, {{ $wo->address->city }}</span>
                                        </div>
                                    @endif
                                    @if ($wo->scheduled_start_at)
                                        <div class="flex items-center gap-1.5 text-slate-500 mt-1">
                                            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <span class="font-semibold text-slate-700">{{ $wo->scheduled_start_at->format('M d, Y @ g:i A') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Work Order Detail Column (When Selected) -->
            @if ($selectedWorkOrder)
                <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6 sticky top-20">
                    <!-- Detail Header -->
                    <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold text-slate-500">{{ $selectedWorkOrder->work_order_number }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-bold bg-slate-100 text-slate-700">
                                    {{ strtoupper($selectedWorkOrder->status->value) }}
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 mt-1">{{ $selectedWorkOrder->title }}</h3>
                        </div>
                        <button wire:click="closeWorkOrderDetails" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Status Workflow Actions Bar -->
                    @if ($selectedWorkOrder->status !== \App\WorkOrderStatus::Completed && $selectedWorkOrder->status !== \App\WorkOrderStatus::Cancelled)
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block">Job Workflow Actions</span>
                            <div class="flex flex-wrap gap-2">
                                @if ($selectedWorkOrder->status === \App\WorkOrderStatus::Scheduled)
                                    <button wire:click="transitionStatus('in_progress')" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold text-xs hover:bg-blue-700 shadow-xs flex items-center gap-1.5 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                                        Start Work (In Progress)
                                    </button>
                                @endif

                                @if ($selectedWorkOrder->status === \App\WorkOrderStatus::InProgress)
                                    <button wire:click="transitionStatus('on_hold')" class="px-3.5 py-2 rounded-xl bg-amber-500 text-white font-semibold text-xs hover:bg-amber-600 shadow-xs transition">
                                        Put On Hold
                                    </button>
                                @endif

                                @if ($selectedWorkOrder->status === \App\WorkOrderStatus::OnHold)
                                    <button wire:click="transitionStatus('in_progress')" class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white font-semibold text-xs hover:bg-emerald-700 shadow-xs transition">
                                        Resume Work
                                    </button>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            This work order was marked completed on {{ $selectedWorkOrder->completed_at?->format('M d, Y @ g:i A') ?? 'N/A' }}.
                        </div>
                    @endif

                    <!-- Customer & Service Location -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs bg-slate-50/60 p-4 rounded-xl border border-slate-100">
                        <div>
                            <span class="text-slate-400 font-semibold uppercase block tracking-wider">Customer</span>
                            <span class="font-bold text-slate-800 text-sm block mt-0.5">{{ $selectedWorkOrder->customer?->name }}</span>
                            <span class="text-slate-500">{{ $selectedWorkOrder->customer?->phone ?? 'No phone' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-semibold uppercase block tracking-wider">Service Location</span>
                            @if ($selectedWorkOrder->address)
                                <span class="font-medium text-slate-800 block mt-0.5">{{ $selectedWorkOrder->address->address_line_1 }}</span>
                                <span class="text-slate-500">{{ $selectedWorkOrder->address->city }}, {{ $selectedWorkOrder->address->state }} {{ $selectedWorkOrder->address->postal_code }}</span>
                            @else
                                <span class="text-slate-500 mt-0.5 block">No address specified</span>
                            @endif
                        </div>
                        @if ($selectedWorkOrder->equipment)
                            <div class="sm:col-span-2 pt-2 border-t border-slate-200/60">
                                <span class="text-slate-400 font-semibold uppercase block tracking-wider">Equipment Asset</span>
                                <span class="font-bold text-slate-800 text-sm block">{{ $selectedWorkOrder->equipment->name }} ({{ $selectedWorkOrder->equipment->model_number ?? 'No model' }})</span>
                                <span class="text-slate-500">Serial: {{ $selectedWorkOrder->equipment->serial_number ?? 'N/A' }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($selectedWorkOrder->description)
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Job Description</span>
                            <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-100">{{ $selectedWorkOrder->description }}</p>
                        </div>
                    @endif

                    <!-- Work Order Entries (Labour & Notes) -->
                    <div class="space-y-4 pt-2 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Work Performed & Job Notes</span>
                            <span class="text-xs text-slate-500">{{ $selectedWorkOrder->entries->count() }} entries</span>
                        </div>

                        <!-- Existing Entries Timeline -->
                        @if ($selectedWorkOrder->entries->isNotEmpty())
                            <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                                @foreach ($selectedWorkOrder->entries as $entry)
                                    <div class="p-3 rounded-xl border text-xs {{ $entry->type === 'labour' ? 'bg-blue-50/30 border-blue-100' : 'bg-slate-50 border-slate-200' }}">
                                        <div class="flex items-center justify-between text-slate-500 mb-1">
                                            <span class="font-bold uppercase tracking-wider {{ $entry->type === 'labour' ? 'text-blue-700' : 'text-slate-700' }}">
                                                {{ $entry->type === 'labour' ? 'Labour (' . $entry->minutes . ' mins)' : 'Job Note' }}
                                            </span>
                                            <span>{{ $entry->created_at->format('M d, g:i A') }}</span>
                                        </div>
                                        <p class="text-slate-800">{{ $entry->description }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-xs text-slate-400 italic bg-slate-50 p-3 rounded-xl text-center">
                                No labour entries or notes recorded yet.
                            </div>
                        @endif

                        @if ($selectedWorkOrder->status !== \App\WorkOrderStatus::Completed)
                            <!-- Record Labour Form -->
                            <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2.5">
                                <span class="text-xs font-bold text-slate-700 block">Record Labour Hours</span>
                                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                                    <div class="sm:col-span-1">
                                        <input type="number" wire:model="labourMinutes" placeholder="Minutes" class="w-full text-xs rounded-lg border-slate-300 shadow-xs focus:ring-blue-500 focus:border-blue-500" min="1" max="1440">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <input type="text" wire:model="labourDescription" placeholder="Description of work performed..." class="w-full text-xs rounded-lg border-slate-300 shadow-xs focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="sm:col-span-1">
                                        <button wire:click="addLabourEntry" class="w-full h-full py-1.5 px-3 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs transition">
                                            Add Labour
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Add Note Form -->
                            <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2.5">
                                <span class="text-xs font-bold text-slate-700 block">Add Job Note / Issue Encountered</span>
                                <div class="flex gap-2">
                                    <input type="text" wire:model="noteDescription" placeholder="Enter note or issue details..." class="flex-1 text-xs rounded-lg border-slate-300 shadow-xs focus:ring-blue-500 focus:border-blue-500">
                                    <button wire:click="addNoteEntry" class="py-1.5 px-3 rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold text-xs transition">
                                        Save Note
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Service Completion Section -->
                    <div class="pt-4 border-t border-slate-100 space-y-3">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider block">Service Completion Report</span>

                        @if ($selectedWorkOrder->status === \App\WorkOrderStatus::Completed)
                            <div class="bg-emerald-50/70 p-4 rounded-xl border border-emerald-200 text-xs text-slate-700 space-y-1">
                                <span class="font-bold text-emerald-800 block">Submitted Completion Notes:</span>
                                <p>{{ $selectedWorkOrder->completion_notes }}</p>
                            </div>
                        @else
                            <div class="space-y-2">
                                <textarea wire:model="completionNotes" rows="3" placeholder="Enter service completion notes, diagnostics summary, and resolution details before completing..." class="w-full text-xs rounded-xl border-slate-300 shadow-xs focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                                @error('completionNotes') <span class="text-xs text-rose-600 block">{{ $message }}</span> @enderror

                                <button wire:click="completeWorkOrder" class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm flex items-center justify-center gap-2 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Submit Completion Report & Mark Finished
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
