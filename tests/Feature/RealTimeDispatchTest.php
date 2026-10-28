<?php

use App\Events\WorkOrderAssigned;
use App\Events\WorkOrderUpdated;
use App\Filament\Resources\ServiceRequests\Pages\ManageServiceRequests;
use App\Filament\Resources\WorkOrders\Pages\ManageWorkOrders;
use App\Livewire\Technician\TechnicianWorkspace;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\ServiceRequestStatus;
use App\UserRole;
use App\WorkOrderStatus;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

it('dispatches WorkOrderAssigned event when work order is created with an assigned technician', function () {
    Event::fake([WorkOrderAssigned::class, WorkOrderUpdated::class]);

    $organization = Organization::create([
        'name' => 'Apex Cooling',
        'slug' => 'apex-cooling',
    ]);

    $tech = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $tech->id,
    ]);
    $membership->assignRole(UserRole::Technician);

    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Metro Hospital',
    ]);

    $workOrder = WorkOrder::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'assigned_to_user_id' => $tech->id,
        'title' => 'HVAC Emergency Service',
    ]);

    Event::assertDispatched(WorkOrderAssigned::class, function (WorkOrderAssigned $event) use ($workOrder, $tech) {
        $channels = collect($event->broadcastOn())->map->name;

        return $event->workOrder->id === $workOrder->id
            && $channels->contains("private-technician.{$tech->id}")
            && $channels->contains("private-organization.{$workOrder->organization_id}");
    });
});

it('dispatches WorkOrderAssigned event upon service request conversion with technician assignment', function () {
    Event::fake([WorkOrderAssigned::class, WorkOrderUpdated::class]);

    $organization = Organization::create([
        'name' => 'Apex Air',
        'slug' => 'apex-air',
    ]);

    $dispatcher = User::factory()->create();
    $dispMembership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $dispatcher->id,
    ]);
    $dispMembership->assignRole(UserRole::Dispatcher);

    $tech = User::factory()->create();
    $techMembership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $tech->id,
    ]);
    $techMembership->assignRole(UserRole::Technician);

    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Grand Hotel',
    ]);

    $request = ServiceRequest::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'title' => 'Chiller pump failure',
        'description' => 'Alarm sounding on floor 2.',
    ]);

    $this->actingAs($dispatcher);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    Livewire::test(ManageServiceRequests::class)
        ->callAction(TestAction::make('convertToWorkOrder')->table($request), [
            'assigned_to_user_id' => $tech->id,
        ]);

    expect($request->fresh()->status)->toBe(ServiceRequestStatus::Converted);

    Event::assertDispatched(WorkOrderAssigned::class, function (WorkOrderAssigned $event) use ($tech) {
        return (int) $event->workOrder->assigned_to_user_id === (int) $tech->id;
    });
});

it('handles real-time broadcast in technician workspace component', function () {
    $organization = Organization::create([
        'name' => 'Apex HVAC',
        'slug' => 'apex-hvac',
    ]);

    $tech = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $tech->id,
    ]);
    $membership->assignRole(UserRole::Technician);

    $this->actingAs($tech);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    $component = Livewire::test(TechnicianWorkspace::class);

    $component->call('onWorkOrderAssigned', [
        'work_order_number' => 'WO-9988',
        'title' => 'Urgent Server Room Cooling',
    ]);

    $component->assertSee('WO-9988')
        ->assertSee('Urgent Server Room Cooling');
});

it('updates work orders page status in real time without toast on admin panel', function () {
    $organization = Organization::create([
        'name' => 'Metro Dispatch',
        'slug' => 'metro-dispatch',
    ]);

    $admin = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $admin->id,
    ]);
    $membership->assignRole(UserRole::Admin);

    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Tech Center',
    ]);

    $workOrder = WorkOrder::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'title' => 'Main AC Unit Repair',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => now(),
        'scheduled_end_at' => now()->addHour(),
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    $test = Livewire::test(ManageWorkOrders::class);
    $test->assertSee('Scheduled');

    // Simulate technician starting work (status -> in_progress)
    $workOrder->transitionTo(WorkOrderStatus::InProgress);

    // Livewire refresh triggered by Reverb event or poll
    $test->call('$refresh');

    $test->assertSee('In Progress');
});
