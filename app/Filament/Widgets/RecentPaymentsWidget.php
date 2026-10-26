<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithServiceFlowFilters;
use App\Models\Payment;
use App\Models\User;
use App\UserRole;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentPaymentsWidget extends TableWidget
{
    use InteractsWithServiceFlowFilters;

    protected static ?string $heading = 'Recent payments received';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->isAccountant()
            || $user->isOrganizationOwner()
            || $user->hasPlatformRole(UserRole::PlatformAdmin);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->dashboardQuery(Payment::class, 'received_at')
                    ->with(['invoice.customer', 'recordedBy'])
            )
            ->columns([
                TextColumn::make('received_at')->label('Received Date')->date()->sortable(),
                TextColumn::make('invoice.invoice_number')->label('Invoice #')->searchable(),
                TextColumn::make('invoice.customer.name')->label('Customer')->searchable(),
                TextColumn::make('amount')->label('Amount')->money('INR')->sortable(),
                TextColumn::make('method')->badge(),
                TextColumn::make('reference')->label('Reference')->placeholder('—'),
                TextColumn::make('recordedBy.name')->label('Recorded By')->placeholder('System'),
            ])
            ->defaultSort('received_at', 'desc')
            ->paginated([5]);
    }
}
