<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithServiceFlowFilters;
use App\Models\TechnicianAvailability;
use App\Models\WorkOrder;
use App\WorkOrderStatus;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class TechnicianUtilizationWidget extends StatsOverviewWidget
{
    use InteractsWithServiceFlowFilters;

    protected function getStats(): array
    {
        $tenant = $this->dashboardTenant();
        $filters = $this->validatedDashboardFilters($tenant);
        $rangeStart = CarbonImmutable::parse($filters['startDate'] ?? today()->toDateString())->startOfDay();
        $rangeEnd = CarbonImmutable::parse($filters['endDate'] ?? today()->toDateString())->endOfDay();

        $availability = TechnicianAvailability::query()
            ->where('organization_id', $tenant->getKey())
            ->whereHas('technician.memberships', function (Builder $query) use ($tenant): void {
                $query
                    ->where('organization_id', $tenant->getKey())
                    ->where('status', 'active')
                    ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('role', 'technician'));
            })
            ->get(['user_id', 'day_of_week', 'starts_at', 'ends_at']);

        if ($availability->isEmpty()) {
            return [
                Stat::make('Technician utilization', 'Not configured')
                    ->description('Add weekly availability for technicians to calculate utilization.'),
            ];
        }

        $availableMinutes = 0;
        $day = $rangeStart;

        while ($day->lessThanOrEqualTo($rangeEnd)) {
            foreach ($availability->where('day_of_week', $day->dayOfWeekIso) as $shift) {
                $availableMinutes += CarbonImmutable::parse($shift->starts_at)
                    ->diffInMinutes(CarbonImmutable::parse($shift->ends_at));
            }

            $day = $day->addDay();
        }

        if ($availableMinutes <= 0) {
            return [
                Stat::make('Technician utilization', 'Not available')
                    ->description('No availability is configured within the selected dates.'),
            ];
        }

        $workOrders = WorkOrder::query()
            ->where('organization_id', $tenant->getKey())
            ->whereNotNull('assigned_to_user_id')
            ->whereNotNull('scheduled_start_at')
            ->whereNotNull('scheduled_end_at')
            ->where('scheduled_start_at', '<', $rangeEnd)
            ->where('scheduled_end_at', '>', $rangeStart)
            ->whereIn('assigned_to_user_id', $availability->pluck('user_id')->unique())
            ->whereIn('status', [
                WorkOrderStatus::Scheduled->value,
                WorkOrderStatus::InProgress->value,
                WorkOrderStatus::Completed->value,
            ])
            ->when($filters['branchId'] ?? null, fn (Builder $query, int|string $branchId): Builder => $query->where('branch_id', $branchId))
            ->get(['assigned_to_user_id', 'scheduled_start_at', 'scheduled_end_at']);

        $scheduledMinutes = $workOrders->sum(function (WorkOrder $workOrder) use ($rangeStart, $rangeEnd): int {
            $scheduledStart = CarbonImmutable::parse($workOrder->scheduled_start_at);
            $scheduledEnd = CarbonImmutable::parse($workOrder->scheduled_end_at);
            $overlapStart = $scheduledStart->greaterThan($rangeStart) ? $scheduledStart : $rangeStart;
            $overlapEnd = $scheduledEnd->lessThan($rangeEnd) ? $scheduledEnd : $rangeEnd;

            return max(0, (int) $overlapStart->diffInMinutes($overlapEnd, false));
        });

        $utilization = round($scheduledMinutes / $availableMinutes * 100, 1);

        return [
            Stat::make('Technician utilization', $utilization.'%')
                ->description(number_format($scheduledMinutes / 60, 1).' scheduled / '.number_format($availableMinutes / 60, 1).' available hours'),
        ];
    }
}
