<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Support\FilamentOptions;
use App\Filament\Widgets\Concerns\InteractsWithServiceFlowFilters;
use App\Models\User;
use App\Models\WorkOrder;
use App\WorkOrderStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UnassignedWorkOrdersWidget extends TableWidget
{
    use InteractsWithServiceFlowFilters;

    protected static ?string $heading = 'Unassigned work orders awaiting dispatch';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '10s';

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
            ->recordActions([
                Action::make('quickAssign')
                    ->label('Assign')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->schema([
                        Select::make('assigned_to_user_id')
                            ->label('Technician')
                            ->options(FilamentOptions::technicians())
                            ->required()
                            ->searchable(),
                        DateTimePicker::make('scheduled_start_at')
                            ->label('Target Start (Optional)')
                            ->seconds(false),
                        DateTimePicker::make('scheduled_end_at')
                            ->label('Target End (Optional)')
                            ->after('scheduled_start_at')
                            ->seconds(false),
                    ])
                    ->action(function (WorkOrder $record, array $data): void {
                        $updateData = ['assigned_to_user_id' => $data['assigned_to_user_id']];
                        if (! empty($data['scheduled_start_at']) && ! empty($data['scheduled_end_at'])) {
                            $updateData['scheduled_start_at'] = $data['scheduled_start_at'];
                            $updateData['scheduled_end_at'] = $data['scheduled_end_at'];
                            $updateData['status'] = WorkOrderStatus::Scheduled->value;
                        }
                        $record->update($updateData);
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5]);
    }
}
