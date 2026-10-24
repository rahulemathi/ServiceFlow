<?php

namespace App\Models;

use App\MembershipStatus;
use App\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    public function assignPlatformRole(UserRole $role): UserRoleAssignment
    {
        if ($role !== UserRole::PlatformAdmin) {
            throw new LogicException('Only platform roles can be assigned directly to a user.');
        }

        return $this->roleAssignments()->firstOrCreate([
            'role' => $role->value,
        ]);
    }

    public function hasPlatformRole(UserRole $role): bool
    {
        return $role === UserRole::PlatformAdmin
            && $this->roleAssignments()->where('role', $role->value)->exists();
    }

    public function currentMembership(?Organization $tenant = null): ?Membership
    {
        $tenant ??= Filament::getTenant();

        if ($tenant instanceof Organization) {
            return $this->memberships()
                ->where('organization_id', $tenant->getKey())
                ->where('status', MembershipStatus::Active->value)
                ->first();
        }

        return $this->memberships()
            ->where('status', MembershipStatus::Active->value)
            ->first();
    }

    /**
     * @param  UserRole|array<UserRole>  $roles
     */
    public function hasOrganizationRole(UserRole|array $roles, ?Organization $tenant = null): bool
    {
        if ($this->hasPlatformRole(UserRole::PlatformAdmin)) {
            return true;
        }

        $roles = is_array($roles) ? $roles : [$roles];
        $roleValues = collect($roles)->map(fn ($r) => $r instanceof UserRole ? $r->value : $r)->all();

        $membership = $this->currentMembership($tenant);

        if (! $membership) {
            return false;
        }

        return $membership->roles()->whereIn('role', $roleValues)->exists();
    }

    public function isOrganizationAdmin(?Organization $tenant = null): bool
    {
        return $this->hasOrganizationRole([UserRole::OrganizationOwner, UserRole::Admin], $tenant);
    }

    public function isDispatcher(?Organization $tenant = null): bool
    {
        return $this->hasOrganizationRole(UserRole::Dispatcher, $tenant);
    }

    public function isTechnician(?Organization $tenant = null): bool
    {
        return $this->hasOrganizationRole(UserRole::Technician, $tenant);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        return $this->hasPlatformRole(UserRole::PlatformAdmin)
            || $this->memberships()
                ->where('status', MembershipStatus::Active->value)
                ->whereHas('roles', function (Builder $query): void {
                    $query->whereIn('role', UserRole::organizationRoleValues());
                })
                ->exists();
    }

    public function getTenants(Panel $panel): array|Collection
    {
        if (! $this->canAccessPanel($panel)) {
            return collect();
        }

        if ($this->hasPlatformRole(UserRole::PlatformAdmin)) {
            return Organization::query()->orderBy('name')->get();
        }

        return $this->memberships()
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('roles', function (Builder $query): void {
                $query->whereIn('role', UserRole::organizationRoleValues());
            })
            ->with('organization')
            ->get()
            ->pluck('organization')
            ->filter()
            ->values();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Organization || ! $this->canAccessPanel(filament()->getCurrentOrDefaultPanel())) {
            return false;
        }

        return $this->hasPlatformRole(UserRole::PlatformAdmin)
            || $this->memberships()
                ->where('organization_id', $tenant->getKey())
                ->where('status', MembershipStatus::Active->value)
                ->whereHas('roles', function (Builder $query): void {
                    $query->whereIn('role', UserRole::organizationRoleValues());
                })
                ->exists();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
