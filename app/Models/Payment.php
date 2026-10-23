<?php

namespace App\Models;

use App\CommercialStatus;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable(['organization_id', 'branch_id', 'invoice_id', 'recorded_by_user_id', 'amount', 'received_at', 'method', 'reference', 'notes'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'date',
        ];
    }

    public static function recordAgainstInvoice(Invoice $invoice, array $attributes): self
    {
        return DB::transaction(function () use ($invoice, $attributes): self {
            $invoiceQuery = Invoice::query()
                ->where('organization_id', $invoice->organization_id)
                ->lockForUpdate();

            $tenant = Filament::getTenant();

            if ($tenant instanceof Organization) {
                $invoiceQuery->where('organization_id', $tenant->getKey());
            }

            $lockedInvoice = $invoiceQuery->findOrFail($invoice->getKey());
            $amount = round((float) $attributes['amount'], 2);

            if (
                $lockedInvoice->status === CommercialStatus::Draft
                || $lockedInvoice->status === CommercialStatus::Void
                || $amount <= 0
                || $amount > $lockedInvoice->balance
            ) {
                throw ValidationException::withMessages([
                    'amount' => 'Payments require a sent invoice and must be greater than zero without exceeding its balance.',
                ]);
            }

            $payment = $lockedInvoice->payments()->create([
                'organization_id' => $lockedInvoice->organization_id,
                'branch_id' => $lockedInvoice->branch_id,
                'recorded_by_user_id' => auth()->id(),
                'amount' => $amount,
                'received_at' => $attributes['received_at'],
                'method' => $attributes['method'],
                'reference' => $attributes['reference'] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $lockedInvoice->refreshPaymentStatus();

            return $payment;
        });
    }

    protected static function booted(): void
    {
        static::created(function (self $payment): void {
            $payment->invoice->refreshPaymentStatus();
        });

        static::deleted(function (self $payment): void {
            $payment->invoice->refreshPaymentStatus();
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
