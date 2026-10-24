<?php

use App\Filament\Resources\Support\FilamentOptions;
use App\Filament\Resources\TeamMembers\Pages\ManageTeamMembers;
use App\MembershipStatus;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->organization = Organization::create([
        'name' => 'Acme Services',
        'slug' => 'acme-services',
    ]);

    $this->branch = Branch::create([
        'organization_id' => $this->organization->id,
        'name' => 'North Branch',
        'code' => 'NB-01',
    ]);

    $this->adminUser = User::factory()->create([
        'name' => 'Admin Boss',
        'email' => 'admin@acmeservices.com',
    ]);

    $membership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->adminUser->id,
        'status' => MembershipStatus::Active,
    ]);
    $membership->assignRole(UserRole::Admin);

    $this->actingAs($this->adminUser);
    Filament::setTenant($this->organization);
});

it('renders the team members resource page for organization admins', function () {
    $response = $this->get("/admin/{$this->organization->slug}/team-members");
    $response->assertOk();

    $content = $response->getContent();
    expect($content)->toContain('Team members');
});

it('creates a new technician from the team members resource and reflects in technicians options', function () {
    expect(FilamentOptions::technicians())->toBeEmpty();

    Livewire::test(ManageTeamMembers::class)
        ->callAction('create', data: [
            'name' => 'Alex Rivera',
            'email' => 'alex.rivera@acmeservices.com',
            'password' => 'secret123',
            'roles' => [UserRole::Technician->value],
            'status' => MembershipStatus::Active->value,
            'branches' => [$this->branch->id],
        ])
        ->assertHasNoActionErrors();

    // Verify user was created
    $user = User::where('email', 'alex.rivera@acmeservices.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Alex Rivera');

    // Verify membership was created
    $membership = Membership::where('organization_id', $this->organization->id)
        ->where('user_id', $user->id)
        ->first();
    expect($membership)->not->toBeNull()
        ->and($membership->status)->toBe(MembershipStatus::Active);

    // Verify role was assigned
    expect($membership->hasRole(UserRole::Technician))->toBeTrue();

    // Verify branch was assigned
    expect($membership->branches->pluck('id')->all())->toContain($this->branch->id);

    // Verify user now shows up in FilamentOptions::technicians()
    $technicians = FilamentOptions::technicians();
    expect($technicians)->toHaveKey($user->id)
        ->and($technicians[$user->id])->toBe('Alex Rivera');
});

it('prevents creating duplicate memberships for the same email in the same organization', function () {
    Livewire::test(ManageTeamMembers::class)
        ->callAction('create', data: [
            'name' => 'Duplicate Admin',
            'email' => 'admin@acmeservices.com', // Admin already belongs to this org
            'password' => 'secret123',
            'roles' => [UserRole::Technician->value],
            'status' => MembershipStatus::Active->value,
        ])
        ->assertHasActionErrors(['email']);
});

it('updates team member roles and details', function () {
    $techUser = User::factory()->create([
        'name' => 'Sam Worker',
        'email' => 'sam@acmeservices.com',
    ]);

    $membership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $techUser->id,
        'status' => MembershipStatus::Active,
    ]);
    $membership->assignRole(UserRole::Technician);

    Livewire::test(ManageTeamMembers::class)
        ->callTableAction('edit', $membership, data: [
            'name' => 'Samuel Worker',
            'email' => 'samuel@acmeservices.com',
            'roles' => [UserRole::Technician->value, UserRole::Dispatcher->value],
            'status' => MembershipStatus::Active->value,
            'branches' => [$this->branch->id],
        ])
        ->assertHasNoTableActionErrors();

    $membership->refresh();
    expect($membership->user->name)->toBe('Samuel Worker')
        ->and($membership->user->email)->toBe('samuel@acmeservices.com')
        ->and($membership->hasRole(UserRole::Technician))->toBeTrue()
        ->and($membership->hasRole(UserRole::Dispatcher))->toBeTrue();
});

it('maintains strict organization tenant isolation for team members', function () {
    $otherOrg = Organization::create([
        'name' => 'Other Org',
        'slug' => 'other-org',
    ]);

    $otherUser = User::factory()->create(['name' => 'Other Tech']);
    $otherMembership = Membership::create([
        'organization_id' => $otherOrg->id,
        'user_id' => $otherUser->id,
        'status' => MembershipStatus::Active,
    ]);
    $otherMembership->assignRole(UserRole::Technician);

    Livewire::test(ManageTeamMembers::class)
        ->assertCanSeeTableRecords([
            Membership::where('user_id', $this->adminUser->id)->first(),
        ])
        ->assertCanNotSeeTableRecords([
            $otherMembership,
        ]);
});
