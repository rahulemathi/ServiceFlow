<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForceChangePasswordController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->must_change_password) {
            return redirect()->to(Filament::getUrl() ?? '/admin');
        }

        return view('auth.force-change-password', [
            'user' => $user,
            'logoutUrl' => Filament::getLogoutUrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->numbers(),
                'different:current_password',
            ],
        ], [
            'current_password.current_password' => 'The provided current password does not match our records.',
            'password.different' => 'Your new password must be different from your current temporary password.',
            'password.min' => 'Your new password must be at least 8 characters.',
        ]);

        $user->update([
            'password' => Hash::make($request->input('password')),
            'must_change_password' => false,
        ]);

        session()->flash('status', 'Your password has been updated successfully. Welcome to ServiceFlow!');

        return redirect()->to(Filament::getUrl() ?? '/admin');
    }
}
