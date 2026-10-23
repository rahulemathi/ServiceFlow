<?php

namespace App\Filament\Widgets;

use App\CommercialStatus;
use App\Filament\Widgets\Concerns\InteractsWithServiceFlowFilters;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use App\ServiceRequestStatus;
use App\WorkOrderStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServiceOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithServiceFlowFilters;

    protected function getStats(): array
    {
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

        return [
            Stat::make('Open service requests', $openRequests),
            Stat::make('Active work orders', $activeWorkOrders),
            Stat::make('Scheduled jobs', $scheduledWorkOrders),
            Stat::make('Completed jobs', $completedWorkOrders),
            Stat::make('Outstanding payments', number_format($outstandingInvoices, 2).' INR'),
        ];
    }
}
