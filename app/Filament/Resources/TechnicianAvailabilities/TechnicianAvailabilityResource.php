<?php

namespace App\Filament\Resources\TechnicianAvailabilities;

use App\Filament\Resources\Support\FilamentOptions;
use App\Filament\Resources\TechnicianAvailabilities\Pages\ManageTechnicianAvailabilities;
use App\Models\TechnicianAvailability;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TechnicianAvailabilityResource extends Resource
{
    protected static ?string $model = TechnicianAvailability::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?string $recordTitleAttribute = 'technician.name';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        /** @var User|null $user */
        $user = Filament::auth()->user();

        if ($user && $user->isTechnician() && ! $user->isOrganizationAdmin() && ! $user->isDispatcher()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Technician')
                    ->options(FilamentOptions::technicians())
                    ->searchable()
                    ->required()
                    ->default(fn (): ?int => Filament::auth()->user()?->id)
                    ->disabled(fn (): bool => ! (Filament::auth()->user()?->isOrganizationAdmin() || Filament::auth()->user()?->isDispatcher())),
                Select::make('day_of_week')->options(FilamentOptions::weekdays())->required(),
                TimePicker::make('starts_at')->seconds(false)->required(),
                TimePicker::make('ends_at')->seconds(false)->after('starts_at')->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('technician.name')
            ->columns([
                TextColumn::make('technician.name')->label('Technician')->searchable()->sortable(),
                TextColumn::make('day_of_week')->formatStateUsing(fn (int $state): string => FilamentOptions::weekdays()[$state]),
                TextColumn::make('starts_at')->time(),
                TextColumn::make('ends_at')->time(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('day_of_week');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTechnicianAvailabilities::route('/'),
        ];
    }
}
