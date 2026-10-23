<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['organization_id', 'name', 'code'])]
class Branch extends Model
{
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
