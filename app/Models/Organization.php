<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
class Organization extends Model implements HasName
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function technicianAvailabilities(): HasMany
    {
        return $this->hasMany(TechnicianAvailability::class);
    }
}
