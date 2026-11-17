<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $part_id
 * @property int $stock_location_id
 * @property float $quantity_on_hand
 * @property-read Organization $organization
 * @property-read Part $part
 * @property-read StockLocation $stockLocation
 */
#[Fillable(['organization_id', 'part_id', 'stock_location_id', 'quantity_on_hand'])]
class PartStock extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $stock): void {
            $stock->organization_id ??= Filament::getTenant()?->getKey();
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
}
