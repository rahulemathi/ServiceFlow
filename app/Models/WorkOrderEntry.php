<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'work_order_id', 'user_id', 'type', 'description', 'minutes', 'quantity', 'unit_price'])]
class WorkOrderEntry extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->organization_id ??= $entry->workOrder?->organization_id;
        });
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
