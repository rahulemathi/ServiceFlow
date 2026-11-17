<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $work_order_id
 * @property string $item_text
 * @property bool $is_completed
 * @property Carbon|null $completed_at
 * @property int|null $completed_by_user_id
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Organization $organization
 * @property-read WorkOrder $workOrder
 * @property-read User|null $completedBy
 */
#[Fillable(['organization_id', 'work_order_id', 'item', 'item_text', 'is_completed', 'completed_at', 'completed_by_user_id', 'sort_order'])]
class WorkOrderChecklist extends Model
{
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $checklist): void {
            if (! $checklist->organization_id && $checklist->work_order_id) {
                $checklist->organization_id = WorkOrder::where('id', $checklist->work_order_id)->value('organization_id')
                    ?? Filament::getTenant()?->getKey();
            }
        });
    }

    public function getItemAttribute(): string
    {
        return $this->attributes['item_text'] ?? '';
    }

    public function setItemAttribute(string $value): void
    {
        $this->attributes['item_text'] = $value;
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
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function markCompleted(?User $user = null): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by_user_id' => $user?->id ?? auth()->id(),
        ]);
    }

    public function markIncomplete(): void
    {
        $this->update([
            'is_completed' => false,
            'completed_at' => null,
            'completed_by_user_id' => null,
        ]);
    }
}
