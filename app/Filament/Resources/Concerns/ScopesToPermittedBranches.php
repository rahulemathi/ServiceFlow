<?php

namespace App\Filament\Resources\Concerns;

use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ScopesToPermittedBranches
{
    /**
     * Scope an Eloquent query to records in the user's permitted branches (or unassigned branch records).
     * Organization administrators and platform administrators have unrestricted access to all branches.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function scopeQueryToPermittedBranches(Builder $query): Builder
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        if (! $user) {
            return $query;
        }

        if ($user->hasPlatformRole(UserRole::PlatformAdmin) || $user->isOrganizationAdmin()) {
            return $query;
        }

        $permittedBranches = $user->permittedBranchIds();
        if (! empty($permittedBranches)) {
            $query->where(function (Builder $b) use ($permittedBranches): void {
                $b->whereIn('branch_id', $permittedBranches)
                    ->orWhereNull('branch_id');
            });
        }

        return $query;
    }
}
