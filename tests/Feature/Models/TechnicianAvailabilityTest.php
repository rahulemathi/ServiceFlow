<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\TechnicianAvailability;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

it('saves weekly availability for an active organization technician', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $technician = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $technician->id,
    ]);
    $membership->assignRole(UserRole::Technician);

    $availability = TechnicianAvailability::create([
        'organization_id' => $organization->id,
        'user_id' => $technician->id,
        'day_of_week' => 1,
        'starts_at' => '08:00:00',
        'ends_at' => '17:00:00',
    ]);

    $this->assertModelExists($availability);
});

it('rejects availability that ends before it starts', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $technician = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $technician->id,
    ]);
    $membership->assignRole(UserRole::Technician);

    expect(fn () => TechnicianAvailability::create([
        'organization_id' => $organization->id,
        'user_id' => $technician->id,
        'day_of_week' => 1,
        'starts_at' => '17:00:00',
        'ends_at' => '08:00:00',
    ]))->toThrow(ValidationException::class);
});

it('rejects availability assigned to a user outside its organization', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $otherOrganization = Organization::create([
        'name' => 'Westside Service',
        'slug' => 'westside-service',
    ]);
    $technician = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $otherOrganization->id,
        'user_id' => $technician->id,
    ]);
    $membership->assignRole(UserRole::Technician);

    expect(fn () => TechnicianAvailability::create([
        'organization_id' => $organization->id,
        'user_id' => $technician->id,
        'day_of_week' => 1,
        'starts_at' => '08:00:00',
        'ends_at' => '17:00:00',
    ]))->toThrow(QueryException::class);
});
