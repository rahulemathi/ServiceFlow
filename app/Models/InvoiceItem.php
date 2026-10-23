<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'invoice_id', 'description', 'quantity', 'unit_price'])]
class InvoiceItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->organization_id ??= $item->invoice?->organization_id;
        });

        static::saving(function (self $item): void {
            $item->line_total = round((float) $item->quantity * (float) $item->unit_price, 2);
        });

        static::saved(function (self $item): void {
            $item->invoice->recalculateTotals();
        });

        static::deleted(function (self $item): void {
            $item->invoice->recalculateTotals();
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
