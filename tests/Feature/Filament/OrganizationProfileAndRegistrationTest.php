<?php

use App\Filament\Pages\Tenancy\EditOrganizationProfile;
use App\Filament\Pages\Tenancy\RegisterOrganization;
use App\MembershipStatus;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create([
        'name' => 'Owner User',
        'email' => 'owner@example.com',
        'must_change_password' => false,
    ]);

    $this->org = Organization::create([
        'name' => 'Original Organization',
        'slug' => 'original-org',
    ]);

    $this->ownerMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->owner->id,
        'status' => MembershipStatus::Active,
    ]);
    $this->ownerMembership->assignRole(UserRole::OrganizationOwner);

    $this->admin = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'must_change_password' => false,
    ]);

    $this->adminMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->admin->id,
        'status' => MembershipStatus::Active,
    ]);
    $this->adminMembership->assignRole(UserRole::Admin);

    $this->tech = User::factory()->create([
        'name' => 'Tech User',
        'email' => 'tech@example.com',
        'must_change_password' => false,
    ]);

    $this->techMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->tech->id,
        'status' => MembershipStatus::Active,
    ]);
    $this->techMembership->assignRole(UserRole::Technician);
});

it('allows an authenticated user to register a new organization and provisions owner role and default branch', function () {
    $newUser = User::factory()->create([
        'email' => 'founder@newcorp.com',
        'must_change_password' => false,
    ]);

    $this->actingAs($newUser);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $this->get('/admin/new')->assertOk();

    Livewire::test(RegisterOrganization::class)
        ->fillForm([
            'name' => 'Acme Climate Solutions',
            'slug' => 'acme-climate-solutions',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    // Verify organization exists
    $org = Organization::where('slug', 'acme-climate-solutions')->first();
    expect($org)->not->toBeNull()
        ->and($org->name)->toBe('Acme Climate Solutions');

    // Verify user is an Organization Owner
    $membership = Membership::where('organization_id', $org->id)
        ->where('user_id', $newUser->id)
        ->first();
    expect($membership)->not->toBeNull()
        ->and($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->hasRole(UserRole::OrganizationOwner))->toBeTrue();

    // Verify default Main branch was created and attached
    $branch = Branch::where('organization_id', $org->id)->where('code', 'MAIN')->first();
    expect($branch)->not->toBeNull()
        ->and($branch->name)->toBe('Main Branch');

    expect($membership->branches()->pluck('branches.id')->all())->toContain($branch->id);
});

it('redirects unauthenticated users attempting to register an organization to login', function () {
    $this->get('/admin/new')->assertRedirect('/admin/login');
});

it('allows organization owners to view and edit their organization profile', function () {
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->org);

    $this->get("/admin/{$this->org->slug}/profile")->assertOk();

    Livewire::test(EditOrganizationProfile::class)
        ->fillForm([
            'name' => 'Updated Organization Name',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->org->refresh();
    expect($this->org->name)->toBe('Updated Organization Name')
        ->and($this->org->slug)->toBe('original-org'); // Slug is permanent and preserved
});

it('denies organization admins and technicians from editing organization profile', function () {
    // Org Admin receives 404 Not Found on profile
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->org);

    $this->get("/admin/{$this->org->slug}/profile")->assertNotFound();

    // Technician is redirected to technician workspace
    $this->actingAs($this->tech);
    $this->get("/admin/{$this->org->slug}/profile")->assertRedirect(route('technician.workspace'));
});

it('maintains strict isolation when editing organization profiles across tenants', function () {
    $orgB = Organization::create([
        'name' => 'Beta Industries',
        'slug' => 'beta-industries',
    ]);
    $ownerB = User::factory()->create(['email' => 'owner.b@example.com']);
    $membershipB = Membership::create([
        'organization_id' => $orgB->id,
        'user_id' => $ownerB->id,
        'status' => MembershipStatus::Active,
    ]);
    $membershipB->assignRole(UserRole::OrganizationOwner);

    // Owner A edits Org A
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->org);

    Livewire::test(EditOrganizationProfile::class)
        ->fillForm([
            'name' => 'Alpha Corporation',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->org->refresh();
    $orgB->refresh();

    expect($this->org->name)->toBe('Alpha Corporation');
    expect($orgB->name)->toBe('Beta Industries');
});
