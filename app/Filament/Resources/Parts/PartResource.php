<?php

namespace App\Filament\Resources\Parts;

use App\Filament\Resources\Parts\Pages\ManageParts;
use App\Filament\Resources\Support\FilamentOptions;
use App\Models\Part;
use App\Models\StockLocation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PartResource extends Resource
{
    protected static ?string $model = Part::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory & suppliers';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->label('SKU / Part Code')
                    ->required()
                    ->maxLength(50),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('cost_price')
                    ->numeric()
                    ->prefix('INR')
                    ->minValue(0)
                    ->step(0.01)
                    ->default(0)
                    ->required(),
                TextInput::make('selling_price')
                    ->numeric()
                    ->prefix('INR')
                    ->minValue(0)
                    ->step(0.01)
                    ->default(0)
                    ->required(),
                TextInput::make('unit')
                    ->default('piece')
                    ->required()
                    ->maxLength(20),
                TextInput::make('min_stock_level')
                    ->label('Min Alert Level')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('total_stock')
                    ->label('Total Stock')
                    ->state(fn (Part $record): string => "{$record->totalStock()} {$record->unit}")
                    ->badge()
                    ->color(fn (Part $record): string => $record->isLowStock() ? 'danger' : 'success'),
                TextColumn::make('cost_price')->money('INR')->sortable(),
                TextColumn::make('selling_price')->money('INR')->sortable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active Status'),
            ])
            ->recordActions([
                Action::make('adjustStock')
                    ->label('Adjust Stock')
                    ->icon(Heroicon::OutlinedArrowsUpDown)
                    ->schema([
                        Select::make('stock_location_id')
                            ->label('Location')
                            ->options(FilamentOptions::stockLocations())
                            ->required()
                            ->searchable(),
                        TextInput::make('quantity_delta')
                            ->label('Quantity to add/subtract (e.g. +10 or -5)')
                            ->numeric()
                            ->step(0.01)
                            ->required(),
                        TextInput::make('notes')
                            ->maxLength(255)
                            ->placeholder('Cycle count adjustment, damaged item, etc.'),
                    ])
                    ->action(function (Part $record, array $data): void {
                        $tenant = Filament::getTenant();
                        /** @var StockLocation $location */
                        $location = StockLocation::query()
                            ->where('organization_id', $tenant?->getKey())
                            ->findOrFail($data['stock_location_id']);

                        $record->adjustStock($location, (float) $data['quantity_delta'], $data['notes'] ?? null);

                        Notification::make()->title('Stock updated successfully.')->success()->send();
                    }),
                Action::make('transferStock')
                    ->label('Transfer')
                    ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
                    ->schema([
                        Select::make('source_location_id')
                            ->label('From Location')
                            ->options(FilamentOptions::stockLocations())
                            ->required()
                            ->searchable(),
                        Select::make('destination_location_id')
                            ->label('To Location')
                            ->options(FilamentOptions::stockLocations())
                            ->required()
                            ->searchable(),
                        TextInput::make('quantity')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required(),
                        TextInput::make('notes')
                            ->maxLength(255)
                            ->placeholder('Transfer to technician van, branch transfer, etc.'),
                    ])
                    ->action(function (Part $record, array $data): void {
                        $tenant = Filament::getTenant();
                        /** @var StockLocation $source */
                        $source = StockLocation::query()
                            ->where('organization_id', $tenant?->getKey())
                            ->findOrFail($data['source_location_id']);
                        /** @var StockLocation $dest */
                        $dest = StockLocation::query()
                            ->where('organization_id', $tenant?->getKey())
                            ->findOrFail($data['destination_location_id']);

                        $record->transferStock($source, $dest, (float) $data['quantity'], $data['notes'] ?? null);

                        Notification::make()->title('Stock transferred successfully.')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageParts::route('/'),
        ];
    }
}
