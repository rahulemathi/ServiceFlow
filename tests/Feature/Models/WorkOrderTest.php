<?php

use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use App\WorkOrderStatus;
use Illuminate\Validation\ValidationException;

it('requires schedule times before a work order can be scheduled', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Asha Rao',
    ]);
    $workOrder = WorkOrder::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'title' => 'Repair',
    ]);

    expect(fn () => $workOrder->transitionTo(WorkOrderStatus::Scheduled))
        ->toThrow(ValidationException::class);

    expect($workOrder->fresh()->status)->toBe(WorkOrderStatus::New);
});

it('records valid work order transitions and completion time', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Asha Rao',
    ]);
    $workOrder = WorkOrder::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'title' => 'Repair',
        'scheduled_start_at' => today()->setTime(9, 0),
        'scheduled_end_at' => today()->setTime(10, 0),
    ]);

    $workOrder->transitionTo(WorkOrderStatus::Scheduled);
    $workOrder->transitionTo(WorkOrderStatus::InProgress);
    $workOrder->transitionTo(WorkOrderStatus::Completed);

    expect($workOrder->fresh()->status)->toBe(WorkOrderStatus::Completed)
        ->and($workOrder->fresh()->completed_at)->not->toBeNull();
});

it('rejects work order schedules whose end is not later than the start', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Asha Rao',
    ]);

    expect(fn () => WorkOrder::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'title' => 'Repair',
        'scheduled_start_at' => today()->setTime(10, 0),
        'scheduled_end_at' => today()->setTime(9, 0),
    ]))->toThrow(ValidationException::class);
});

it('rejects assignment to a technician from another organization', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $otherOrganization = Organization::create([
        'name' => 'Westside Service',
        'slug' => 'westside-service',
    ]);
    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Asha Rao',
    ]);
    $technician = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $otherOrganization->id,
        'user_id' => $technician->id,
    ]);
    $membership->assignRole(UserRole::Technician);

    expect(fn () => WorkOrder::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'assigned_to_user_id' => $technician->id,
        'title' => 'Repair',
    ]))->toThrow(ValidationException::class);
});
