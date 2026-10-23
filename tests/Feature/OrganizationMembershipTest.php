<?php

use App\Models\Branch;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

it('keeps roles scoped to each organization membership', function () {
    $user = User::factory()->create();
    $firstOrganization = Organization::create([
        'name' => 'First Services',
        'slug' => 'first-services',
    ]);
    $secondOrganization = Organization::create([
        'name' => 'Second Services',
        'slug' => 'second-services',
    ]);

    $firstMembership = Membership::create([
        'organization_id' => $firstOrganization->id,
        'user_id' => $user->id,
    ]);
    $secondMembership = Membership::create([
        'organization_id' => $secondOrganization->id,
        'user_id' => $user->id,
    ]);

    $firstMembership->assignRole(UserRole::Dispatcher);
    $secondMembership->assignRole(UserRole::Technician);

    expect($firstMembership->hasRole(UserRole::Dispatcher))->toBeTrue()
        ->and($firstMembership->hasRole(UserRole::Technician))->toBeFalse()
        ->and($secondMembership->hasRole(UserRole::Technician))->toBeTrue()
        ->and($user->memberships()->count())->toBe(2);
});

it('rejects roles assigned outside their scope', function () {
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Acme Services',
        'slug' => 'acme-services',
    ]);
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    expect(fn () => MembershipRole::create([
        'membership_id' => $membership->id,
        'role' => UserRole::PlatformAdmin,
    ]))->toThrow(LogicException::class);

    expect(fn () => UserRoleAssignment::create([
        'user_id' => $user->id,
        'role' => UserRole::Technician,
    ]))->toThrow(LogicException::class);
});

it('rejects assignment to a branch from another organization', function () {
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'First Services',
        'slug' => 'first-services',
    ]);
    $otherOrganization = Organization::create([
        'name' => 'Second Services',
        'slug' => 'second-services',
    ]);
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    $branch = Branch::create([
        'organization_id' => $otherOrganization->id,
        'name' => 'Second Branch',
    ]);

    expect(fn () => $membership->assignBranch($branch))
        ->toThrow(LogicException::class);

    $this->assertDatabaseCount('membership_branches', 0);
});

it('enforces organization consistency on direct branch membership writes', function () {
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'First Services',
        'slug' => 'first-services',
    ]);
    $otherOrganization = Organization::create([
        'name' => 'Second Services',
        'slug' => 'second-services',
    ]);
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    $branch = Branch::create([
        'organization_id' => $otherOrganization->id,
        'name' => 'Second Branch',
    ]);

    expect(fn () => DB::table('membership_branches')->insert([
        'organization_id' => $organization->id,
        'membership_id' => $membership->id,
        'branch_id' => $branch->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
