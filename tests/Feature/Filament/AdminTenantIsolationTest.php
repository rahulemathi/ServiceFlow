<?php

use App\Filament\Widgets\ServiceOverviewWidget;
use App\Filament\Widgets\TechnicianUtilizationWidget;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\TechnicianAvailability;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use App\WorkOrderStatus;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('shows records for the selected organization and hides other organizations', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $otherOrganization = Organization::create([
        'name' => 'Westside Service',
        'slug' => 'westside-service',
    ]);
    $user = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    $membership->assignRole(UserRole::OrganizationOwner);

    Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Northside customer',
    ]);
    Customer::create([
        'organization_id' => $otherOrganization->id,
        'name' => 'Westside customer',
    ]);

    $this->actingAs($user)
        ->get("/admin/{$organization->slug}/customers")
        ->assertOk()
        ->assertSee('Northside customer')
        ->assertDontSee('Westside customer');
});

it('does not allow an organization administrator to open another organization panel', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $otherOrganization = Organization::create([
        'name' => 'Westside Service',
        'slug' => 'westside-service',
    ]);
    $user = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    $membership->assignRole(UserRole::OrganizationOwner);

    $this->actingAs($user)
        ->get("/admin/{$otherOrganization->slug}/customers")
        ->assertNotFound();
});

it('renders database-backed dashboard widgets for the active organization', function () {
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

    $this->actingAs($user)
        ->get("/admin/{$organization->slug}")
        ->assertOk()
        ->assertSee('ServiceOverviewWidget')
        ->assertSee('ServiceRequestTrendWidget')
        ->assertSee('WorkOrderStatusChartWidget')
        ->assertSee('TechnicianUtilizationWidget')
        ->assertSee('UpcomingMaintenanceWidget');
});

it('opens every implemented tenant resource index', function () {
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

    foreach ([
        'branches',
        'customers',
        'customer-addresses',
        'equipment',
        'service-requests',
        'work-orders',
        'maintenance-contracts',
        'technician-availabilities',
        'estimates',
        'invoices',
        'payments',
    ] as $resource) {
        $this->actingAs($user)
            ->get("/admin/{$organization->slug}/{$resource}")
            ->assertOk();
    }
});

it('calculates dashboard statistics using only the active organization and selected dates', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $otherOrganization = Organization::create([
        'name' => 'Westside Service',
        'slug' => 'westside-service',
    ]);
    $user = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    $membership->assignRole(UserRole::OrganizationOwner);
    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Northside customer',
    ]);
    ServiceRequest::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'title' => 'Repair request',
        'description' => 'No cooling',
    ]);
    WorkOrder::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'title' => 'Active repair',
    ]);
    ServiceRequest::create([
        'organization_id' => $otherOrganization->id,
        'customer_id' => Customer::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Westside customer',
        ])->id,
        'title' => 'Other repair request',
        'description' => 'Not in this tenant',
    ]);
    $this->actingAs($user);
    Filament::setTenant($organization);

    Livewire::test(ServiceOverviewWidget::class, [
        'pageFilters' => [
            'startDate' => today()->toDateString(),
            'endDate' => today()->toDateString(),
            'branchId' => null,
        ],
    ])
        ->assertSeeInOrder([
            'Open service requests',
            '1',
            'Active work orders',
            '1',
        ]);
});

it('calculates technician utilization from saved availability and scheduled jobs', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $owner = User::factory()->create();
    $ownerMembership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);
    $ownerMembership->assignRole(UserRole::OrganizationOwner);

    $technician = User::factory()->create();
    $technicianMembership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $technician->id,
    ]);
    $technicianMembership->assignRole(UserRole::Technician);

    $monday = today()->startOfWeek();
    TechnicianAvailability::create([
        'organization_id' => $organization->id,
        'user_id' => $technician->id,
        'day_of_week' => 1,
        'starts_at' => '08:00:00',
        'ends_at' => '12:00:00',
    ]);
    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Northside customer',
    ]);
    WorkOrder::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'assigned_to_user_id' => $technician->id,
        'title' => 'Scheduled repair',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => $monday->setTime(9, 0),
        'scheduled_end_at' => $monday->setTime(10, 0),
    ]);
    $this->actingAs($owner);
    Filament::setTenant($organization);

    Livewire::test(TechnicianUtilizationWidget::class, [
        'pageFilters' => [
            'startDate' => $monday->toDateString(),
            'endDate' => $monday->toDateString(),
            'branchId' => null,
        ],
    ])
        ->assertSee('Technician utilization')
        ->assertSee('25%');
});
