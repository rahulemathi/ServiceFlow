<?php

use App\Filament\Resources\MaintenanceContracts\Pages\ManageMaintenanceContracts;
use App\MaintenanceContractStatus;
use App\MaintenanceFrequency;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\MaintenanceContract;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use App\WorkOrderStatus;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('generates a scheduled work order from an active maintenance contract in filament', function () {
    $organization = Organization::create([
        'name' => 'Metro HVAC Care',
        'slug' => 'metro-hvac-care',
    ]);
    $user = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    $membership->assignRole(UserRole::OrganizationOwner);

    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Acme Towers',
    ]);

    $equipment = Equipment::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'name' => 'Main Chiller Unit',
    ]);

    $contract = MaintenanceContract::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'equipment_id' => $equipment->id,
        'name' => 'Quarterly Chiller Maintenance',
        'frequency' => MaintenanceFrequency::Quarterly,
        'starts_at' => '2026-01-01',
        'next_service_at' => '2026-04-01',
        'status' => MaintenanceContractStatus::Active,
        'amount' => 15000,
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    Livewire::test(ManageMaintenanceContracts::class)
        ->callAction(TestAction::make('generateWorkOrder')->table($contract));

    $contract->refresh();

    // Verify work order was generated
    $workOrder = WorkOrder::where('organization_id', $organization->id)
        ->where('customer_id', $customer->id)
        ->first();

    expect($workOrder)->not->toBeNull()
        ->and($workOrder->title)->toContain('Preventive Maintenance')
        ->and($workOrder->status)->toBe(WorkOrderStatus::New)
        ->and($workOrder->equipment_id)->toBe($equipment->id)
        ->and($workOrder->scheduled_start_at->toDateString())->toBe('2026-04-01');

    // Verify next service date was advanced by 3 months (Quarterly)
    expect($contract->next_service_at->toDateString())->toBe('2026-07-01')
        ->and($contract->status)->toBe(MaintenanceContractStatus::Active);
});

it('expires maintenance contract when next service date passes ends_at', function () {
    $organization = Organization::create([
        'name' => 'Apex Maintenance',
        'slug' => 'apex-maintenance',
    ]);

    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'City Hospital',
    ]);

    $contract = MaintenanceContract::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'name' => 'Annual Generator Inspection',
        'frequency' => MaintenanceFrequency::Annual,
        'starts_at' => '2025-01-01',
        'ends_at' => '2026-06-01',
        'next_service_at' => '2026-01-01',
        'status' => MaintenanceContractStatus::Active,
        'amount' => 5000,
    ]);

    $workOrder = $contract->generateWorkOrder();

    $contract->refresh();

    expect($workOrder)->not->toBeNull()
        ->and($contract->status)->toBe(MaintenanceContractStatus::Expired)
        ->and($contract->next_service_at)->toBeNull();
});
