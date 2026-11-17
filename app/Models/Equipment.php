<?php

namespace App\Models;

use App\EquipmentStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $branch_id
 * @property int $customer_id
 * @property int|null $customer_address_id
 * @property string $name
 * @property string|null $manufacturer
 * @property string|null $model
 * @property string|null $serial_number
 * @property Carbon|null $installed_at
 * @property Carbon|null $warranty_expires_at
 * @property EquipmentStatus $status
 * @property string|null $notes
 * @property-read Organization $organization
 * @property-read Branch|null $branch
 * @property-read Customer $customer
 * @property-read CustomerAddress|null $address
 */
#[Fillable(['organization_id', 'branch_id', 'customer_id', 'customer_address_id', 'name', 'manufacturer', 'model', 'serial_number', 'installed_at', 'warranty_expires_at', 'status', 'notes'])]
class Equipment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => EquipmentStatus::class,
            'installed_at' => 'date',
            'warranty_expires_at' => 'date',
        ];
    }

    public function isUnderWarranty(): bool
    {
        return $this->warranty_expires_at !== null && $this->warranty_expires_at->isFuture();
    }

    public function warrantyStatusLabel(): string
    {
        if (! $this->warranty_expires_at) {
            return 'No warranty recorded';
        }

        if ($this->isUnderWarranty()) {
            return 'Under warranty until '.$this->warranty_expires_at->format('M d, Y');
        }

        return 'Warranty expired on '.$this->warranty_expires_at->format('M d, Y');
    }

    public function qrLookupUrl(): string
    {
        return route('equipment.lookup', ['serial' => $this->serial_number ?: (string) $this->id]);
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
     * @return HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * @return HasMany<MaintenanceContract, $this>
     */
    public function maintenanceContracts(): HasMany
    {
        return $this->hasMany(MaintenanceContract::class);
    }
}
