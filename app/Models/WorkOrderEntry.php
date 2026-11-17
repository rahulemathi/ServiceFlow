<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'work_order_id', 'user_id', 'part_id', 'stock_location_id', 'type', 'description', 'minutes', 'quantity', 'unit_price'])]
class WorkOrderEntry extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->organization_id ??= $entry->workOrder?->organization_id;

            // When entry records part consumption, deduct stock transactionally
            if ($entry->type === 'part' && $entry->part_id && $entry->stock_location_id && $entry->quantity > 0) {
                /** @var Part $part */
                $part = Part::query()->where('organization_id', $entry->organization_id)->findOrFail($entry->part_id);
                /** @var StockLocation $location */
                $location = StockLocation::query()->where('organization_id', $entry->organization_id)->findOrFail($entry->stock_location_id);
                $part->consumeStock($location, (float) $entry->quantity, $entry->workOrder, $entry->description);
            }
        });
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
