<?php

namespace App\Models;

use App\StockMovementType;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property float $cost_price
 * @property float $selling_price
 * @property string $unit
 * @property float $min_stock_level
 * @property bool $is_active
 * @property-read Organization $organization
 * @property-read Collection<int, PartStock> $stocks
 * @property-read Collection<int, StockMovement> $movements
 */
#[Fillable(['organization_id', 'sku', 'name', 'description', 'cost_price', 'selling_price', 'unit', 'min_stock_level', 'is_active'])]
class Part extends Model
{
    protected function casts(): array
    {
        return [
            'cost_price' => 'float',
            'selling_price' => 'float',
            'min_stock_level' => 'float',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $part): void {
            $part->organization_id ??= Filament::getTenant()?->getKey();
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
     * @return HasMany<PartStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(PartStock::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    public function totalStock(): float
    {
        return (float) $this->stocks()->sum('quantity_on_hand');
    }

    public function stockAt(StockLocation $location): float
    {
        return (float) ($this->stocks()->where('stock_location_id', $location->id)->value('quantity_on_hand') ?? 0);
    }

    public function isLowStock(): bool
    {
        return $this->totalStock() <= $this->min_stock_level;
    }

    public function adjustStock(StockLocation $location, float $quantityDelta, ?string $notes = null, ?User $user = null): StockMovement
    {
        if ($location->organization_id !== $this->organization_id) {
            throw new \LogicException('Stock location belongs to another organization.');
        }

        return DB::transaction(function () use ($location, $quantityDelta, $notes, $user): StockMovement {
            /** @var PartStock $stock */
            $stock = PartStock::query()
                ->where('organization_id', $this->organization_id)
                ->where('part_id', $this->id)
                ->where('stock_location_id', $location->id)
                ->lockForUpdate()
                ->firstOrCreate([
                    'organization_id' => $this->organization_id,
                    'part_id' => $this->id,
                    'stock_location_id' => $location->id,
                ], [
                    'quantity_on_hand' => 0,
                ]);

            $newBalance = $stock->quantity_on_hand + $quantityDelta;

            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Cannot adjust stock below zero. Current balance is {$stock->quantity_on_hand}, adjustment was {$quantityDelta}.",
                ]);
            }

            $stock->quantity_on_hand = $newBalance;
            $stock->save();

            return StockMovement::create([
                'organization_id' => $this->organization_id,
                'part_id' => $this->id,
                'stock_location_id' => $location->id,
                'user_id' => $user?->id ?? Filament::auth()->id() ?? auth()->id(),
                'type' => StockMovementType::Adjustment->value,
                'quantity' => $quantityDelta,
                'balance_after' => $newBalance,
                'notes' => $notes ?? 'Inventory stock adjustment',
            ]);
        });
    }

    public function consumeStock(
        StockLocation $location,
        float $quantity,
        ?Model $reference = null,
        ?string $notes = null,
        ?User $user = null,
        bool $allowNegative = false,
    ): StockMovement {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Consumed quantity must be greater than zero.',
            ]);
        }

        if ($location->organization_id !== $this->organization_id) {
            throw new \LogicException('Stock location belongs to another organization.');
        }

        return DB::transaction(function () use ($location, $quantity, $reference, $notes, $user, $allowNegative): StockMovement {
            /** @var PartStock $stock */
            $stock = PartStock::query()
                ->where('organization_id', $this->organization_id)
                ->where('part_id', $this->id)
                ->where('stock_location_id', $location->id)
                ->lockForUpdate()
                ->firstOrCreate([
                    'organization_id' => $this->organization_id,
                    'part_id' => $this->id,
                    'stock_location_id' => $location->id,
                ], [
                    'quantity_on_hand' => 0,
                ]);

            if (! $allowNegative && $stock->quantity_on_hand < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Insufficient stock on hand for {$this->name} at {$location->name}. Available: {$stock->quantity_on_hand}, Requested: {$quantity}.",
                ]);
            }

            $newBalance = $stock->quantity_on_hand - $quantity;
            $stock->quantity_on_hand = $newBalance;
            $stock->save();

            return StockMovement::create([
                'organization_id' => $this->organization_id,
                'part_id' => $this->id,
                'stock_location_id' => $location->id,
                'user_id' => $user?->id ?? Filament::auth()->id() ?? auth()->id(),
                'type' => StockMovementType::WorkOrderConsumption->value,
                'quantity' => -$quantity,
                'balance_after' => $newBalance,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'notes' => $notes ?? 'Consumed for work order service',
            ]);
        });
    }

    public function transferStock(
        StockLocation $source,
        StockLocation $destination,
        float $quantity,
        ?string $notes = null,
        ?User $user = null,
    ): void {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Transfer quantity must be greater than zero.',
            ]);
        }

        if ($source->organization_id !== $this->organization_id || $destination->organization_id !== $this->organization_id) {
            throw new \LogicException('Stock locations must belong to the same organization.');
        }

        DB::transaction(function () use ($source, $destination, $quantity, $notes, $user): void {
            /** @var PartStock $sourceStock */
            $sourceStock = PartStock::query()
                ->where('organization_id', $this->organization_id)
                ->where('part_id', $this->id)
                ->where('stock_location_id', $source->id)
                ->lockForUpdate()
                ->firstOrCreate([
                    'organization_id' => $this->organization_id,
                    'part_id' => $this->id,
                    'stock_location_id' => $source->id,
                ], ['quantity_on_hand' => 0]);

            if ($sourceStock->quantity_on_hand < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Insufficient stock at source location ({$source->name}). Available: {$sourceStock->quantity_on_hand}.",
                ]);
            }

            /** @var PartStock $destStock */
            $destStock = PartStock::query()
                ->where('organization_id', $this->organization_id)
                ->where('part_id', $this->id)
                ->where('stock_location_id', $destination->id)
                ->lockForUpdate()
                ->firstOrCreate([
                    'organization_id' => $this->organization_id,
                    'part_id' => $this->id,
                    'stock_location_id' => $destination->id,
                ], ['quantity_on_hand' => 0]);

            $sourceStock->quantity_on_hand -= $quantity;
            $sourceStock->save();

            $destStock->quantity_on_hand += $quantity;
            $destStock->save();

            $userId = $user?->id ?? Filament::auth()->id() ?? auth()->id();

            // Outbound from source
            StockMovement::create([
                'organization_id' => $this->organization_id,
                'part_id' => $this->id,
                'stock_location_id' => $source->id,
                'user_id' => $userId,
                'type' => StockMovementType::TransferOut->value,
                'quantity' => -$quantity,
                'balance_after' => $sourceStock->quantity_on_hand,
                'notes' => $notes ?? "Transferred to {$destination->name}",
            ]);

            // Inbound to destination
            StockMovement::create([
                'organization_id' => $this->organization_id,
                'part_id' => $this->id,
                'stock_location_id' => $destination->id,
                'user_id' => $userId,
                'type' => StockMovementType::TransferIn->value,
                'quantity' => $quantity,
                'balance_after' => $destStock->quantity_on_hand,
                'notes' => $notes ?? "Transferred from {$source->name}",
            ]);
        });
    }
}
