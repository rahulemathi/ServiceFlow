<?php

namespace App\Filament\Widgets;

use App\CommercialStatus;
use App\Filament\Widgets\Concerns\InteractsWithServiceFlowFilters;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\ServiceRequestStatus;
use App\UserRole;
use App\WorkOrderStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServiceOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithServiceFlowFilters;

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->isDispatcher() || $user->isServiceManager() || $user->hasPlatformRole(UserRole::PlatformAdmin));
    }

    protected function getStats(): array
    {
        /** @var User|null $user */
        $user = auth()->user();
        $isTechnicianOnly = $user && $user->isTechnician() && ! $user->isOrganizationAdmin() && ! $user->isDispatcher();

        if ($isTechnicianOnly) {
            $myActiveWorkOrders = $this->dashboardQuery(WorkOrder::class, 'created_at')
                ->where('assigned_to_user_id', $user->id)
                ->whereIn('status', [
                    WorkOrderStatus::New->value,
                    WorkOrderStatus::Scheduled->value,
                    WorkOrderStatus::InProgress->value,
                    WorkOrderStatus::OnHold->value,
                ])
                ->count();

            $myScheduledWorkOrders = $this->dashboardQuery(WorkOrder::class, 'scheduled_start_at')
                ->where('assigned_to_user_id', $user->id)
                ->whereNotNull('scheduled_start_at')
                ->whereIn('status', [WorkOrderStatus::Scheduled->value, WorkOrderStatus::InProgress->value])
                ->count();

            $myCompletedWorkOrders = $this->dashboardQuery(WorkOrder::class, 'completed_at')
                ->where('assigned_to_user_id', $user->id)
                ->where('status', WorkOrderStatus::Completed->value)
                ->count();

            return [
                Stat::make('My active work orders', $myActiveWorkOrders),
                Stat::make('My scheduled jobs', $myScheduledWorkOrders),
                Stat::make('My completed jobs', $myCompletedWorkOrders),
            ];
        }

        $openRequests = $this->dashboardQuery(ServiceRequest::class, 'created_at')
            ->whereIn('status', [ServiceRequestStatus::Open->value, ServiceRequestStatus::Reviewing->value])
            ->count();

        $activeWorkOrders = $this->dashboardQuery(WorkOrder::class, 'created_at')
            ->whereIn('status', [
                WorkOrderStatus::New->value,
                WorkOrderStatus::Scheduled->value,
                WorkOrderStatus::InProgress->value,
                WorkOrderStatus::OnHold->value,
            ])
            ->count();

        $scheduledWorkOrders = $this->dashboardQuery(WorkOrder::class, 'scheduled_start_at')
            ->whereNotNull('scheduled_start_at')
            ->whereIn('status', [WorkOrderStatus::Scheduled->value, WorkOrderStatus::InProgress->value])
            ->count();

        $completedWorkOrders = $this->dashboardQuery(WorkOrder::class, 'completed_at')
            ->where('status', WorkOrderStatus::Completed->value)
            ->count();

        $stats = [
            Stat::make('Open service requests', $openRequests),
            Stat::make('Active work orders', $activeWorkOrders),
            Stat::make('Scheduled jobs', $scheduledWorkOrders),
            Stat::make('Completed jobs', $completedWorkOrders),
        ];

        if ($user && $user->isOrganizationAdmin()) {
            $outstandingInvoices = $this->dashboardQuery(Invoice::class, 'issued_at')
                ->whereIn('status', [
                    CommercialStatus::Sent->value,
                    CommercialStatus::PartiallyPaid->value,
                    CommercialStatus::Overdue->value,
                ])
                ->withSum('payments', 'amount')
                ->get(['id', 'total'])
                ->sum(fn (Invoice $invoice): float => max(
                    0,
                    (float) $invoice->total - (float) ($invoice->payments_sum_amount ?? 0),
                ));

            $stats[] = Stat::make('Outstanding payments', number_format($outstandingInvoices, 2).' INR');
        }

        return $stats;
    }
}
