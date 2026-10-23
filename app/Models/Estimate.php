<?php

namespace App\Models;

use App\CommercialStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['organization_id', 'branch_id', 'customer_id', 'work_order_id', 'status', 'expires_at', 'tax_rate', 'notes'])]
class Estimate extends Model
{
    protected $attributes = [
        'status' => 'draft',
        'tax_rate' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => CommercialStatus::class,
            'expires_at' => 'date',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $estimate): void {
            $estimate->estimate_number ??= 'EST-'.Str::upper((string) Str::ulid());
        });

        static::saved(function (self $estimate): void {
            $estimate->recalculateTotals();
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

    public function items(): HasMany
    {
        return $this->hasMany(EstimateItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
