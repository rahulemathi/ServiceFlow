<?php

use App\MembershipStatus;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('technician.{id}', function (User $user, int $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('organization.{organizationId}', function (User $user, int $organizationId): bool {
    if ($user->hasPlatformRole(UserRole::PlatformAdmin)) {
        return true;
    }

    return $user->memberships()
        ->where('organization_id', $organizationId)
        ->where('status', MembershipStatus::Active->value)
        ->exists();
});
