<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithServiceFlowFilters;
use App\Models\MaintenanceContract;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingMaintenanceWidget extends TableWidget
{
    use InteractsWithServiceFlowFilters;

    protected static ?string $heading = 'Upcoming maintenance';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->isDispatcher());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->dashboardQuery(MaintenanceContract::class, 'next_service_at')
                ->whereNotNull('next_service_at')
                ->whereDate('next_service_at', '>=', today())
                ->with(['customer:id,name', 'equipment:id,name']))
            ->columns([
                TextColumn::make('next_service_at')->date()->sortable(),
                TextColumn::make('contract_number')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('customer.name')->label('Customer'),
                TextColumn::make('equipment.name')->label('Equipment')->placeholder('All equipment'),
                TextColumn::make('frequency')->badge(),
            ])
            ->defaultSort('next_service_at')
            ->paginated([5]);
    }
}
