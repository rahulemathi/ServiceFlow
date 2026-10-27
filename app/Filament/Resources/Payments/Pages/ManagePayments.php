<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Support\FilamentOptions;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class ManagePayments extends ManageRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordPayment')
                ->label('Record payment')
                ->icon(Heroicon::OutlinedBanknotes)
                ->schema([
                    Select::make('invoice_id')
                        ->options(FilamentOptions::payableInvoices())
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?int $state, Set $set): void {
                            if (! $state) {
                                $set('amount', null);

                                return;
                            }

                            $invoice = Invoice::query()
                                ->where('organization_id', Filament::getTenant()?->getKey())
                                ->find($state);

                            $set('amount', $invoice?->balance);
                        }),
                    TextInput::make('amount')
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->readOnly()
                        ->required(),
                    DatePicker::make('received_at')->default(today())->required(),
                    Select::make('method')
                        ->options([
                            'cash' => 'Cash',
                            'card' => 'Card',
                            'bank_transfer' => 'Bank transfer',
                            'check' => 'Check',
                            'other' => 'Other',
                        ])
                        ->required(),
                    TextInput::make('reference')->maxLength(255),
                    Textarea::make('notes')->rows(3),
                ])
                ->action(function (array $data): void {
                    $invoice = Invoice::query()
                        ->where('organization_id', Filament::getTenant()?->getKey())
                        ->findOrFail($data['invoice_id']);

                    $data['amount'] = $data['amount'] ?? $invoice->balance;

                    Payment::recordAgainstInvoice($invoice, $data);
                })
                ->successNotificationTitle('Payment recorded'),
        ];
    }
}
