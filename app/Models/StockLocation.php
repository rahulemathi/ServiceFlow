<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $branch_id
 * @property string $name
 * @property bool $is_active
 * @property-read Organization $organization
 * @property-read Branch|null $branch
 */
#[Fillable(['organization_id', 'branch_id', 'name', 'is_active'])]
class StockLocation extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $location): void {
            $location->organization_id ??= Filament::getTenant()?->getKey();
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
     * @return HasMany<PartStock, $this>
     */
    public function partStocks(): HasMany
    {
        return $this->hasMany(PartStock::class);
    }
}
