<?php

namespace App\Filament\Resources\Invoices;

use App\CommercialStatus;
use App\Filament\Resources\Invoices\Pages\ManageInvoices;
use App\Filament\Resources\Support\FilamentOptions;
use App\Models\Invoice;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Financials';

    protected static ?string $recordTitleAttribute = 'invoice_number';

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
                DatePicker::make('issued_at'),
                DatePicker::make('due_at')->afterOrEqual('issued_at'),
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
                TextColumn::make('invoice_number')->searchable()->sortable(),
                TextColumn::make('customer.name')->searchable()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('total')->money('INR')->sortable(),
                TextColumn::make('balance')->money('INR'),
                TextColumn::make('issued_at')->date()->sortable(),
                TextColumn::make('due_at')->date()->sortable(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('customer:id,name')
                ->withSum('payments', 'amount'))
            ->filters([
                SelectFilter::make('branch_id')->options(FilamentOptions::branches()),
                SelectFilter::make('status')->options(FilamentOptions::enum([
                    CommercialStatus::Draft,
                    CommercialStatus::Sent,
                    CommercialStatus::PartiallyPaid,
                    CommercialStatus::Paid,
                    CommercialStatus::Overdue,
                    CommercialStatus::Void,
                ])),
            ])
            ->recordActions([
                Action::make('send')
                    ->label('Mark as sent')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record): bool => $record->status === CommercialStatus::Draft)
                    ->action(function (Invoice $record): void {
                        $record->recalculateTotals();

                        if ((float) $record->total <= 0) {
                            throw ValidationException::withMessages([
                                'items' => 'An invoice must have a total greater than zero before it can be sent.',
                            ]);
                        }

                        $record->update([
                            'status' => CommercialStatus::Sent,
                            'issued_at' => $record->issued_at ?? today(),
                        ]);
                        $record->refreshPaymentStatus();
                    }),
                EditAction::make()->visible(fn (Invoice $record): bool => $record->status === CommercialStatus::Draft),
                DeleteAction::make()->visible(fn (Invoice $record): bool => $record->status === CommercialStatus::Draft),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInvoices::route('/'),
        ];
    }
}
