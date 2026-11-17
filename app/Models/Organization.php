<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'plan', 'subscription_status', 'trial_ends_at', 'subscription_ends_at', 'grace_period_ends_at', 'max_users', 'max_branches', 'max_monthly_work_orders'])]
class Organization extends Model implements HasName
{
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'max_users' => 'integer',
            'max_branches' => 'integer',
            'max_monthly_work_orders' => 'integer',
        ];
    }

    public function isSubscriptionActive(): bool
    {
        if (in_array($this->subscription_status, ['active', 'trialing'], true)) {
            return true;
        }

        if ($this->subscription_status === 'past_due' && $this->isInGracePeriod()) {
            return true;
        }

        return false;
    }

    public function isInGracePeriod(): bool
    {
        return $this->grace_period_ends_at !== null && $this->grace_period_ends_at->isFuture();
    }

    public function isSuspended(): bool
    {
        return $this->subscription_status === 'suspended' || $this->subscription_status === 'canceled';
    }

    public function canAddUser(): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        if ($this->max_users === null) {
            return true;
        }

        return $this->memberships()->where('status', 'active')->count() < $this->max_users;
    }

    public function canAddBranch(): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        if ($this->max_branches === null) {
            return true;
        }

        return $this->branches()->count() < $this->max_branches;
    }

    public function canCreateWorkOrder(): bool
    {
        if (! $this->isSubscriptionActive()) {
            return false;
        }

        if ($this->max_monthly_work_orders === null) {
            return true;
        }

        $startOfMonth = now()->startOfMonth();
        $monthlyCount = $this->workOrders()
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        return $monthlyCount < $this->max_monthly_work_orders;
    }

    public function recordAuditAccess(User $admin, string $action, array $details = []): PlatformAuditLog
    {
        return PlatformAuditLog::create([
            'user_id' => $admin->id,
            'organization_id' => $this->id,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'details' => $details,
        ]);
    }

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

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
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
