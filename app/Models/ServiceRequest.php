<?php

namespace App\Models;

use App\ServicePriority;
use App\ServiceRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['organization_id', 'branch_id', 'customer_id', 'customer_address_id', 'equipment_id', 'title', 'description', 'priority', 'status', 'requested_at'])]
class ServiceRequest extends Model
{
    protected $attributes = [
        'priority' => 'normal',
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'priority' => ServicePriority::class,
            'status' => ServiceRequestStatus::class,
            'requested_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->request_number ??= 'SR-'.Str::upper((string) Str::ulid());
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }
}
