<?php

namespace App\Models;

use App\Events\WorkOrderAssigned;
use App\Events\WorkOrderUpdated;
use App\UserRole;
use App\WorkOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable(['organization_id', 'branch_id', 'service_request_id', 'customer_id', 'customer_address_id', 'equipment_id', 'assigned_to_user_id', 'title', 'description', 'status', 'scheduled_start_at', 'scheduled_end_at', 'completed_at', 'completion_notes'])]
class WorkOrder extends Model
{
    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $workOrder): void {
            $workOrder->work_order_number ??= 'WO-'.Str::upper((string) Str::ulid());
        });

        static::created(function (self $workOrder): void {
            if ($workOrder->assigned_to_user_id !== null) {
                WorkOrderAssigned::dispatch($workOrder, "New work order {$workOrder->work_order_number} has been assigned.");
            } else {
                WorkOrderUpdated::dispatch($workOrder, "New work order {$workOrder->work_order_number} created.");
            }
        });

        static::updated(function (self $workOrder): void {
            if ($workOrder->wasChanged('assigned_to_user_id')) {
                if ($workOrder->assigned_to_user_id !== null) {
                    WorkOrderAssigned::dispatch($workOrder, "Work order {$workOrder->work_order_number} has been assigned.");
                } else {
                    WorkOrderUpdated::dispatch($workOrder, "Work order {$workOrder->work_order_number} was unassigned.");
                }
            } elseif ($workOrder->wasChanged('status')) {
                $statusName = is_object($workOrder->status) ? $workOrder->status->value : $workOrder->status;
                WorkOrderUpdated::dispatch($workOrder, "Work order {$workOrder->work_order_number} status updated to {$statusName}.");
            } else {
                WorkOrderUpdated::dispatch($workOrder);
            }
        });

        static::saving(function (self $workOrder): void {
            if ($workOrder->assigned_to_user_id !== null) {
                $isActiveTechnician = Membership::query()
                    ->where('organization_id', $workOrder->organization_id)
                    ->where('user_id', $workOrder->assigned_to_user_id)
                    ->where('status', 'active')
                    ->whereHas('roles', fn ($query) => $query->where('role', UserRole::Technician->value))
                    ->exists();

                if (! $isActiveTechnician) {
                    throw ValidationException::withMessages([
                        'assigned_to_user_id' => 'Work orders can only be assigned to active technicians in this organization.',
                    ]);
                }
            }

            if (
                $workOrder->scheduled_start_at !== null
                && $workOrder->scheduled_end_at !== null
                && $workOrder->scheduled_end_at->lessThanOrEqualTo($workOrder->scheduled_start_at)
            ) {
                throw ValidationException::withMessages([
                    'scheduled_end_at' => 'The scheduled end must be later than the scheduled start.',
                ]);
            }
        });
    }

    public function transitionTo(WorkOrderStatus $status): void
    {
        $currentStatus = $this->status;
        $allowedTransitions = match ($currentStatus) {
            WorkOrderStatus::New => [WorkOrderStatus::Scheduled, WorkOrderStatus::InProgress, WorkOrderStatus::Cancelled],
            WorkOrderStatus::Scheduled => [WorkOrderStatus::InProgress, WorkOrderStatus::Cancelled],
            WorkOrderStatus::InProgress => [WorkOrderStatus::OnHold, WorkOrderStatus::Completed],
            WorkOrderStatus::OnHold => [WorkOrderStatus::InProgress, WorkOrderStatus::Cancelled],
            WorkOrderStatus::Completed, WorkOrderStatus::Cancelled => [],
        };

        if (! in_array($status, $allowedTransitions, true)) {
            throw ValidationException::withMessages([
                'status' => "A work order cannot transition from {$currentStatus->value} to {$status->value}.",
            ]);
        }

        if (
            $status === WorkOrderStatus::Scheduled
            && ($this->scheduled_start_at === null || $this->scheduled_end_at === null)
        ) {
            throw ValidationException::withMessages([
                'scheduled_start_at' => 'A work order requires start and end times before it can be scheduled.',
            ]);
        }

        if ($status === WorkOrderStatus::InProgress && $this->scheduled_start_at === null) {
            $this->scheduled_start_at = now();
        }

        $this->status = $status;

        if ($status === WorkOrderStatus::Completed) {
            $this->completed_at = now();
        }

        $this->save();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WorkOrderEntry::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
