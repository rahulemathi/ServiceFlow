<?php

use App\MaintenanceContractStatus;
use App\MaintenanceFrequency;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\MaintenanceContract;
use App\Models\Organization;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->org = Organization::create([
        'name' => 'Apex Facilities Management',
        'slug' => 'apex-facilities-management',
    ]);

    $this->branch = Branch::create([
        'organization_id' => $this->org->id,
        'name' => 'Metro North Hub',
    ]);

    $this->customer = Customer::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branch->id,
        'name' => 'Global Tech Park',
        'email' => 'facilities@globaltech.test',
    ]);

    $this->equipment = Equipment::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'name' => 'Chiller Plant #1',
        'serial_number' => 'CP-9901-X',
    ]);
});

test('recurrence rules generate correctly dated work orders linked to contract and equipment', function (): void {
    $contract = MaintenanceContract::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'equipment_id' => $this->equipment->id,
        'name' => 'Quarterly Chiller Servicing',
        'frequency' => MaintenanceFrequency::Quarterly,
        'starts_at' => '2026-01-01',
        'next_service_at' => '2026-04-01',
        'status' => MaintenanceContractStatus::Active,
        'amount' => 15000,
    ]);

    $wo = $contract->generateWorkOrder(Carbon::parse('2026-04-01'));

    expect($wo)->not->toBeNull();
    expect($wo->maintenance_contract_id)->toBe($contract->id);
    expect($wo->customer_id)->toBe($this->customer->id);
    expect($wo->equipment_id)->toBe($this->equipment->id);
    expect($wo->branch_id)->toBe($this->branch->id);
    expect($wo->scheduled_start_at->toDateString())->toBe('2026-04-01');

    // Next service date advanced by 3 months (Quarterly)
    expect($contract->fresh()->next_service_at->toDateString())->toBe('2026-07-01');
});

test('repeated or concurrent scheduler runs cannot duplicate work orders', function (): void {
    $contract = MaintenanceContract::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'equipment_id' => $this->equipment->id,
        'name' => 'Monthly Filter Replacement',
        'frequency' => MaintenanceFrequency::Monthly,
        'starts_at' => now()->subMonths(3)->toDateString(),
        'next_service_at' => now()->toDateString(),
        'status' => MaintenanceContractStatus::Active,
        'amount' => 5000,
    ]);

    // First generation succeeds
    $wo1 = $contract->generateWorkOrder(now());
    expect($wo1)->not->toBeNull();

    // Repeated call for the exact same target date returns null (idempotent)
    $wo2 = $contract->generateWorkOrder(now());
    expect($wo2)->toBeNull();

    // Verify only ONE work order exists in the database
    expect(WorkOrder::where('maintenance_contract_id', $contract->id)->count())->toBe(1);
});

test('contract transitions to expired when recurrence exceeds ends_at', function (): void {
    $contract = MaintenanceContract::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'equipment_id' => $this->equipment->id,
        'name' => '1-Year Annual Inspection',
        'frequency' => MaintenanceFrequency::Annual,
        'starts_at' => today()->subMonths(11)->toDateString(),
        'ends_at' => today()->addDays(10)->toDateString(),
        'next_service_at' => today()->toDateString(),
        'status' => MaintenanceContractStatus::Active,
        'amount' => 25000,
    ]);

    expect($contract->isExpired())->toBeFalse();

    // Generating the current occurrence pushes nextDate past ends_at
    $wo = $contract->generateWorkOrder(today());
    expect($wo)->not->toBeNull();

    $contract->refresh();
    expect($contract->status)->toBe(MaintenanceContractStatus::Expired);
    expect($contract->next_service_at)->toBeNull();
    expect($contract->isExpired())->toBeTrue();
});

test('contract renewal reactivates expired contracts and schedules next service', function (): void {
    $contract = MaintenanceContract::create([
        'organization_id' => $this->org->id,
        'customer_id' => $this->customer->id,
        'name' => 'Expiring Contract',
        'frequency' => MaintenanceFrequency::Monthly,
        'starts_at' => '2025-01-01',
        'ends_at' => '2025-12-31',
        'next_service_at' => null,
        'status' => MaintenanceContractStatus::Expired,
        'amount' => 5000,
    ]);

    $newEnd = today()->addYear()->toDateString();
    $contract->renew($newEnd, 6000);

    $contract->refresh();
    expect($contract->status)->toBe(MaintenanceContractStatus::Active);
    expect((float) $contract->amount)->toEqual(6000.0);
    expect($contract->ends_at->toDateString())->toBe($newEnd);
    expect($contract->next_service_at)->not->toBeNull();
    expect($contract->isExpired())->toBeFalse();
});

test('artisan scheduler prunes expired contracts and logs observable expiration notices', function (): void {
    // Contract expiring in 15 days (within 30-day window)
    MaintenanceContract::create([
        'organization_id' => $this->org->id,
        'customer_id' => $this->customer->id,
        'name' => 'Upcoming Expiry Contract',
        'frequency' => MaintenanceFrequency::Quarterly,
        'starts_at' => now()->subMonths(11)->toDateString(),
        'ends_at' => now()->addDays(15)->toDateString(),
        'next_service_at' => now()->addDays(60)->toDateString(),
        'status' => MaintenanceContractStatus::Active,
        'amount' => 12000,
    ]);

    // Contract already past ends_at
    $pastContract = MaintenanceContract::create([
        'organization_id' => $this->org->id,
        'customer_id' => $this->customer->id,
        'name' => 'Past Contract',
        'frequency' => MaintenanceFrequency::Monthly,
        'starts_at' => now()->subYear()->toDateString(),
        'ends_at' => now()->subDay()->toDateString(),
        'next_service_at' => now()->subDay()->toDateString(),
        'status' => MaintenanceContractStatus::Active,
        'amount' => 3000,
    ]);

    $this->artisan('serviceflow:generate-maintenance-work-orders')
        ->expectsOutputToContain('Generated 0 maintenance work order(s).')
        ->expectsOutputToContain('Notice: 1 active contract(s) are expiring within the next 30 days.')
        ->assertSuccessful();

    expect($pastContract->fresh()->status)->toBe(MaintenanceContractStatus::Expired);
});
