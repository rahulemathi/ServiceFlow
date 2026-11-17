<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class OrganizationInvitationController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        /** @var OrganizationInvitation|null $invitation */
        $invitation = OrganizationInvitation::with(['organization', 'inviter'])
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            return view('auth.invitation-status', [
                'status' => 'invalid',
                'title' => 'Invalid Invitation',
                'message' => 'The invitation link you followed does not exist or may have been revoked.',
            ]);
        }

        if ($invitation->isAccepted()) {
            return view('auth.invitation-status', [
                'status' => 'accepted',
                'title' => 'Invitation Already Accepted',
                'message' => 'This invitation has already been accepted. Please sign in to access your organization workspace.',
                'invitation' => $invitation,
            ]);
        }

        if ($invitation->isExpired()) {
            return view('auth.invitation-status', [
                'status' => 'expired',
                'title' => 'Invitation Expired',
                'message' => 'This invitation link expired on '.$invitation->expires_at->toFormattedDateString().'. Please contact your organization administrator to request a new invitation.',
                'invitation' => $invitation,
            ]);
        }

        $user = auth()->user();

        // If authenticated user matches the invitation email
        if ($user instanceof User) {
            return view('auth.invitation-accept', [
                'invitation' => $invitation,
                'user' => $user,
                'isEmailMatch' => strtolower($user->email) === strtolower($invitation->email),
            ]);
        }

        // If not authenticated, check whether an account with this email already exists
        $existingUser = User::where('email', $invitation->email)->first();

        return view('auth.invitation-accept', [
            'invitation' => $invitation,
            'existingUser' => $existingUser,
            'isEmailMatch' => true,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        /** @var OrganizationInvitation|null $invitation */
        $invitation = OrganizationInvitation::with('organization')
            ->where('token', $token)
            ->first();

        if (! $invitation || ! $invitation->isValid()) {
            abort(400, 'This invitation has expired or is invalid.');
        }

        $user = auth()->user();

        if ($user instanceof User) {
            $invitation->accept($user);

            return redirect("/admin/{$invitation->organization->slug}")
                ->with('status', 'Welcome! You have successfully joined '.$invitation->organization->name.'.');
        }

        // If not authenticated and account already exists, redirect to login
        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser) {
            return redirect('/admin/login')
                ->with('status', 'Please sign in with your password to complete acceptance of your invitation to '.$invitation->organization->name.'.');
        }

        // New user registration flow
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $invitation->email,
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        $invitation->accept($newUser);

        auth()->login($newUser);

        return redirect("/admin/{$invitation->organization->slug}")
            ->with('status', 'Welcome to ServiceFlow! You have joined '.$invitation->organization->name.'.');
    }
}
