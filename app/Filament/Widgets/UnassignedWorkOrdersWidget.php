<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithServiceFlowFilters;
use App\Models\User;
use App\Models\WorkOrder;
use App\WorkOrderStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UnassignedWorkOrdersWidget extends TableWidget
{
    use InteractsWithServiceFlowFilters;

    protected static ?string $heading = 'Unassigned work orders awaiting dispatch';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->isDispatcher()
            || $user->isServiceManager()
            || $user->isOrganizationAdmin();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->dashboardQuery(WorkOrder::class, 'created_at')
                    ->whereNull('assigned_to_user_id')
                    ->whereNotIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Cancelled->value])
                    ->with(['customer', 'branch'])
            )
            ->columns([
                TextColumn::make('created_at')->label('Requested')->dateTime()->sortable(),
                TextColumn::make('work_order_number')->label('Work Order #')->searchable()->sortable(),
                TextColumn::make('title')->label('Title')->limit(35),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('branch.name')->label('Branch')->placeholder('All branches'),
                TextColumn::make('scheduled_start_at')->label('Target Date')->dateTime()->placeholder('Unscheduled'),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5]);
    }
}
