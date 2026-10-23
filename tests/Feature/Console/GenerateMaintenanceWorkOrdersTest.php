<?php

use App\MaintenanceContractStatus;
use App\MaintenanceFrequency;
use App\Models\Customer;
use App\Models\MaintenanceContract;
use App\Models\Organization;
use App\Models\WorkOrder;

it('generates work orders via artisan command for contracts due today or earlier', function () {
    $organization = Organization::create([
        'name' => 'Swift Services',
        'slug' => 'swift-services',
    ]);

    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Metro Plaza',
    ]);

    $dueContract = MaintenanceContract::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'name' => 'Monthly AC Filter Change',
        'frequency' => MaintenanceFrequency::Monthly,
        'starts_at' => now()->subMonths(2)->toDateString(),
        'next_service_at' => now()->subDay()->toDateString(),
        'status' => MaintenanceContractStatus::Active,
        'amount' => 2000,
    ]);

    $futureContract = MaintenanceContract::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'name' => 'Next Month Inspection',
        'frequency' => MaintenanceFrequency::Monthly,
        'starts_at' => now()->toDateString(),
        'next_service_at' => now()->addMonth()->toDateString(),
        'status' => MaintenanceContractStatus::Active,
        'amount' => 2000,
    ]);

    $this->artisan('serviceflow:generate-maintenance-work-orders')
        ->expectsOutput('Generated 1 maintenance work order(s).')
        ->assertSuccessful();

    expect(WorkOrder::where('organization_id', $organization->id)->count())->toBe(1);

    $dueContract->refresh();
    $futureContract->refresh();

    expect($dueContract->next_service_at->greaterThan(now()->toDateString()))->toBeTrue();
});
