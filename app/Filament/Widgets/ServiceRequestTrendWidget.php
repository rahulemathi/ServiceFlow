<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithServiceFlowFilters;
use App\Models\ServiceRequest;
use App\Models\User;
use App\UserRole;
use Filament\Widgets\ChartWidget;

class ServiceRequestTrendWidget extends ChartWidget
{
    use InteractsWithServiceFlowFilters;

    protected ?string $heading = 'Service request trend';

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->isDispatcher() || $user->isServiceManager() || $user->hasPlatformRole(UserRole::PlatformAdmin));
    }

    protected function getData(): array
    {
        $requestsByDay = $this->dashboardQuery(ServiceRequest::class, 'created_at')
            ->selectRaw('DATE(created_at) as request_date, COUNT(*) as aggregate')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('request_date')
            ->pluck('aggregate', 'request_date');

        return [
            'labels' => $requestsByDay->keys()->all(),
            'datasets' => [[
                'label' => 'Service requests',
                'data' => $requestsByDay->values()->map(fn (int|string $count): int => (int) $count)->all(),
                'borderColor' => '#3b82f6',
                'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                'fill' => true,
                'tension' => 0.3,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
