<?php

namespace App\Filament\Resources\Equipment;

use App\Filament\Resources\Equipment\Pages\ManageEquipment;
use App\Filament\Resources\Support\FilamentOptions;
use App\Models\Equipment;
use App\Models\User;
use App\UserRole;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EquipmentResource extends Resource
{
    protected static ?string $model = Equipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->isDispatcher() || $user->isServiceManager() || $user->hasPlatformRole(UserRole::PlatformAdmin));
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->isDispatcher() || $user->isServiceManager() || $user->hasPlatformRole(UserRole::PlatformAdmin));
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->hasPlatformRole(UserRole::PlatformAdmin));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                Select::make('customer_id')->options(FilamentOptions::customers())->searchable()->required(),
                Select::make('branch_id')->options(FilamentOptions::branches())->searchable()->placeholder('No branch'),
                TextInput::make('manufacturer')->maxLength(255),
                TextInput::make('model')->maxLength(255),
                TextInput::make('serial_number')->maxLength(255),
                DatePicker::make('installed_at'),
                DatePicker::make('warranty_expires_at'),
                Select::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive', 'retired' => 'Retired'])
                    ->default('active')
                    ->required(),
                Textarea::make('notes')->rows(4)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('manufacturer')->toggleable(),
                TextColumn::make('model')->searchable()->toggleable(),
                TextColumn::make('serial_number')->searchable(),
                TextColumn::make('customer.name')->searchable()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('warranty_expires_at')->date()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')->options(FilamentOptions::branches()),
                SelectFilter::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive', 'retired' => 'Retired']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEquipment::route('/'),
        ];
    }
}
