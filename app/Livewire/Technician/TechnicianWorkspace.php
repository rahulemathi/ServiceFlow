<?php

namespace App\Livewire\Technician;

use App\Models\Organization;
use App\Models\TechnicianAvailability;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEntry;
use App\WorkOrderStatus;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.technician')]
class TechnicianWorkspace extends Component
{
    public string $activeTab = 'all';

    public ?int $selectedWorkOrderId = null;

    // Time entry form
    public string $labourMinutes = '60';

    public string $labourDescription = '';

    // Note form
    public string $noteDescription = '';

    // Completion form
    public string $completionNotes = '';

    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    public function mount(?WorkOrder $workOrder = null): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->isTechnician()) {
            abort(403, 'Access denied. The technician workspace is only accessible by assigned technicians.');
        }

        if ($workOrder && $workOrder->exists) {
            $this->selectWorkOrder($workOrder->id);
        }
    }

    public function selectWorkOrder(int $id): void
    {
        $this->resetFeedback();

        /** @var User $user */
        $user = auth()->user();
        $tenant = $this->getTenant();

        $workOrder = WorkOrder::query()
            ->where('organization_id', $tenant?->getKey())
            ->where('assigned_to_user_id', $user->id)
            ->with(['customer', 'address', 'equipment', 'entries.user'])
            ->find($id);

        if (! $workOrder) {
            abort(403, 'Unauthorized access. You may only view work orders explicitly assigned to you.');
        }

        $this->selectedWorkOrderId = $workOrder->id;
        $this->completionNotes = $workOrder->completion_notes ?? '';
    }

    public function closeWorkOrderDetails(): void
    {
        $this->selectedWorkOrderId = null;
        $this->resetFeedback();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetFeedback();
    }

    public function transitionStatus(string $statusValue): void
    {
        $this->resetFeedback();
        $workOrder = $this->getSelectedWorkOrder();

        if (! $workOrder) {
            return;
        }

        if ($workOrder->status === WorkOrderStatus::Completed) {
            $this->errorMessage = 'Completed work orders cannot be modified.';

            return;
        }

        $targetStatus = WorkOrderStatus::tryFrom($statusValue);
        if (! $targetStatus) {
            $this->errorMessage = 'Invalid status requested.';

            return;
        }

        try {
            $workOrder->transitionTo($targetStatus);
            $this->feedbackMessage = "Work order updated to status: {$targetStatus->value}.";
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?? 'Unable to change status.';
        }
    }

    public function addLabourEntry(): void
    {
        $this->resetFeedback();
        $workOrder = $this->getSelectedWorkOrder();

        if (! $workOrder) {
            return;
        }

        if ($workOrder->status === WorkOrderStatus::Completed) {
            $this->errorMessage = 'Cannot record labour on completed work orders.';

            return;
        }

        $this->validate([
            'labourMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'labourDescription' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        WorkOrderEntry::create([
            'organization_id' => $workOrder->organization_id,
            'work_order_id' => $workOrder->id,
            'user_id' => auth()->id(),
            'type' => 'labour',
            'minutes' => (int) $this->labourMinutes,
            'description' => $this->labourDescription,
        ]);

        $this->labourDescription = '';
        $this->feedbackMessage = 'Labour entry recorded successfully.';
    }

    public function addNoteEntry(): void
    {
        $this->resetFeedback();
        $workOrder = $this->getSelectedWorkOrder();

        if (! $workOrder) {
            return;
        }

        if ($workOrder->status === WorkOrderStatus::Completed) {
            $this->errorMessage = 'Cannot add notes to completed work orders.';

            return;
        }

        $this->validate([
            'noteDescription' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        WorkOrderEntry::create([
            'organization_id' => $workOrder->organization_id,
            'work_order_id' => $workOrder->id,
            'user_id' => auth()->id(),
            'type' => 'note',
            'description' => $this->noteDescription,
        ]);

        $this->noteDescription = '';
        $this->feedbackMessage = 'Job note added.';
    }

    public function completeWorkOrder(): void
    {
        $this->resetFeedback();
        $workOrder = $this->getSelectedWorkOrder();

        if (! $workOrder) {
            return;
        }

        if ($workOrder->status === WorkOrderStatus::Completed) {
            $this->errorMessage = 'This work order is already completed.';

            return;
        }

        $this->validate([
            'completionNotes' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'completionNotes.required' => 'Completion notes are required before marking the work order as completed.',
            'completionNotes.min' => 'Please provide at least 5 characters of completion notes describing the work done.',
        ]);

        try {
            $workOrder->completion_notes = $this->completionNotes;
            $workOrder->save();
            $workOrder->transitionTo(WorkOrderStatus::Completed);

            $this->feedbackMessage = 'Service completion report submitted and work order completed!';
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?? 'Unable to complete work order.';
        }
    }

    protected function getSelectedWorkOrder(): ?WorkOrder
    {
        if (! $this->selectedWorkOrderId) {
            return null;
        }

        /** @var User $user */
        $user = auth()->user();
        $tenant = $this->getTenant();

        $workOrder = WorkOrder::query()
            ->where('organization_id', $tenant?->getKey())
            ->where('assigned_to_user_id', $user->id)
            ->with(['customer', 'address', 'equipment', 'entries.user'])
            ->find($this->selectedWorkOrderId);

        if (! $workOrder) {
            abort(403, 'You are not authorized to view or edit this work order.');
        }

        return $workOrder;
    }

    protected function getTenant(): ?Organization
    {
        /** @var User $user */
        $user = auth()->user();

        return Filament::getTenant()
            ?? $user->currentMembership()?->organization
            ?? $user->memberships()->where('status', 'active')->first()?->organization;
    }

    protected function resetFeedback(): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $tenant = $this->getTenant();
        $today = Carbon::today();

        $baseQuery = WorkOrder::query()
            ->where('organization_id', $tenant?->getKey())
            ->where('assigned_to_user_id', $user->id)
            ->with(['customer', 'address', 'equipment']);

        // Tab Filtering
        $workOrders = (clone $baseQuery)
            ->when($this->activeTab === 'today', function (Builder $query) use ($today): void {
                $query->whereDate('scheduled_start_at', $today);
            })
            ->when($this->activeTab === 'in_progress', function (Builder $query): void {
                $query->whereIn('status', [WorkOrderStatus::InProgress->value, WorkOrderStatus::OnHold->value]);
            })
            ->when($this->activeTab === 'completed', function (Builder $query): void {
                $query->where('status', WorkOrderStatus::Completed->value);
            })
            ->when($this->activeTab === 'all', function (Builder $query): void {
                $query->whereNotIn('status', [WorkOrderStatus::Cancelled->value]);
            })
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 1 WHEN status = 'scheduled' THEN 2 WHEN status = 'on_hold' THEN 3 WHEN status = 'new' THEN 4 ELSE 5 END")
            ->orderBy('scheduled_start_at')
            ->get();

        // Metrics for summary cards
        $metrics = [
            'total' => (clone $baseQuery)->count(),
            'today' => (clone $baseQuery)->whereDate('scheduled_start_at', $today)->count(),
            'in_progress' => (clone $baseQuery)->where('status', WorkOrderStatus::InProgress->value)->count(),
            'on_hold' => (clone $baseQuery)->where('status', WorkOrderStatus::OnHold->value)->count(),
            'completed' => (clone $baseQuery)->where('status', WorkOrderStatus::Completed->value)->count(),
            'overdue' => (clone $baseQuery)
                ->whereNotIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Cancelled->value])
                ->where('scheduled_end_at', '<', now())
                ->count(),
        ];

        // Technician Availability schedules
        $availabilities = TechnicianAvailability::query()
            ->where('organization_id', $tenant?->getKey())
            ->where('user_id', $user->id)
            ->orderBy('day_of_week')
            ->get();

        return view('livewire.technician.workspace', [
            'workOrders' => $workOrders,
            'selectedWorkOrder' => $this->getSelectedWorkOrder(),
            'metrics' => $metrics,
            'availabilities' => $availabilities,
            'organization' => $tenant,
        ]);
    }
}
