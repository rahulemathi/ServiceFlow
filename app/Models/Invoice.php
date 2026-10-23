<?php

namespace App\Models;

use App\CommercialStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['organization_id', 'branch_id', 'customer_id', 'work_order_id', 'estimate_id', 'status', 'issued_at', 'due_at', 'tax_rate', 'notes'])]
class Invoice extends Model
{
    protected $attributes = [
        'status' => 'draft',
        'tax_rate' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => CommercialStatus::class,
            'issued_at' => 'date',
            'due_at' => 'date',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            $invoice->invoice_number ??= 'INV-'.Str::upper((string) Str::ulid());
        });

        static::saved(function (self $invoice): void {
            $invoice->recalculateTotals();
        });
    }

    public function recalculateTotals(): void
    {
        $subtotal = round((float) $this->items()->sum('line_total'), 2);
        $taxAmount = round($subtotal * (float) $this->tax_rate / 100, 2);

        $this->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
        ])->saveQuietly();

        $this->refreshPaymentStatus();
    }

    public function refreshPaymentStatus(): void
    {
        if ($this->status === CommercialStatus::Void || $this->status === CommercialStatus::Draft) {
            return;
        }

        $paid = (float) $this->payments()->sum('amount');
        $balance = max(0, round((float) $this->total - $paid, 2));
        $status = match (true) {
            $balance === 0.0 => CommercialStatus::Paid,
            $this->due_at !== null && $this->due_at->isBefore(today()) => CommercialStatus::Overdue,
            $paid > 0 => CommercialStatus::PartiallyPaid,
            default => CommercialStatus::Sent,
        };

        $this->forceFill(['status' => $status])->saveQuietly();
    }

    public function getBalanceAttribute(): float
    {
        $paid = array_key_exists('payments_sum_amount', $this->attributes)
            ? (float) $this->attributes['payments_sum_amount']
            : ($this->relationLoaded('payments')
                ? (float) $this->payments->sum('amount')
                : (float) $this->payments()->sum('amount'));

        return max(0, round((float) $this->total - $paid, 2));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
