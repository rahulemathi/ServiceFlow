<?php

namespace App\Models;

use App\MembershipStatus;
use App\UserRole;
use Carbon\Carbon;
use Database\Factories\OrganizationInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $email
 * @property UserRole $role
 * @property string $token
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read User|null $inviter
 */
#[Fillable(['organization_id', 'email', 'role', 'token', 'invited_by', 'expires_at', 'accepted_at'])]
class OrganizationInvitation extends Model
{
    /** @use HasFactory<OrganizationInvitationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isAccepted();
    }

    public function accept(User $user): Membership
    {
        if (! $this->isValid()) {
            throw new LogicException('This invitation is no longer valid or has expired.');
        }

        return DB::transaction(function () use ($user): Membership {
            $this->update(['accepted_at' => now()]);

            $membership = Membership::firstOrCreate(
                [
                    'organization_id' => $this->organization_id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => MembershipStatus::Active,
                ]
            );

            $membership->update(['status' => MembershipStatus::Active]);

            if ($this->role->isOrganizationRole()) {
                $membership->assignRole($this->role);
            }

            // Assign main branch if available and user has no branch yet
            $mainBranch = Branch::where('organization_id', $this->organization_id)
                ->where('code', 'MAIN')
                ->first();

            if ($mainBranch && $membership->branches()->count() === 0) {
                $membership->assignBranch($mainBranch);
            }

            return $membership;
        });
    }
}
