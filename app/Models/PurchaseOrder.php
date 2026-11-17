<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $branch_id
 * @property int $supplier_id
 * @property string $po_number
 * @property string $status
 * @property Carbon $order_date
 * @property Carbon|null $expected_date
 * @property float $total_amount
 * @property-read Organization $organization
 * @property-read Branch|null $branch
 * @property-read Supplier $supplier
 * @property-read Collection<int, PurchaseOrderItem> $items
 */
#[Fillable(['organization_id', 'branch_id', 'supplier_id', 'po_number', 'status', 'order_date', 'expected_date', 'total_amount'])]
class PurchaseOrder extends Model
{
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'total_amount' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $po): void {
            $po->organization_id ??= Filament::getTenant()?->getKey();
            $po->po_number ??= 'PO-'.Str::upper((string) Str::ulid());
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function recalculateTotal(): void
    {
        $this->total_amount = (float) $this->items()->sum('total_cost');
        $this->save();
    }
}
