<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['organization_id', 'name', 'code'])]
class Branch extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $branch): void {
            if ($branch->organization && ! $branch->organization->canAddBranch()) {
                throw ValidationException::withMessages([
                    'name' => 'Branch limit exceeded for current plan. Please upgrade your subscription.',
                ]);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'membership_branches')
            ->withPivot('organization_id')
            ->withTimestamps();
    }
}
