<?php

namespace App\Models;

use App\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['user_id', 'role'])]
class UserRoleAssignment extends Model
{
    protected $table = 'user_roles';

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $roleAssignment): void {
            $role = UserRole::tryFrom((string) ($roleAssignment->getAttributes()['role'] ?? ''));

            if ($role !== UserRole::PlatformAdmin) {
                throw new LogicException('Only platform roles can be assigned to a user directly.');
            }
        });
    }
}
