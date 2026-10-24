<?php

namespace App\Filament\Resources\MaintenanceContracts;

use App\Filament\Resources\MaintenanceContracts\Pages\ManageMaintenanceContracts;
use App\Filament\Resources\Support\FilamentOptions;
use App\MaintenanceContractStatus;
use App\MaintenanceFrequency;
use App\Models\MaintenanceContract;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class MaintenanceContractResource extends Resource
{
    protected static ?string $model = MaintenanceContract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Service operations';

    protected static ?string $recordTitleAttribute = 'contract_number';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->isDispatcher());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')->options(FilamentOptions::customers())->searchable()->required()->live(),
                Select::make('branch_id')->options(FilamentOptions::branches())->searchable()->placeholder('No branch'),
                Select::make('equipment_id')
                    ->options(fn (Get $get): array => FilamentOptions::equipmentForCustomer($get('customer_id')))
                    ->searchable()
                    ->placeholder('No specific equipment'),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('frequency')->options(FilamentOptions::enum(MaintenanceFrequency::cases()))->required(),
                DatePicker::make('starts_at')->required(),
                DatePicker::make('ends_at')->afterOrEqual('starts_at'),
                DatePicker::make('next_service_at'),
                Select::make('status')
                    ->options(FilamentOptions::enum(MaintenanceContractStatus::cases()))
                    ->default(MaintenanceContractStatus::Active->value)
                    ->required(),
                TextInput::make('amount')->numeric()->minValue(0)->step(0.01)->default(0)->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract_number')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('customer.name')->searchable()->sortable(),
                TextColumn::make('equipment.name')->placeholder('All equipment'),
                TextColumn::make('frequency')->badge(),
                TextColumn::make('next_service_at')->date()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('amount')->money('INR')->sortable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')->options(FilamentOptions::branches()),
                SelectFilter::make('status')->options(FilamentOptions::enum(MaintenanceContractStatus::cases())),
                SelectFilter::make('frequency')->options(FilamentOptions::enum(MaintenanceFrequency::cases())),
            ])
            ->recordActions([
                Action::make('generateWorkOrder')
                    ->label('Generate work order')
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->requiresConfirmation()
                    ->visible(fn (MaintenanceContract $record): bool => $record->status === MaintenanceContractStatus::Active)
                    ->action(function (MaintenanceContract $record): void {
                        DB::transaction(function () use ($record): void {
                            $contract = MaintenanceContract::query()
                                ->where('organization_id', Filament::getTenant()?->getKey())
                                ->lockForUpdate()
                                ->findOrFail($record->getKey());

                            if ($contract->status !== MaintenanceContractStatus::Active) {
                                return;
                            }

                            $contract->generateWorkOrder();
                        });
                    }),
                EditAction::make(),
            ])
            ->defaultSort('next_service_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMaintenanceContracts::route('/'),
        ];
    }
}
