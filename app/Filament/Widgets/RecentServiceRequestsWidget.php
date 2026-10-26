<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithServiceFlowFilters;
use App\Models\ServiceRequest;
use App\Models\User;
use App\UserRole;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentServiceRequestsWidget extends TableWidget
{
    use InteractsWithServiceFlowFilters;

    protected static ?string $heading = 'Recent service requests';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->isDispatcher() || $user->isServiceManager() || $user->hasPlatformRole(UserRole::PlatformAdmin));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->dashboardQuery(ServiceRequest::class, 'created_at')->with('customer:id,name'))
            ->columns([
                TextColumn::make('request_number')->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('customer.name')->label('Customer'),
                TextColumn::make('title')->limit(45),
                TextColumn::make('priority')->badge(),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5]);
    }
}
