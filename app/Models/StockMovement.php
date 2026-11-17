<?php

namespace App\Models;

use App\StockMovementType;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $part_id
 * @property int $stock_location_id
 * @property int|null $user_id
 * @property StockMovementType|string $type
 * @property float $quantity
 * @property float $balance_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $notes
 * @property-read Organization $organization
 * @property-read Part $part
 * @property-read StockLocation $stockLocation
 * @property-read User|null $user
 */
#[Fillable(['organization_id', 'part_id', 'stock_location_id', 'user_id', 'type', 'quantity', 'balance_after', 'reference_type', 'reference_id', 'notes'])]
class StockMovement extends Model
{
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'float',
            'balance_after' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $movement): void {
            $movement->organization_id ??= Filament::getTenant()?->getKey();
            $movement->user_id ??= Filament::auth()->id() ?? auth()->id();
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
