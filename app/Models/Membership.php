<?php

namespace App\Models;

use App\MembershipStatus;
use App\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property MembershipStatus|string $status
 * @property-read User|null $user
 * @property-read Organization|null $organization
 * @property-read Collection<int, MembershipRole> $roles
 * @property-read Collection<int, Branch> $branches
 */
#[Fillable(['organization_id', 'user_id', 'status'])]
class Membership extends Model
{
    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(MembershipRole::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'membership_branches')
            ->withPivot('organization_id')
            ->withTimestamps();
    }

    public function assignRole(UserRole $role): MembershipRole
    {
        if (! $role->isOrganizationRole()) {
            throw new LogicException('Platform roles cannot be assigned to an organization membership.');
        }

        return $this->roles()->firstOrCreate([
            'role' => $role->value,
        ]);
    }

    protected static function booted(): void
    {
        static::creating(function (self $membership): void {
            $status = MembershipStatus::tryFrom((string) ($membership->getAttributes()['status'] ?? ''));
            if ($status === MembershipStatus::Active && $membership->organization && ! $membership->organization->canAddUser()) {
                throw ValidationException::withMessages([
                    'user_id' => 'Organization active user limit exceeded for current plan. Please upgrade your subscription.',
                ]);
            }
        });

        static::saving(function (self $membership): void {
            $status = MembershipStatus::tryFrom((string) ($membership->getAttributes()['status'] ?? ''));

            if ($status === null) {
                throw new LogicException('A membership must use a valid status.');
            }

            if ($membership->exists && $membership->isDirty('status') && $status === MembershipStatus::Active) {
                if ($membership->organization && ! $membership->organization->canAddUser()) {
                    throw ValidationException::withMessages([
                        'user_id' => 'Organization active user limit exceeded for current plan. Please upgrade your subscription.',
                    ]);
                }
            }
        });
    }

    public function hasRole(UserRole $role): bool
    {
        return $role->isOrganizationRole()
            && $this->roles()->where('role', $role->value)->exists();
    }

    public function assignBranch(Branch $branch): void
    {
        if ($branch->organization_id !== $this->organization_id) {
            throw new LogicException('A membership can only be assigned to a branch in its organization.');
        }

        $this->branches()->syncWithoutDetaching([
            $branch->getKey() => [
                'organization_id' => $this->organization_id,
            ],
        ]);
    }
}
