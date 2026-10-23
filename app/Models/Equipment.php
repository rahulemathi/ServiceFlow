<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'branch_id', 'customer_id', 'customer_address_id', 'name', 'manufacturer', 'model', 'serial_number', 'installed_at', 'warranty_expires_at', 'status', 'notes'])]
class Equipment extends Model
{
    protected function casts(): array
    {
        return [
            'installed_at' => 'date',
            'warranty_expires_at' => 'date',
        ];
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

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function maintenanceContracts(): HasMany
    {
        return $this->hasMany(MaintenanceContract::class);
    }
}
