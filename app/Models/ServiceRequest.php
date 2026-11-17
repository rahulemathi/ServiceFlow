<?php

namespace App\Models;

use App\ServicePriority;
use App\ServiceRequestStatus;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $branch_id
 * @property int $customer_id
 * @property int|null $customer_address_id
 * @property int|null $equipment_id
 * @property string $request_number
 * @property string $title
 * @property string $description
 * @property ServicePriority $priority
 * @property ServiceRequestStatus $status
 * @property string|null $status_reason
 * @property Carbon|null $requested_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Organization $organization
 * @property-read Branch|null $branch
 * @property-read Customer $customer
 * @property-read CustomerAddress|null $address
 * @property-read Equipment|null $equipment
 * @property-read WorkOrder|null $workOrder
 */
#[Fillable(['organization_id', 'branch_id', 'customer_id', 'customer_address_id', 'equipment_id', 'title', 'description', 'priority', 'status', 'status_reason', 'requested_at', 'reviewed_at', 'cancelled_at'])]
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
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->request_number ??= 'SR-'.Str::upper((string) Str::ulid());
            $request->organization_id ??= Filament::getTenant()?->getKey();
        });
    }

    public function transitionTo(ServiceRequestStatus $targetStatus, ?string $reason = null): void
    {
        $allowed = match ($this->status) {
            ServiceRequestStatus::Open => [ServiceRequestStatus::Reviewing, ServiceRequestStatus::Converted, ServiceRequestStatus::Closed, ServiceRequestStatus::Cancelled],
            ServiceRequestStatus::Reviewing => [ServiceRequestStatus::Converted, ServiceRequestStatus::Closed, ServiceRequestStatus::Cancelled],
            ServiceRequestStatus::Converted, ServiceRequestStatus::Closed, ServiceRequestStatus::Cancelled => [],
        };

        if (! in_array($targetStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "A service request cannot transition from {$this->status->value} to {$targetStatus->value}.",
            ]);
        }

        if ($targetStatus === ServiceRequestStatus::Cancelled && blank($reason) && blank($this->cancellation_reason)) {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'A cancellation reason is required when cancelling a service request.',
            ]);
        }

        $this->status = $targetStatus;

        if ($reason !== null) {
            $this->cancellation_reason = $reason;
        }

        if ($targetStatus === ServiceRequestStatus::Reviewing && $this->reviewed_at === null) {
            $this->reviewed_at = now();
        }

        if ($targetStatus === ServiceRequestStatus::Cancelled) {
            $this->cancelled_at = now();
        }

        $this->save();
    }

    public function addComment(?User $user, string $comment, bool $isInternal = false): ServiceRequestComment
    {
        return $this->comments()->create([
            'organization_id' => $this->organization_id,
            'user_id' => $user?->id ?? auth()->id(),
            'comment' => $comment,
            'is_internal' => $isInternal,
        ]);
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<CustomerAddress, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * @return HasOne<WorkOrder, $this>
     */
    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }

    /**
     * @return HasMany<ServiceRequestComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ServiceRequestComment::class);
    }
}
