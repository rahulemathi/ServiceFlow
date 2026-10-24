<?php

namespace App\Filament\Resources\WorkOrders;

use App\Filament\Resources\Support\FilamentOptions;
use App\Filament\Resources\WorkOrders\Pages\ManageWorkOrders;
use App\Models\User;
use App\Models\WorkOrder;
use App\WorkOrderStatus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'Service operations';

    protected static ?string $recordTitleAttribute = 'work_order_number';

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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        /** @var User|null $user */
        $user = Filament::auth()->user();

        if ($user && $user->isTechnician() && ! $user->isOrganizationAdmin() && ! $user->isDispatcher()) {
            $query->where('assigned_to_user_id', $user->id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')->options(FilamentOptions::customers())->searchable()->required(),
                Select::make('branch_id')->options(FilamentOptions::branches())->searchable()->placeholder('No branch'),
                Select::make('customer_address_id')->options(FilamentOptions::addresses())->searchable()->placeholder('No address'),
                Select::make('equipment_id')->options(FilamentOptions::equipment())->searchable()->placeholder('No equipment'),
                Select::make('assigned_to_user_id')
                    ->label('Technician')
                    ->options(FilamentOptions::technicians())
                    ->searchable()
                    ->placeholder('Unassigned')
                    ->disabled(fn (): bool => ! (Filament::auth()->user()?->isOrganizationAdmin() || Filament::auth()->user()?->isDispatcher())),
                TextInput::make('title')->required()->maxLength(255),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                DateTimePicker::make('scheduled_start_at')->seconds(false),
                DateTimePicker::make('scheduled_end_at')->after('scheduled_start_at')->seconds(false),
                Textarea::make('completion_notes')->rows(3)->columnSpanFull(),
                Repeater::make('entries')
                    ->relationship()
                    ->schema([
                        Select::make('type')
                            ->options(['note' => 'Note', 'labor' => 'Labor', 'part' => 'Part'])
                            ->required(),
                        TextInput::make('description')->required()->maxLength(255),
                        TextInput::make('minutes')->numeric()->minValue(0),
                        TextInput::make('quantity')->numeric()->minValue(0)->step(0.01),
                        TextInput::make('unit_price')->numeric()->minValue(0)->step(0.01),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('work_order_number')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->limit(50),
                TextColumn::make('customer.name')->searchable()->sortable(),
                TextColumn::make('assignedTo.name')->label('Technician')->placeholder('Unassigned')->sortable(),
                TextColumn::make('scheduled_start_at')->dateTime()->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('branch_id')->options(FilamentOptions::branches()),
                SelectFilter::make('status')->options(FilamentOptions::enum(WorkOrderStatus::cases())),
            ])
            ->recordActions([
                Action::make('schedule')
                    ->schema([
                        DateTimePicker::make('scheduled_start_at')->required()->seconds(false),
                        DateTimePicker::make('scheduled_end_at')->required()->after('scheduled_start_at')->seconds(false),
                    ])
                    ->visible(fn (WorkOrder $record): bool => $record->status === WorkOrderStatus::New)
                    ->action(function (WorkOrder $record, array $data): void {
                        $record->fill($data)->save();
                        $record->transitionTo(WorkOrderStatus::Scheduled);
                    }),
                Action::make('start')
                    ->visible(fn (WorkOrder $record): bool => $record->status === WorkOrderStatus::Scheduled)
                    ->action(fn (WorkOrder $record) => $record->transitionTo(WorkOrderStatus::InProgress)),
                Action::make('hold')
                    ->label('Put on hold')
                    ->visible(fn (WorkOrder $record): bool => $record->status === WorkOrderStatus::InProgress)
                    ->action(fn (WorkOrder $record) => $record->transitionTo(WorkOrderStatus::OnHold)),
                Action::make('resume')
                    ->visible(fn (WorkOrder $record): bool => $record->status === WorkOrderStatus::OnHold)
                    ->action(fn (WorkOrder $record) => $record->transitionTo(WorkOrderStatus::InProgress)),
                Action::make('cancel')
                    ->requiresConfirmation()
                    ->visible(fn (WorkOrder $record): bool => in_array($record->status, [
                        WorkOrderStatus::New,
                        WorkOrderStatus::Scheduled,
                        WorkOrderStatus::OnHold,
                    ], true))
                    ->action(fn (WorkOrder $record) => $record->transitionTo(WorkOrderStatus::Cancelled)),
                Action::make('complete')
                    ->schema([Textarea::make('completion_notes')->rows(3)])
                    ->visible(fn (WorkOrder $record): bool => $record->status === WorkOrderStatus::InProgress)
                    ->action(function (WorkOrder $record, array $data): void {
                        $record->completion_notes = $data['completion_notes'] ?? null;
                        $record->save();
                        $record->transitionTo(WorkOrderStatus::Completed);
                    }),
                EditAction::make()->visible(fn (WorkOrder $record): bool => ! in_array(
                    $record->status,
                    [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled],
                    true,
                )),
            ])
            ->defaultSort('scheduled_start_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWorkOrders::route('/'),
        ];
    }
}
