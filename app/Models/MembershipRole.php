<?php

namespace App\Models;

use App\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['membership_id', 'role'])]
class MembershipRole extends Model
{
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $membershipRole): void {
            $role = UserRole::tryFrom((string) ($membershipRole->getAttributes()['role'] ?? ''));

            if ($role === null || ! $role->isOrganizationRole()) {
                throw new LogicException('An organization membership can only have organization roles.');
            }
        });
    }
}
