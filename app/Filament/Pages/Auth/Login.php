<?php

namespace App\Filament\Pages\Auth;

use App\MembershipStatus;
use App\Models\User;
use App\UserRole;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\Auth\Authenticatable;

class Login extends BaseLogin
{
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if (! ($user instanceof FilamentUser)) {
            return true;
        }

        /** @var User $user */
        return $user->hasPlatformRole(UserRole::PlatformAdmin)
            || $user->memberships()->where('status', MembershipStatus::Active->value)->exists();
    }
}
