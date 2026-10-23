<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['organization_id', 'user_id', 'day_of_week', 'starts_at', 'ends_at'])]
class TechnicianAvailability extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $availability): void {
            if ($availability->starts_at >= $availability->ends_at) {
                throw ValidationException::withMessages([
                    'ends_at' => 'Availability end time must be later than its start time.',
                ]);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
