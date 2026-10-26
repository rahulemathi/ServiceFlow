<?php

namespace App\Http\Middleware;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as BaseAuthenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Exceptions\HttpResponseException;

class Authenticate extends BaseAuthenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var User|null $user */
        $user = $guard->user();

        $panel = Filament::getCurrentOrDefaultPanel();

        if ($user instanceof FilamentUser && ! $user->canAccessPanel($panel)) {
            if ($panel->getId() === 'admin' && $user->isTechnician()) {
                throw new HttpResponseException(redirect()->route('technician.workspace'));
            }

            abort(403);
        }
    }
}
