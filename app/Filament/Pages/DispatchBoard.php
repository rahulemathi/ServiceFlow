<?php

namespace App\Filament\Pages;

use App\Models\Organization;
use App\Models\TechnicianAvailability;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use App\WorkOrderStatus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class DispatchBoard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Service operations';

    protected static ?string $navigationLabel = 'Dispatch Board';

    protected static ?string $title = 'Dispatch & Scheduling Board';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.dispatch-board';

    public ?int $selectedBranchId = null;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return $user->isOrganizationAdmin() || $user->isDispatcher() || $user->isServiceManager();
    }

    /**
     * @return Collection<int, User>
     */
    public function getTechniciansProperty(): Collection
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Organization) {
            return new Collection;
        }

        /** @var User $currentUser */
        $currentUser = Filament::auth()->user();

        return User::query()
            ->whereHas('memberships', function (Builder $query) use ($tenant, $currentUser): void {
                $query->where('organization_id', $tenant->id)
                    ->where('status', 'active')
                    ->whereHas('roles', fn ($roleQ) => $roleQ->where('role', UserRole::Technician->value))
                    ->when($this->selectedBranchId, function (Builder $branchQ): void {
                        $branchQ->whereHas('branches', fn ($b) => $b->where('branches.id', $this->selectedBranchId));
                    })
                    ->when(! $currentUser->isOrganizationAdmin() && ! empty($currentUser->permittedBranchIds($tenant)), function (Builder $permQ) use ($currentUser, $tenant): void {
                        $permQ->whereHas('branches', fn ($b) => $b->whereIn('branches.id', $currentUser->permittedBranchIds($tenant)));
                    });
            })
            ->with([
                'assignedWorkOrders' => function ($query) use ($tenant): void {
                    $query->where('organization_id', $tenant->id)
                        ->whereNotIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Cancelled->value])
                        ->orderBy('scheduled_start_at');
                },
            ])
            ->get();
    }

    /**
     * @return Collection<int, WorkOrder>
     */
    public function getUnassignedWorkOrdersProperty(): Collection
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Organization) {
            return new Collection;
        }

        /** @var User $currentUser */
        $currentUser = Filament::auth()->user();

        return WorkOrder::query()
            ->where('organization_id', $tenant->id)
            ->whereNull('assigned_to_user_id')
            ->whereNotIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Cancelled->value])
            ->when($this->selectedBranchId, fn ($q) => $q->where('branch_id', $this->selectedBranchId))
            ->when(! $currentUser->isOrganizationAdmin() && ! empty($currentUser->permittedBranchIds($tenant)), function (Builder $permQ) use ($currentUser, $tenant): void {
                $permQ->where(function (Builder $nested) use ($currentUser, $tenant): void {
                    $nested->whereNull('branch_id')
                        ->orWhereIn('branch_id', $currentUser->permittedBranchIds($tenant));
                });
            })
            ->with(['customer', 'branch'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array<int, array{technician: User, todayCount: int, weekCount: int, availabilities: Collection<int, TechnicianAvailability>, activeOrders: Collection<int, WorkOrder>}>
     */
    public function getTechnicianSchedulesProperty(): array
    {
        $tenant = Filament::getTenant();
        $orgId = $tenant instanceof Organization ? $tenant->id : 0;
        $today = Carbon::today();
        $endOfWeek = Carbon::today()->endOfWeek();

        $result = [];

        foreach ($this->technicians as $tech) {
            /** @var Collection<int, WorkOrder> $assigned */
            $assigned = $tech->assignedWorkOrders;

            $todayCount = $assigned->filter(function (WorkOrder $wo) use ($today): bool {
                return $wo->scheduled_start_at !== null && $wo->scheduled_start_at->isSameDay($today);
            })->count();

            $weekCount = $assigned->filter(function (WorkOrder $wo) use ($today, $endOfWeek): bool {
                return $wo->scheduled_start_at !== null
                    && $wo->scheduled_start_at->gte($today)
                    && $wo->scheduled_start_at->lte($endOfWeek);
            })->count();

            $availabilities = TechnicianAvailability::query()
                ->where('organization_id', $orgId)
                ->where('user_id', $tech->id)
                ->orderBy('day_of_week')
                ->get();

            $result[] = [
                'technician' => $tech,
                'todayCount' => $todayCount,
                'weekCount' => $weekCount,
                'availabilities' => $availabilities,
                'activeOrders' => $assigned,
            ];
        }

        return $result;
    }

    public function assignWorkOrderAction(): Action
    {
        return Action::make('assignWorkOrder')
            ->label('Assign Work Order')
            ->modalHeading('Dispatch Work Order to Technician')
            ->schema([
                Select::make('work_order_id')
                    ->label('Work Order')
                    ->options($this->unassignedWorkOrders->pluck('work_order_number', 'id')->map(function ($num, $id) {
                        $wo = $this->unassignedWorkOrders->firstWhere('id', $id);

                        return "{$num} – {$wo?->title}";
                    }))
                    ->required()
                    ->searchable(),
                Select::make('technician_id')
                    ->label('Assign Technician')
                    ->options($this->technicians->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Toggle::make('has_overlap_override')
                    ->label('Override schedule conflict if technician is busy')
                    ->live(),
                TextInput::make('overlap_override_reason')
                    ->label('Override Reason')
                    ->visible(fn (Get $get): bool => (bool) $get('has_overlap_override')),
            ])
            ->action(function (array $data): void {
                $tenant = Filament::getTenant();
                $workOrder = WorkOrder::query()
                    ->where('organization_id', $tenant?->getKey())
                    ->findOrFail($data['work_order_id']);

                $workOrder->update([
                    'assigned_to_user_id' => $data['technician_id'],
                    'has_overlap_override' => (bool) ($data['has_overlap_override'] ?? false),
                    'overlap_override_reason' => $data['overlap_override_reason'] ?? null,
                    'overlap_override_by_id' => ! empty($data['has_overlap_override']) ? Filament::auth()->id() : null,
                ]);

                Notification::make()
                    ->title("Work order {$workOrder->work_order_number} dispatched.")
                    ->success()
                    ->send();
            });
    }
}
