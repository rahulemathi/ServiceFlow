<?php

namespace App\Filament\Resources\Invoices;

use App\CommercialStatus;
use App\Filament\Resources\Concerns\ScopesToPermittedBranches;
use App\Filament\Resources\Invoices\Pages\ManageInvoices;
use App\Filament\Resources\Support\FilamentOptions;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\UserRole;
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
use Filament\Notifications\Notification;
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
    use ScopesToPermittedBranches;

    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Financials';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        return $user && ($user->isOrganizationAdmin() || $user->isAccountant() || $user->hasPlatformRole(UserRole::PlatformAdmin));
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeQueryToPermittedBranches(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')->options(FilamentOptions::customers())->searchable()->required()->live(),
                Select::make('branch_id')
                    ->options(FilamentOptions::branches())
                    ->rules(FilamentOptions::branchValidationRules())
                    ->searchable()
                    ->placeholder('No branch'),
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
                Action::make('recordPayment')
                    ->label('Record Payment')
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->visible(fn (Invoice $record): bool => ! in_array($record->status, [CommercialStatus::Draft, CommercialStatus::Void], true) && $record->balance > 0)
                    ->schema([
                        TextInput::make('amount')
                            ->label('Payment Amount (INR)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required(),
                        DatePicker::make('received_at')->default(today())->required(),
                        Select::make('method')
                            ->options([
                                'bank_transfer' => 'Bank Transfer / NEFT',
                                'upi' => 'UPI',
                                'credit_card' => 'Credit / Debit Card',
                                'cash' => 'Cash',
                                'cheque' => 'Cheque',
                            ])
                            ->default('bank_transfer')
                            ->required(),
                        TextInput::make('reference')->placeholder('Transaction ref, UTR, or cheque #'),
                        TextInput::make('notes')->placeholder('Optional payment notes'),
                    ])
                    ->fillForm(fn (Invoice $record): array => [
                        'amount' => $record->balance,
                        'received_at' => today(),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        Payment::recordAgainstInvoice($record, $data);
                        Notification::make()->title('Payment recorded successfully.')->success()->send();
                    }),
                Action::make('recordRefund')
                    ->label('Issue Refund / Credit')
                    ->icon(Heroicon::OutlinedReceiptRefund)
                    ->visible(fn (Invoice $record): bool => ! in_array($record->status, [CommercialStatus::Draft, CommercialStatus::Void], true) && ((float) $record->total - $record->balance) > 0)
                    ->schema([
                        TextInput::make('amount')
                            ->label('Refund Amount (INR)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required(),
                        DatePicker::make('received_at')->label('Refund Date')->default(today())->required(),
                        Select::make('method')
                            ->options([
                                'refund' => 'Bank Refund',
                                'credit_note' => 'Credit Note',
                            ])
                            ->default('refund')
                            ->required(),
                        TextInput::make('reference')->placeholder('Refund transaction reference'),
                        TextInput::make('notes')->placeholder('Reason for refund / credit adjustment'),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        Payment::recordRefund($record, $data);
                        Notification::make()->title('Refund / credit note recorded successfully.')->success()->send();
                    }),
                Action::make('void')
                    ->label('Void invoice')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record): bool => in_array($record->status, [CommercialStatus::Draft, CommercialStatus::Sent], true))
                    ->action(fn (Invoice $record) => $record->update(['status' => CommercialStatus::Void])),
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
