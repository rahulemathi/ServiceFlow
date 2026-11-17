<?php

namespace App\Models;

use App\Events\WorkOrderAssigned;
use App\Events\WorkOrderUpdated;
use App\UserRole;
use App\WorkOrderStatus;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable(['organization_id', 'branch_id', 'service_request_id', 'maintenance_contract_id', 'customer_id', 'customer_address_id', 'equipment_id', 'assigned_to_user_id', 'title', 'description', 'status', 'status_reason', 'scheduled_start_at', 'scheduled_end_at', 'completed_at', 'completion_notes', 'customer_signature_name', 'customer_signed_at', 'supervisor_approved_by', 'supervisor_approved_at', 'supervisor_signature_notes', 'has_overlap_override', 'overlap_override_reason', 'overlap_override_by_id'])]
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
            'customer_signed_at' => 'datetime',
            'supervisor_approved_at' => 'datetime',
            'has_overlap_override' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $workOrder): void {
            $workOrder->work_order_number ??= 'WO-'.Str::upper((string) Str::ulid());
            $workOrder->organization_id ??= Filament::getTenant()?->getKey();

            $org = $workOrder->organization ?? ($workOrder->organization_id ? Organization::find($workOrder->organization_id) : null);
            if ($org && ! $org->canCreateWorkOrder()) {
                throw ValidationException::withMessages([
                    'title' => 'Cannot create work order: subscription is suspended or monthly work order limit has been reached.',
                ]);
            }
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
            $workOrder->organization_id ??= Filament::getTenant()?->getKey();

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

            if (
                $workOrder->assigned_to_user_id !== null
                && $workOrder->scheduled_start_at !== null
                && $workOrder->scheduled_end_at !== null
                && ! in_array($workOrder->status, [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)
            ) {
                if (! $workOrder->has_overlap_override) {
                    $conflicting = self::query()
                        ->where('organization_id', $workOrder->organization_id)
                        ->where('assigned_to_user_id', $workOrder->assigned_to_user_id)
                        ->when($workOrder->id, fn ($q) => $q->where('id', '!=', $workOrder->id))
                        ->whereNotIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Cancelled->value])
                        ->where('scheduled_start_at', '<', $workOrder->scheduled_end_at)
                        ->where('scheduled_end_at', '>', $workOrder->scheduled_start_at)
                        ->first();

                    if ($conflicting) {
                        throw ValidationException::withMessages([
                            'assigned_to_user_id' => "Technician already has an overlapping work order scheduled during this time window ({$conflicting->work_order_number}). An authorized override is required to double-book.",
                        ]);
                    }
                } else {
                    if (blank($workOrder->overlap_override_reason)) {
                        throw ValidationException::withMessages([
                            'overlap_override_reason' => 'An audit reason is required when overriding a technician schedule overlap.',
                        ]);
                    }

                    if (! $workOrder->overlap_override_by_id && Filament::auth()->id()) {
                        $workOrder->overlap_override_by_id = Filament::auth()->id();
                    }
                }
            }
        });
    }

    public function transitionTo(WorkOrderStatus $status, ?string $reason = null, ?User $user = null): void
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

        if ($status === WorkOrderStatus::OnHold && blank($reason) && blank($this->status_reason)) {
            throw ValidationException::withMessages([
                'status_reason' => 'A reason is required when placing a work order on hold.',
            ]);
        }

        if ($status === WorkOrderStatus::Cancelled && blank($reason) && blank($this->status_reason)) {
            throw ValidationException::withMessages([
                'status_reason' => 'A reason is required when cancelling a work order.',
            ]);
        }

        if ($status === WorkOrderStatus::Completed) {
            if (! $this->allChecklistsCompleted()) {
                throw ValidationException::withMessages([
                    'status' => 'All checklist items must be completed before marking the work order as completed.',
                ]);
            }
        }

        if ($status === WorkOrderStatus::InProgress && $this->scheduled_start_at === null) {
            $this->scheduled_start_at = now();
        }

        $this->status = $status;

        if ($reason !== null) {
            $this->status_reason = $reason;
        }

        if ($status === WorkOrderStatus::Completed) {
            $this->completed_at = now();
        }

        $this->save();

        // Record status change log
        $this->statusLogs()->create([
            'organization_id' => $this->organization_id,
            'from_status' => $currentStatus->value,
            'to_status' => $status->value,
            'reason' => $reason,
            'user_id' => $user?->id ?? auth()->id(),
        ]);
    }

    public function allChecklistsCompleted(): bool
    {
        if ($this->checklists()->count() === 0) {
            return true;
        }

        return ! $this->checklists()->where('is_completed', false)->exists();
    }

    public function isCustomerSigned(): bool
    {
        return $this->customer_signed_at !== null && ! blank($this->customer_signature_name);
    }

    public function signCustomer(string $name): void
    {
        $this->update([
            'customer_signature_name' => $name,
            'customer_signed_at' => now(),
        ]);
    }

    public function isSupervisorApproved(): bool
    {
        return $this->supervisor_approved_at !== null && $this->supervisor_approved_by !== null;
    }

    public function approveSupervisor(User $supervisor, ?string $notes = null): void
    {
        $this->update([
            'supervisor_approved_by' => $supervisor->id,
            'supervisor_approved_at' => now(),
            'supervisor_signature_notes' => $notes,
        ]);
    }

    /**
     * @return HasMany<WorkOrderChecklist, $this>
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(WorkOrderChecklist::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<WorkOrderStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(WorkOrderStatusLog::class)->latest();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function overlapOverrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overlap_override_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_approved_by');
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

    /**
     * @return BelongsTo<MaintenanceContract, $this>
     */
    public function maintenanceContract(): BelongsTo
    {
        return $this->belongsTo(MaintenanceContract::class);
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
