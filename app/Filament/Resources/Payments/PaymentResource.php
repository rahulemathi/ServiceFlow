<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use App\Models\User;
use App\UserRole;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Financials';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->isAccountant() || $user->hasPlatformRole(UserRole::PlatformAdmin));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('received_at')->date()->sortable(),
                TextColumn::make('invoice.invoice_number')->searchable()->sortable(),
                TextColumn::make('invoice.customer.name')->label('Customer')->searchable()->sortable(),
                TextColumn::make('amount')->money('INR')->sortable(),
                TextColumn::make('method')->badge(),
                TextColumn::make('reference')->searchable()->placeholder('—'),
                TextColumn::make('recordedBy.name')->label('Recorded by')->placeholder('System'),
            ])
            ->defaultSort('received_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }
}
