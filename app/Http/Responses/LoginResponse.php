<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        if ($user && $user->isTechnician() && ! $user->canAccessPanel(Filament::getPanel('admin'))) {
            return redirect()->intended(route('technician.workspace'));
        }

        if ($user && $user->canAccessPanel(Filament::getPanel('admin'))) {
            return redirect()->intended(Filament::getUrl());
        }

        return redirect()->intended('/');
    }
}
