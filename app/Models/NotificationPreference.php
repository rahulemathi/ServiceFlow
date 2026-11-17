<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string $event_type
 * @property string $channel
 * @property bool $is_enabled
 * @property-read Organization $organization
 * @property-read User $user
 */
#[Fillable(['organization_id', 'user_id', 'event_type', 'channel', 'is_enabled'])]
class NotificationPreference extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $pref): void {
            $pref->organization_id ??= Filament::getTenant()?->getKey();
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

    public static function isChannelEnabled(int $organizationId, int $userId, string $eventType, string $channel): bool
    {
        $pref = static::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('event_type', $eventType)
            ->where('channel', $channel)
            ->first();

        return $pref ? $pref->is_enabled : true; // Enabled by default
    }
}
