<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithServiceFlowFilters;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingWorkOrdersWidget extends TableWidget
{
    use InteractsWithServiceFlowFilters;

    protected static ?string $heading = 'Upcoming schedule';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->isDispatcher() || $user->isServiceManager() || $user->hasPlatformRole(UserRole::PlatformAdmin));
    }

    public function table(Table $table): Table
    {
        $query = $this->dashboardQuery(WorkOrder::class, 'scheduled_start_at')
            ->whereNotNull('scheduled_start_at')
            ->where('scheduled_start_at', '>=', now())
            ->with(['customer:id,name', 'assignedTo:id,name']);

        /** @var User|null $user */
        $user = auth()->user();

        if ($user && $user->isTechnician() && ! $user->isOrganizationAdmin() && ! $user->isDispatcher()) {
            $query->where('assigned_to_user_id', $user->id);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('scheduled_start_at')->label('Start')->dateTime()->sortable(),
                TextColumn::make('work_order_number')->searchable(),
                TextColumn::make('title')->limit(40),
                TextColumn::make('customer.name')->label('Customer'),
                TextColumn::make('assignedTo.name')->label('Technician')->placeholder('Unassigned'),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('scheduled_start_at')
            ->paginated([5]);
    }
}
