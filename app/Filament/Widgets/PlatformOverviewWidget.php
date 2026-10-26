<?php

namespace App\Filament\Widgets;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use App\WorkOrderStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && $user->hasPlatformRole(UserRole::PlatformAdmin);
    }

    protected function getStats(): array
    {
        $totalOrganizations = Organization::count();
        $totalBranches = Branch::count();
        $totalUsers = User::count();
        $activeWorkOrders = WorkOrder::whereIn('status', [
            WorkOrderStatus::New->value,
            WorkOrderStatus::Scheduled->value,
            WorkOrderStatus::InProgress->value,
            WorkOrderStatus::OnHold->value,
        ])->count();

        return [
            Stat::make('Organizations', $totalOrganizations)
                ->description('Active customer organizations')
                ->color('primary'),

            Stat::make('Total Branches', $totalBranches)
                ->description('Operating service locations')
                ->color('info'),

            Stat::make('Platform Users', $totalUsers)
                ->description('All registered accounts')
                ->color('success'),

            Stat::make('Active Work Orders', $activeWorkOrders)
                ->description('Cross-organization fleet operations')
                ->color('warning'),
        ];
    }
}
