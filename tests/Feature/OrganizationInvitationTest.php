<?php

use App\Filament\Resources\TeamMembers\Pages\ManageTeamMembers;
use App\MembershipStatus;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::create([
        'name' => 'Invitation Acme Corp',
        'slug' => 'invitation-acme-corp',
    ]);

    $this->mainBranch = Branch::create([
        'organization_id' => $this->org->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
    ]);

    $this->owner = User::factory()->create([
        'name' => 'Org Owner',
        'email' => 'owner@invitationacme.com',
        'must_change_password' => false,
    ]);
    $this->ownerMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->owner->id,
        'status' => MembershipStatus::Active,
    ]);
    $this->ownerMembership->assignRole(UserRole::OrganizationOwner);

    $this->admin = User::factory()->create([
        'name' => 'Org Admin',
        'email' => 'admin@invitationacme.com',
        'must_change_password' => false,
    ]);
    $this->adminMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->admin->id,
        'status' => MembershipStatus::Active,
    ]);
    $this->adminMembership->assignRole(UserRole::Admin);

    $this->tech = User::factory()->create([
        'name' => 'Org Tech',
        'email' => 'tech@invitationacme.com',
        'must_change_password' => false,
    ]);
    $this->techMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->tech->id,
        'status' => MembershipStatus::Active,
    ]);
    $this->techMembership->assignRole(UserRole::Technician);
});

it('allows organization admin to send an invitation via manage team members', function () {
    $this->actingAs($this->admin);
    Filament::setTenant($this->org);

    Livewire::test(ManageTeamMembers::class)
        ->callAction('inviteMember', [
            'email' => 'newtech@example.com',
            'role' => UserRole::Technician->value,
        ])
        ->assertHasNoActionErrors();

    $invitation = OrganizationInvitation::where('email', 'newtech@example.com')
        ->where('organization_id', $this->org->id)
        ->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->role)->toBe(UserRole::Technician)
        ->and($invitation->invited_by)->toBe($this->admin->id)
        ->and($invitation->isValid())->toBeTrue()
        ->and($invitation->expires_at->isFuture())->toBeTrue();
});

it('prevents an organization admin from inviting an organization owner', function () {
    $this->actingAs($this->admin);
    Filament::setTenant($this->org);

    Livewire::test(ManageTeamMembers::class)
        ->callAction('inviteMember', [
            'email' => 'coowner@example.com',
            'role' => UserRole::OrganizationOwner->value,
        ])
        ->assertHasActionErrors(['role']);

    expect(OrganizationInvitation::where('email', 'coowner@example.com')->exists())->toBeFalse();
});

it('allows an organization owner to invite another organization owner', function () {
    $this->actingAs($this->owner);
    Filament::setTenant($this->org);

    Livewire::test(ManageTeamMembers::class)
        ->callAction('inviteMember', [
            'email' => 'coowner@example.com',
            'role' => UserRole::OrganizationOwner->value,
        ])
        ->assertHasNoActionErrors();

    $invitation = OrganizationInvitation::where('email', 'coowner@example.com')->first();
    expect($invitation)->not->toBeNull()
        ->and($invitation->role)->toBe(UserRole::OrganizationOwner);
});

it('prevents inviting an email that already belongs to an active member', function () {
    $this->actingAs($this->admin);
    Filament::setTenant($this->org);

    Livewire::test(ManageTeamMembers::class)
        ->callAction('inviteMember', [
            'email' => $this->tech->email,
            'role' => UserRole::Dispatcher->value,
        ])
        ->assertHasActionErrors(['email']);
});

it('revokes an invitation successfully', function () {
    $invitation = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'revoke@example.com',
        'role' => UserRole::Dispatcher,
        'token' => Str::random(40),
        'invited_by' => $this->admin->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($this->admin);
    Filament::setTenant($this->org);

    Livewire::test(ManageTeamMembers::class)
        ->call('revokeInvitation', $invitation->id);

    expect(OrganizationInvitation::find($invitation->id))->toBeNull();
});

it('renders invalid, expired, and already accepted status pages appropriately', function () {
    // 1. Invalid token
    $response = $this->get(route('invitations.show', 'non-existent-token'));
    $response->assertOk();
    $response->assertSee('Invalid Invitation');

    // 2. Expired invitation
    $expired = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'expired@example.com',
        'role' => UserRole::Technician,
        'token' => Str::random(40),
        'invited_by' => $this->admin->id,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->get(route('invitations.show', $expired->token));
    $response->assertOk();
    $response->assertSee('Invitation Expired');

    // 3. Accepted invitation
    $accepted = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'accepted@example.com',
        'role' => UserRole::Technician,
        'token' => Str::random(40),
        'invited_by' => $this->admin->id,
        'expires_at' => now()->addDays(5),
        'accepted_at' => now()->subHour(),
    ]);

    $response = $this->get(route('invitations.show', $accepted->token));
    $response->assertOk();
    $response->assertSee('Invitation Already Accepted');
});

it('displays the acceptance form for a valid invitation', function () {
    $invitation = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'invitee@example.com',
        'role' => UserRole::Technician,
        'token' => Str::random(40),
        'invited_by' => $this->admin->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->get(route('invitations.show', $invitation->token));
    $response->assertOk();
    $response->assertSee('Team Invitation');
    $response->assertSee($this->org->name);
    $response->assertSee('invitee@example.com');
});

it('allows a brand-new user to accept an invitation, registering an account and joining the organization', function () {
    $invitation = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'brandnew@example.com',
        'role' => UserRole::Technician,
        'token' => Str::random(40),
        'invited_by' => $this->admin->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->post(route('invitations.accept', $invitation->token), [
        'name' => 'Brand New User',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $response->assertRedirect("/admin/{$this->org->slug}");

    $user = User::where('email', 'brandnew@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Brand New User')
        ->and(Hash::check('secret1234', $user->password))->toBeTrue()
        ->and(auth()->id())->toBe($user->id);

    // Membership check
    $membership = Membership::where('organization_id', $this->org->id)
        ->where('user_id', $user->id)
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->hasRole(UserRole::Technician))->toBeTrue()
        ->and($membership->branches()->pluck('code')->all())->toContain('MAIN');

    $invitation->refresh();
    expect($invitation->isAccepted())->toBeTrue();
});

it('allows an already authenticated user to accept an invitation and join organization', function () {
    $existingUser = User::factory()->create([
        'name' => 'Existing Multi-Org User',
        'email' => 'multiorg@example.com',
        'must_change_password' => false,
    ]);

    $invitation = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'multiorg@example.com',
        'role' => UserRole::Dispatcher,
        'token' => Str::random(40),
        'invited_by' => $this->admin->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($existingUser);

    $response = $this->post(route('invitations.accept', $invitation->token));
    $response->assertRedirect("/admin/{$this->org->slug}");

    $membership = Membership::where('organization_id', $this->org->id)
        ->where('user_id', $existingUser->id)
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->hasRole(UserRole::Dispatcher))->toBeTrue();

    $invitation->refresh();
    expect($invitation->isAccepted())->toBeTrue();
});

it('rejects POST accept on an expired invitation with 400', function () {
    $expired = OrganizationInvitation::create([
        'organization_id' => $this->org->id,
        'email' => 'expired@example.com',
        'role' => UserRole::Technician,
        'token' => Str::random(40),
        'invited_by' => $this->admin->id,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->post(route('invitations.accept', $expired->token), [
        'name' => 'Expired User',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $response->assertStatus(400);
});
