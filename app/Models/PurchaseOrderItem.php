<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $purchase_order_id
 * @property int $part_id
 * @property float $quantity
 * @property float $unit_cost
 * @property float $total_cost
 * @property-read Organization $organization
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read Part $part
 */
#[Fillable(['organization_id', 'purchase_order_id', 'part_id', 'quantity', 'unit_cost', 'total_cost'])]
class PurchaseOrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_cost' => 'float',
            'total_cost' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->organization_id ??= Filament::getTenant()?->getKey();
            $item->total_cost = $item->quantity * $item->unit_cost;
        });

        static::saved(function (self $item): void {
            $item->purchaseOrder?->recalculateTotal();
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
