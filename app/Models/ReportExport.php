<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string $type
 * @property string $status
 * @property string|null $file_path
 * @property array<string, mixed>|null $filters
 * @property int $row_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Organization $organization
 * @property-read User $user
 */
#[Fillable(['organization_id', 'user_id', 'type', 'status', 'file_path', 'filters', 'row_count'])]
class ReportExport extends Model
{
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'row_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $export): void {
            $export->organization_id ??= Filament::getTenant()?->getKey();
            $export->user_id ??= Filament::auth()->id() ?? auth()->id();
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
