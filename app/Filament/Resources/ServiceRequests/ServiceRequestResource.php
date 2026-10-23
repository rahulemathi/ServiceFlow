<?php

namespace App\Filament\Resources\ServiceRequests;

use App\Filament\Resources\ServiceRequests\Pages\ManageServiceRequests;
use App\Filament\Resources\Support\FilamentOptions;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use App\ServicePriority;
use App\ServiceRequestStatus;
use App\WorkOrderStatus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Service operations';

    protected static ?string $recordTitleAttribute = 'request_number';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')->options(FilamentOptions::customers())->searchable()->required()->live(),
                Select::make('branch_id')->options(FilamentOptions::branches())->searchable()->placeholder('No branch'),
                Select::make('customer_address_id')
                    ->label('Service address')
                    ->options(fn (Get $get): array => FilamentOptions::addressesForCustomer($get('customer_id')))
                    ->searchable()
                    ->placeholder('No specific address'),
                Select::make('equipment_id')
                    ->options(fn (Get $get): array => FilamentOptions::equipmentForCustomer($get('customer_id')))
                    ->searchable()
                    ->placeholder('No equipment'),
                TextInput::make('title')->required()->maxLength(255),
                Textarea::make('description')->required()->rows(4)->columnSpanFull(),
                Select::make('priority')->options(FilamentOptions::enum(ServicePriority::cases()))->default(ServicePriority::Normal->value)->required(),
                DateTimePicker::make('requested_at')->seconds(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_number')->searchable()->sortable(),
                TextColumn::make('customer.name')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->limit(50),
                TextColumn::make('priority')->badge()->color(fn (ServicePriority $state): string => match ($state) {
                    ServicePriority::Urgent => 'danger',
                    ServicePriority::High => 'warning',
                    ServicePriority::Normal => 'primary',
                    ServicePriority::Low => 'gray',
                }),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')->options(FilamentOptions::branches()),
                SelectFilter::make('priority')->options(FilamentOptions::enum(ServicePriority::cases())),
                SelectFilter::make('status')->options(FilamentOptions::enum(ServiceRequestStatus::cases())),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Start review')
                    ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::Open)
                    ->action(fn (ServiceRequest $record) => $record->update(['status' => ServiceRequestStatus::Reviewing])),
                Action::make('convertToWorkOrder')
                    ->label('Convert to work order')
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->visible(fn (ServiceRequest $record): bool => ! $record->workOrder()->exists()
                        && $record->status !== ServiceRequestStatus::Cancelled)
                    ->requiresConfirmation()
                    ->action(function (ServiceRequest $record): void {
                        DB::transaction(function () use ($record): void {
                            $request = ServiceRequest::query()
                                ->where('organization_id', Filament::getTenant()?->getKey())
                                ->lockForUpdate()
                                ->findOrFail($record->getKey());

                            if ($request->workOrder()->exists()) {
                                return;
                            }

                            WorkOrder::create([
                                'organization_id' => $request->organization_id,
                                'branch_id' => $request->branch_id,
                                'service_request_id' => $request->id,
                                'customer_id' => $request->customer_id,
                                'customer_address_id' => $request->customer_address_id,
                                'equipment_id' => $request->equipment_id,
                                'title' => $request->title,
                                'description' => $request->description,
                                'status' => WorkOrderStatus::New->value,
                            ]);

                            $request->update(['status' => ServiceRequestStatus::Converted->value]);
                        });
                    }),
                Action::make('close')
                    ->requiresConfirmation()
                    ->visible(fn (ServiceRequest $record): bool => in_array($record->status, [ServiceRequestStatus::Open, ServiceRequestStatus::Reviewing], true))
                    ->action(fn (ServiceRequest $record) => $record->update(['status' => ServiceRequestStatus::Closed])),
                Action::make('cancel')
                    ->requiresConfirmation()
                    ->visible(fn (ServiceRequest $record): bool => in_array($record->status, [ServiceRequestStatus::Open, ServiceRequestStatus::Reviewing], true))
                    ->action(fn (ServiceRequest $record) => $record->update(['status' => ServiceRequestStatus::Cancelled])),
                EditAction::make()->visible(fn (ServiceRequest $record): bool => ! in_array(
                    $record->status,
                    [ServiceRequestStatus::Converted, ServiceRequestStatus::Closed, ServiceRequestStatus::Cancelled],
                    true,
                )),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServiceRequests::route('/'),
        ];
    }
}
