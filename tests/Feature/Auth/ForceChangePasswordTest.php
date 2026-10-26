<?php

use App\Filament\Pages\Auth\Login;
use App\MembershipStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->organization = Organization::create([
        'name' => 'Acme Services',
        'slug' => 'acme-services',
    ]);

    $this->user = User::factory()->create([
        'name' => 'Alex Tech',
        'email' => 'alex.tech@acmeservices.com',
        'password' => Hash::make('temporary-pass-123'),
        'must_change_password' => true,
    ]);

    $membership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->user->id,
        'status' => MembershipStatus::Active,
    ]);
    $membership->assignRole(UserRole::Admin);

    $this->actingAs($this->user);
    Filament::setTenant($this->organization);
});

it('redirects user with must_change_password flag away from dashboard to change password screen', function () {
    $this->actingAs($this->user)
        ->get("/admin/{$this->organization->slug}")
        ->assertRedirect(route('filament.admin.password.change'));
});

it('renders the forced change password screen with user info and sign-out option', function () {
    $response = $this->actingAs($this->user)
        ->get(route('filament.admin.password.change'));

    $response->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('Set a New Password')
        ->toContain('Alex Tech')
        ->toContain('alex.tech@acmeservices.com')
        ->toContain('Current / Temporary Password')
        ->toContain('New Password')
        ->toContain('Confirm New Password')
        ->toContain(Filament::getLogoutUrl());
});

it('rejects incorrect current password', function () {
    $response = $this->actingAs($this->user)
        ->post(route('filament.admin.password.update'), [
            'current_password' => 'wrong-pass',
            'password' => 'NewSecurePassword123',
            'password_confirmation' => 'NewSecurePassword123',
        ]);

    $response->assertSessionHasErrors(['current_password']);
    expect($this->user->fresh()->must_change_password)->toBeTrue();
    expect(Hash::check('temporary-pass-123', $this->user->fresh()->password))->toBeTrue();
});

it('rejects new password if identical to temporary password', function () {
    $response = $this->actingAs($this->user)
        ->post(route('filament.admin.password.update'), [
            'current_password' => 'temporary-pass-123',
            'password' => 'temporary-pass-123',
            'password_confirmation' => 'temporary-pass-123',
        ]);

    $response->assertSessionHasErrors(['password']);
    expect($this->user->fresh()->must_change_password)->toBeTrue();
});

it('successfully updates password, clears must_change_password flag, and grants dashboard access', function () {
    $response = $this->actingAs($this->user)
        ->post(route('filament.admin.password.update'), [
            'current_password' => 'temporary-pass-123',
            'password' => 'NewBrandPass2026!',
            'password_confirmation' => 'NewBrandPass2026!',
        ]);

    $response->assertRedirect(Filament::getUrl() ?? '/admin');

    $this->user->refresh();
    expect($this->user->must_change_password)->toBeFalse()
        ->and(Hash::check('NewBrandPass2026!', $this->user->password))->toBeTrue();

    // Now they can freely access the dashboard without being redirected
    $this->get("/admin/{$this->organization->slug}")
        ->assertOk();
});

it('redirects users who have already changed password away from password change screen', function () {
    $this->user->update(['must_change_password' => false]);

    $this->actingAs($this->user)
        ->get(route('filament.admin.password.change'))
        ->assertRedirect(Filament::getUrl() ?? '/admin');
});

it('allows a newly created technician or dispatcher to sign in without credentials mismatch error', function () {
    $technician = User::factory()->create([
        'email' => 'tech1@acmeservices.com',
        'password' => 'AssignedPass123!',
        'must_change_password' => true,
    ]);

    $membership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $technician->id,
        'status' => MembershipStatus::Active,
    ]);
    $membership->assignRole(UserRole::Technician);

    auth()->logout();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'tech1@acmeservices.com',
            'password' => 'AssignedPass123!',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($technician);
});
