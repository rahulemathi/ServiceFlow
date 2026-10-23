<?php

use App\Filament\Resources\ServiceRequests\Pages\ManageServiceRequests;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\User;
use App\ServiceRequestStatus;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('converts a tenant service request to one work order from its table action', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $user = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    $membership->assignRole(UserRole::OrganizationOwner);
    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Asha Rao',
    ]);
    $request = ServiceRequest::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'title' => 'Compressor repair',
        'description' => 'Unit does not cool.',
    ]);
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    Livewire::test(ManageServiceRequests::class)
        ->callAction(TestAction::make('convertToWorkOrder')->table($request));

    $this->assertDatabaseHas('work_orders', [
        'organization_id' => $organization->id,
        'service_request_id' => $request->id,
        'customer_id' => $customer->id,
    ]);
    expect($request->fresh()->status)->toBe(ServiceRequestStatus::Converted)
        ->and($request->workOrder()->count())->toBe(1);
});
