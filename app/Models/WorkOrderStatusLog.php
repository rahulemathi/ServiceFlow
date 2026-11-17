<?php

namespace App\Models;

use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $work_order_id
 * @property string $from_status
 * @property string $to_status
 * @property string|null $reason
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Organization $organization
 * @property-read WorkOrder $workOrder
 * @property-read User|null $user
 */
#[Fillable(['organization_id', 'work_order_id', 'from_status', 'to_status', 'reason', 'user_id'])]
class WorkOrderStatusLog extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            if (! $log->organization_id && $log->work_order_id) {
                $log->organization_id = WorkOrder::where('id', $log->work_order_id)->value('organization_id')
                    ?? Filament::getTenant()?->getKey();
            }
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
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
