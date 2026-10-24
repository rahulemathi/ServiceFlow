<?php

use App\MembershipStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;

it('allows active organization team members to access the admin panel', function (UserRole $role) {
    $organization = Organization::create([
        'name' => 'Acme Services',
        'slug' => 'acme-services',
    ]);
    $user = User::factory()->create();

    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'status' => MembershipStatus::Active,
    ]);
    $membership->assignRole($role);

    $this->actingAs($user)
        ->get("/admin/{$organization->slug}")
        ->assertOk();
})->with([
    'organization owner' => UserRole::OrganizationOwner,
    'organization admin' => UserRole::Admin,
    'dispatcher' => UserRole::Dispatcher,
    'technician' => UserRole::Technician,
]);

it('allows platform admins to access the admin panel', function () {
    $organization = Organization::create([
        'name' => 'Platform tenant',
        'slug' => 'platform-tenant',
    ]);
    $user = User::factory()->create();
    $user->assignPlatformRole(UserRole::PlatformAdmin);

    $this->actingAs($user)
        ->get("/admin/{$organization->slug}")
        ->assertOk();
});

it('denies admin panel access without an active organization role', function (?UserRole $role, MembershipStatus $status) {
    $organization = Organization::create([
        'name' => 'Acme Services',
        'slug' => 'acme-services',
    ]);
    $user = User::factory()->create();

    if ($role !== null) {
        $membership = Membership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => $status,
        ]);
        $membership->assignRole($role);
    }

    $this->actingAs($user)
        ->get("/admin/{$organization->slug}")
        ->assertForbidden();
})->with([
    'no organization membership' => [null, MembershipStatus::Active],
    'invited organization owner' => [UserRole::OrganizationOwner, MembershipStatus::Invited],
    'suspended organization admin' => [UserRole::Admin, MembershipStatus::Suspended],
    'suspended technician' => [UserRole::Technician, MembershipStatus::Suspended],
    'suspended dispatcher' => [UserRole::Dispatcher, MembershipStatus::Suspended],
]);
