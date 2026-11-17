<?php

use App\Filament\Resources\TeamMembers\Pages\ManageTeamMembers;
use App\MembershipStatus;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    // Org A setup
    $this->orgA = Organization::create([
        'name' => 'Organization Alpha',
        'slug' => 'org-alpha',
    ]);
    $this->branchA = Branch::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Alpha HQ Branch',
        'code' => 'ALPHA-01',
    ]);
    $this->adminA = User::factory()->create([
        'name' => 'Alpha Admin',
        'email' => 'admin@alpha.com',
        'password' => Hash::make('AdminPass123'),
        'must_change_password' => false,
    ]);
    $this->membershipAdminA = Membership::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->adminA->id,
        'status' => MembershipStatus::Active,
    ]);
    $this->membershipAdminA->assignRole(UserRole::Admin);

    // Org B setup
    $this->orgB = Organization::create([
        'name' => 'Organization Beta',
        'slug' => 'org-beta',
    ]);
    $this->branchB = Branch::create([
        'organization_id' => $this->orgB->id,
        'name' => 'Beta HQ Branch',
        'code' => 'BETA-01',
    ]);
    $this->adminB = User::factory()->create([
        'name' => 'Beta Admin',
        'email' => 'admin@beta.com',
        'password' => Hash::make('AdminPass456'),
        'must_change_password' => false,
    ]);
    $this->membershipAdminB = Membership::create([
        'organization_id' => $this->orgB->id,
        'user_id' => $this->adminB->id,
        'status' => MembershipStatus::Active,
    ]);
    $this->membershipAdminB->assignRole(UserRole::Admin);

    // Multi-tenant shared technician user
    $this->sharedUser = User::factory()->create([
        'name' => 'Shared Technician',
        'email' => 'shared.tech@serviceflow.com',
        'password' => Hash::make('OriginalSecurePassword!'),
        'must_change_password' => false,
    ]);

    // Membership in Org A
    $this->membershipA = Membership::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->sharedUser->id,
        'status' => MembershipStatus::Active,
    ]);
    $this->membershipA->assignRole(UserRole::Technician);
    $this->membershipA->assignBranch($this->branchA);

    // Membership in Org B
    $this->membershipB = Membership::create([
        'organization_id' => $this->orgB->id,
        'user_id' => $this->sharedUser->id,
        'status' => MembershipStatus::Active,
    ]);
    $this->membershipB->assignRole(UserRole::Dispatcher);
    $this->membershipB->assignBranch($this->branchB);
});

it('allows an organization admin to update permitted membership fields', function () {
    $this->actingAs($this->adminA);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->orgA);

    Livewire::test(ManageTeamMembers::class)
        ->callTableAction('edit', $this->membershipA, data: [
            'status' => MembershipStatus::Suspended->value,
            'roles' => [UserRole::ServiceManager->value],
            'branches' => [$this->branchA->id],
        ])
        ->assertHasNoTableActionErrors();

    $this->membershipA->refresh();
    expect($this->membershipA->status)->toBe(MembershipStatus::Suspended)
        ->and($this->membershipA->hasRole(UserRole::ServiceManager))->toBeTrue()
        ->and($this->membershipA->hasRole(UserRole::Technician))->toBeFalse();
});

it('ensures membership updates do not modify global user email, name, or password', function () {
    $this->actingAs($this->adminA);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->orgA);

    $originalName = $this->sharedUser->name;
    $originalEmail = $this->sharedUser->email;
    $originalPasswordHash = $this->sharedUser->password;
    $originalMustChange = $this->sharedUser->must_change_password;

    Livewire::test(ManageTeamMembers::class)
        ->callTableAction('edit', $this->membershipA, data: [
            'name' => 'Malicious New Name',
            'email' => 'hijacked@malicious.com',
            'password' => 'HackedPassword123!',
            'must_change_password' => true,
            'status' => MembershipStatus::Active->value,
            'roles' => [UserRole::Technician->value],
            'branches' => [$this->branchA->id],
        ])
        ->assertHasNoTableActionErrors();

    $this->sharedUser->refresh();

    // Global user properties must be completely unaltered
    expect($this->sharedUser->name)->toBe($originalName)
        ->and($this->sharedUser->email)->toBe($originalEmail)
        ->and($this->sharedUser->password)->toBe($originalPasswordHash)
        ->and($this->sharedUser->must_change_password)->toBe($originalMustChange)
        ->and(Hash::check('OriginalSecurePassword!', $this->sharedUser->password))->toBeTrue()
        ->and(Hash::check('HackedPassword123!', $this->sharedUser->password))->toBeFalse();
});

it('ensures updating one membership does not alter another organization membership of the same user', function () {
    $this->actingAs($this->adminA);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->orgA);

    // Org A admin suspends the user's Org A membership and changes Org A roles
    Livewire::test(ManageTeamMembers::class)
        ->callTableAction('edit', $this->membershipA, data: [
            'status' => MembershipStatus::Suspended->value,
            'roles' => [UserRole::Accountant->value],
            'branches' => [],
        ])
        ->assertHasNoTableActionErrors();

    $this->membershipA->refresh();
    $this->membershipB->refresh();

    // Org A membership is updated
    expect($this->membershipA->status)->toBe(MembershipStatus::Suspended)
        ->and($this->membershipA->hasRole(UserRole::Accountant))->toBeTrue()
        ->and(DB::table('membership_branches')->where('membership_id', $this->membershipA->id)->count())->toBe(0);

    // Org B membership is completely unaffected
    expect($this->membershipB->organization_id)->toBe($this->orgB->id)
        ->and($this->membershipB->status)->toBe(MembershipStatus::Active)
        ->and($this->membershipB->hasRole(UserRole::Dispatcher))->toBeTrue()
        ->and($this->membershipB->hasRole(UserRole::Accountant))->toBeFalse()
        ->and(DB::table('membership_branches')->where('membership_id', $this->membershipB->id)->pluck('branch_id')->all())->toContain($this->branchB->id);
});

it('does not overwrite existing user credentials when adding an existing user to a new organization', function () {
    $newOrg = Organization::create([
        'name' => 'Organization Gamma',
        'slug' => 'org-gamma',
    ]);
    $newBranch = Branch::create([
        'organization_id' => $newOrg->id,
        'name' => 'Gamma Branch',
        'code' => 'GAM-01',
    ]);
    $adminGamma = User::factory()->create([
        'name' => 'Gamma Admin',
        'email' => 'admin@gamma.com',
    ]);
    $membershipGamma = Membership::create([
        'organization_id' => $newOrg->id,
        'user_id' => $adminGamma->id,
        'status' => MembershipStatus::Active,
    ]);
    $membershipGamma->assignRole(UserRole::Admin);

    $this->actingAs($adminGamma);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($newOrg);

    $originalName = $this->sharedUser->name;
    $originalPasswordHash = $this->sharedUser->password;

    Livewire::test(ManageTeamMembers::class)
        ->callAction('create', data: [
            'name' => 'Gamma Overwrite Attempt',
            'email' => $this->sharedUser->email,
            'password' => 'NewPassword999!',
            'must_change_password' => true,
            'roles' => [UserRole::Technician->value],
            'status' => MembershipStatus::Active->value,
            'branches' => [$newBranch->id],
        ])
        ->assertHasNoActionErrors();

    // Verify existing user credentials were not altered
    $this->sharedUser->refresh();
    expect($this->sharedUser->name)->toBe($originalName)
        ->and($this->sharedUser->password)->toBe($originalPasswordHash)
        ->and($this->sharedUser->must_change_password)->toBeFalse()
        ->and(Hash::check('OriginalSecurePassword!', $this->sharedUser->password))->toBeTrue();

    // Verify membership in Gamma was created
    $newMembership = Membership::where('organization_id', $newOrg->id)
        ->where('user_id', $this->sharedUser->id)
        ->first();
    expect($newMembership)->not->toBeNull()
        ->and($newMembership->status)->toBe(MembershipStatus::Active)
        ->and($newMembership->hasRole(UserRole::Technician))->toBeTrue();
});

it('rejects unauthorized membership edits across tenant boundaries', function () {
    // Org A admin tries to edit Org B membership
    $this->actingAs($this->adminA);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->orgA);

    // Table query scoping prevents seeing or operating on Org B memberships
    Livewire::test(ManageTeamMembers::class)
        ->assertCanSeeTableRecords([$this->membershipA])
        ->assertCanNotSeeTableRecords([$this->membershipB]);
});

it('prevents regular org admins from editing an organization owner membership', function () {
    // Create an owner for Org A
    $ownerA = User::factory()->create(['email' => 'owner@alpha.com']);
    $ownerMembershipA = Membership::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $ownerA->id,
        'status' => MembershipStatus::Active,
    ]);
    $ownerMembershipA->assignRole(UserRole::OrganizationOwner);

    // Regular admin signs into Org A
    $this->actingAs($this->adminA);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->orgA);

    Livewire::test(ManageTeamMembers::class)
        ->assertTableActionHidden('edit', $ownerMembershipA);
});
