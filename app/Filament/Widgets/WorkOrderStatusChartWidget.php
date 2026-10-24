<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithServiceFlowFilters;
use App\Models\User;
use App\Models\WorkOrder;
use App\WorkOrderStatus;
use Filament\Widgets\ChartWidget;

class WorkOrderStatusChartWidget extends ChartWidget
{
    use InteractsWithServiceFlowFilters;

    protected ?string $heading = 'Work orders by status';

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected function getData(): array
    {
        $query = $this->dashboardQuery(WorkOrder::class, 'created_at');

        /** @var User|null $user */
        $user = auth()->user();

        if ($user && $user->isTechnician() && ! $user->isOrganizationAdmin() && ! $user->isDispatcher()) {
            $query->where('assigned_to_user_id', $user->id);
        }

        $counts = $query
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'labels' => array_map(
                fn (WorkOrderStatus $status): string => str($status->value)->replace('_', ' ')->title()->toString(),
                WorkOrderStatus::cases(),
            ),
            'datasets' => [[
                'label' => 'Work orders',
                'data' => array_map(
                    fn (WorkOrderStatus $status): int => (int) ($counts[$status->value] ?? 0),
                    WorkOrderStatus::cases(),
                ),
                'backgroundColor' => ['#94a3b8', '#3b82f6', '#f59e0b', '#a855f7', '#22c55e', '#ef4444'],
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
