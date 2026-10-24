<?php

namespace App\Filament\Resources\Estimates;

use App\CommercialStatus;
use App\Filament\Resources\Estimates\Pages\ManageEstimates;
use App\Filament\Resources\Support\FilamentOptions;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
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
use Illuminate\Validation\ValidationException;

class EstimateResource extends Resource
{
    protected static ?string $model = Estimate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Financials';

    protected static ?string $recordTitleAttribute = 'estimate_number';

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
                Select::make('work_order_id')
                    ->options(fn (Get $get): array => FilamentOptions::workOrdersForCustomer($get('customer_id')))
                    ->searchable()
                    ->placeholder('No work order'),
                DatePicker::make('expires_at'),
                TextInput::make('tax_rate')->numeric()->minValue(0)->maxValue(100)->step(0.01)->default(0)->required(),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
                Repeater::make('items')
                    ->relationship()
                    ->schema([
                        TextInput::make('description')->required()->maxLength(255),
                        TextInput::make('quantity')->numeric()->minValue(0.01)->step(0.01)->default(1)->required(),
                        TextInput::make('unit_price')->numeric()->minValue(0)->step(0.01)->default(0)->required(),
                    ])
                    ->columns(3)
                    ->minItems(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('estimate_number')->searchable()->sortable(),
                TextColumn::make('customer.name')->searchable()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('total')->money('INR')->sortable(),
                TextColumn::make('expires_at')->date()->sortable(),
                TextColumn::make('created_at')->date()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')->options(FilamentOptions::branches()),
                SelectFilter::make('status')->options(FilamentOptions::enum([
                    CommercialStatus::Draft,
                    CommercialStatus::Sent,
                    CommercialStatus::Approved,
                    CommercialStatus::Rejected,
                ])),
            ])
            ->recordActions([
                Action::make('send')
                    ->label('Mark sent')
                    ->requiresConfirmation()
                    ->visible(fn (Estimate $record): bool => $record->status === CommercialStatus::Draft)
                    ->action(function (Estimate $record): void {
                        $record->recalculateTotals();

                        if ((float) $record->total <= 0) {
                            throw ValidationException::withMessages([
                                'items' => 'An estimate must have a total greater than zero before it can be sent.',
                            ]);
                        }

                        $record->update(['status' => CommercialStatus::Sent]);
                    }),
                Action::make('approve')
                    ->requiresConfirmation()
                    ->visible(fn (Estimate $record): bool => $record->status === CommercialStatus::Sent)
                    ->action(fn (Estimate $record) => $record->update(['status' => CommercialStatus::Approved])),
                Action::make('reject')
                    ->requiresConfirmation()
                    ->visible(fn (Estimate $record): bool => $record->status === CommercialStatus::Sent)
                    ->action(fn (Estimate $record) => $record->update(['status' => CommercialStatus::Rejected])),
                Action::make('createInvoice')
                    ->label('Create invoice')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->requiresConfirmation()
                    ->visible(fn (Estimate $record): bool => $record->status === CommercialStatus::Approved
                        && ! $record->invoice()->exists())
                    ->action(function (Estimate $record): void {
                        DB::transaction(function () use ($record): void {
                            $estimate = Estimate::query()
                                ->where('organization_id', Filament::getTenant()?->getKey())
                                ->lockForUpdate()
                                ->with('items')
                                ->findOrFail($record->getKey());

                            if ($estimate->status !== CommercialStatus::Approved || $estimate->invoice()->exists()) {
                                return;
                            }

                            $invoice = Invoice::create([
                                'organization_id' => $estimate->organization_id,
                                'branch_id' => $estimate->branch_id,
                                'customer_id' => $estimate->customer_id,
                                'work_order_id' => $estimate->work_order_id,
                                'estimate_id' => $estimate->id,
                                'tax_rate' => $estimate->tax_rate,
                                'notes' => $estimate->notes,
                            ]);

                            foreach ($estimate->items as $item) {
                                $invoice->items()->create([
                                    'organization_id' => $invoice->organization_id,
                                    'description' => $item->description,
                                    'quantity' => $item->quantity,
                                    'unit_price' => $item->unit_price,
                                ]);
                            }
                        });
                    }),
                EditAction::make()->visible(fn (Estimate $record): bool => $record->status === CommercialStatus::Draft),
                DeleteAction::make()->visible(fn (Estimate $record): bool => $record->status === CommercialStatus::Draft),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEstimates::route('/'),
        ];
    }
}
