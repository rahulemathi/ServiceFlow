<?php

namespace App\Filament\Resources\CustomerAddresses;

use App\Filament\Resources\CustomerAddresses\Pages\ManageCustomerAddresses;
use App\Filament\Resources\Support\FilamentOptions;
use App\Models\CustomerAddress;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CustomerAddressResource extends Resource
{
    protected static ?string $model = CustomerAddress::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $recordTitleAttribute = 'address_line_1';

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->isDispatcher());
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->isDispatcher());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')->options(FilamentOptions::customers())->searchable()->required(),
                Select::make('branch_id')->options(FilamentOptions::branches())->searchable()->placeholder('No branch'),
                TextInput::make('label')->maxLength(100),
                TextInput::make('address_line_1')->required()->maxLength(255),
                TextInput::make('address_line_2')->maxLength(255),
                TextInput::make('city')->required()->maxLength(100),
                TextInput::make('region')->maxLength(100),
                TextInput::make('postal_code')->maxLength(30),
                TextInput::make('country')->default('India')->required()->maxLength(100),
                Checkbox::make('is_primary'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')->searchable()->sortable(),
                TextColumn::make('label')->placeholder('Service address'),
                TextColumn::make('address_line_1')->searchable(),
                TextColumn::make('city')->searchable()->sortable(),
                TextColumn::make('branch.name')->placeholder('Organization-wide'),
                TextColumn::make('is_primary')->label('Primary')->badge(),
            ])
            ->filters([
                SelectFilter::make('branch_id')->options(FilamentOptions::branches()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('city');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomerAddresses::route('/'),
        ];
    }
}
