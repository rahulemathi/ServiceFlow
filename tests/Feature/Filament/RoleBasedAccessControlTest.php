<?php

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\Estimates\EstimateResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\MaintenanceContracts\MaintenanceContractResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Resources\TeamMembers\Pages\ManageTeamMembers;
use App\Filament\Resources\TeamMembers\TeamMemberResource;
use App\Filament\Resources\TechnicianAvailabilities\TechnicianAvailabilityResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Widgets\FinanceOverviewWidget;
use App\Filament\Widgets\PlatformOverviewWidget;
use App\Filament\Widgets\RecentPaymentsWidget;
use App\Filament\Widgets\RecentServiceRequestsWidget;
use App\Filament\Widgets\ServiceOverviewWidget;
use App\Filament\Widgets\TechnicianUtilizationWidget;
use App\Filament\Widgets\UnassignedWorkOrdersWidget;
use App\Filament\Widgets\UpcomingMaintenanceWidget;
use App\Livewire\Technician\TechnicianWorkspace;
use App\MembershipStatus;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use App\WorkOrderStatus;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->organization = Organization::create([
        'name' => 'Apex Maintenance',
        'slug' => 'apex-maintenance',
    ]);
});

function createTeamUser(Organization $org, UserRole $role): User
{
    $user = User::factory()->create([
        'must_change_password' => false,
    ]);

    $membership = Membership::create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'status' => MembershipStatus::Active,
    ]);
    $membership->assignRole($role);

    return $user;
}

it('allows platform admin unrestricted platform access', function () {
    $platformAdmin = User::factory()->create([
        'must_change_password' => false,
    ]);
    $platformAdmin->assignPlatformRole(UserRole::PlatformAdmin);

    $this->actingAs($platformAdmin);
    Filament::setTenant($this->organization);

    expect($platformAdmin->hasPlatformRole(UserRole::PlatformAdmin))->toBeTrue()
        ->and(PlatformOverviewWidget::canView())->toBeTrue();
});

it('allows organization owner to access all organization resources and manage team', function () {
    $owner = createTeamUser($this->organization, UserRole::OrganizationOwner);

    $this->actingAs($owner);
    Filament::setTenant($this->organization);

    expect(BranchResource::canViewAny())->toBeTrue()
        ->and(TeamMemberResource::canViewAny())->toBeTrue()
        ->and(InvoiceResource::canViewAny())->toBeTrue()
        ->and(PaymentResource::canViewAny())->toBeTrue()
        ->and(EstimateResource::canViewAny())->toBeTrue()
        ->and(ServiceRequestResource::canViewAny())->toBeTrue()
        ->and(MaintenanceContractResource::canViewAny())->toBeTrue()
        ->and(WorkOrderResource::canViewAny())->toBeTrue()
        ->and(CustomerResource::canViewAny())->toBeTrue();

    $this->get("/admin/{$this->organization->slug}/branches")->assertOk();
    $this->get("/admin/{$this->organization->slug}/team-members")->assertOk();
    $this->get("/admin/{$this->organization->slug}/invoices")->assertOk();
    $this->get("/admin/{$this->organization->slug}/payments")->assertOk();
});

it('allows admin to access operational resources and financials', function () {
    $admin = createTeamUser($this->organization, UserRole::Admin);

    $this->actingAs($admin);
    Filament::setTenant($this->organization);

    expect(BranchResource::canViewAny())->toBeTrue()
        ->and(TeamMemberResource::canViewAny())->toBeTrue()
        ->and(InvoiceResource::canViewAny())->toBeTrue()
        ->and(PaymentResource::canViewAny())->toBeTrue()
        ->and(EstimateResource::canViewAny())->toBeTrue()
        ->and(ServiceRequestResource::canViewAny())->toBeTrue()
        ->and(MaintenanceContractResource::canViewAny())->toBeTrue()
        ->and(WorkOrderResource::canViewAny())->toBeTrue()
        ->and(CustomerResource::canViewAny())->toBeTrue();

    $this->get("/admin/{$this->organization->slug}/branches")->assertOk();
    $this->get("/admin/{$this->organization->slug}/team-members")->assertOk();
    $this->get("/admin/{$this->organization->slug}/invoices")->assertOk();
    $this->get("/admin/{$this->organization->slug}/payments")->assertOk();
});

it('allows dispatcher to access dispatch resources but hides team management, branches, and financials', function () {
    $dispatcher = createTeamUser($this->organization, UserRole::Dispatcher);

    $this->actingAs($dispatcher);
    Filament::setTenant($this->organization);

    // Allowed resources
    expect(ServiceRequestResource::canViewAny())->toBeTrue()
        ->and(WorkOrderResource::canViewAny())->toBeTrue()
        ->and(WorkOrderResource::canCreate())->toBeTrue()
        ->and(EstimateResource::canViewAny())->toBeTrue()
        ->and(MaintenanceContractResource::canViewAny())->toBeTrue()
        ->and(CustomerResource::canViewAny())->toBeTrue()
        ->and(CustomerResource::canCreate())->toBeTrue()
        ->and(TechnicianAvailabilityResource::canViewAny())->toBeTrue();

    // Hidden from dispatchers
    expect(BranchResource::canViewAny())->toBeFalse()
        ->and(TeamMemberResource::canViewAny())->toBeFalse()
        ->and(InvoiceResource::canViewAny())->toBeFalse()
        ->and(PaymentResource::canViewAny())->toBeFalse();

    // Direct HTTP route checks: allowed operational routes
    $this->get("/admin/{$this->organization->slug}/work-orders")->assertOk();
    $this->get("/admin/{$this->organization->slug}/service-requests")->assertOk();
    $this->get("/admin/{$this->organization->slug}/estimates")->assertOk();

    // Forbidden direct admin routes
    $this->get("/admin/{$this->organization->slug}/branches")->assertForbidden();
    $this->get("/admin/{$this->organization->slug}/team-members")->assertForbidden();
    $this->get("/admin/{$this->organization->slug}/invoices")->assertForbidden();
    $this->get("/admin/{$this->organization->slug}/payments")->assertForbidden();
});

it('allows service manager to manage operational resources but denies financial and branch management', function () {
    $sm = createTeamUser($this->organization, UserRole::ServiceManager);

    $this->actingAs($sm);
    Filament::setTenant($this->organization);

    // Allowed
    expect(ServiceRequestResource::canViewAny())->toBeTrue()
        ->and(WorkOrderResource::canViewAny())->toBeTrue()
        ->and(EstimateResource::canViewAny())->toBeTrue()
        ->and(MaintenanceContractResource::canViewAny())->toBeTrue()
        ->and(EquipmentResource::canViewAny())->toBeTrue()
        ->and(CustomerResource::canViewAny())->toBeTrue();

    // Denied
    expect(BranchResource::canViewAny())->toBeFalse()
        ->and(TeamMemberResource::canViewAny())->toBeFalse()
        ->and(InvoiceResource::canViewAny())->toBeFalse()
        ->and(PaymentResource::canViewAny())->toBeFalse();

    $this->get("/admin/{$this->organization->slug}/work-orders")->assertOk();
    $this->get("/admin/{$this->organization->slug}/service-requests")->assertOk();
    $this->get("/admin/{$this->organization->slug}/branches")->assertForbidden();
    $this->get("/admin/{$this->organization->slug}/invoices")->assertForbidden();
});

it('allows accountant to access financial modules and denies operational resources', function () {
    $accountant = createTeamUser($this->organization, UserRole::Accountant);

    $this->actingAs($accountant);
    Filament::setTenant($this->organization);

    // Financial access
    expect(InvoiceResource::canViewAny())->toBeTrue()
        ->and(PaymentResource::canViewAny())->toBeTrue()
        ->and(EstimateResource::canViewAny())->toBeTrue()
        ->and(CustomerResource::canViewAny())->toBeTrue();

    // Operational restrictions
    expect(WorkOrderResource::canViewAny())->toBeFalse()
        ->and(ServiceRequestResource::canViewAny())->toBeFalse()
        ->and(EquipmentResource::canViewAny())->toBeFalse()
        ->and(MaintenanceContractResource::canViewAny())->toBeFalse()
        ->and(BranchResource::canViewAny())->toBeFalse()
        ->and(TeamMemberResource::canViewAny())->toBeFalse();

    $this->get("/admin/{$this->organization->slug}/invoices")->assertOk();
    $this->get("/admin/{$this->organization->slug}/payments")->assertOk();
    $this->get("/admin/{$this->organization->slug}/estimates")->assertOk();
    $this->get("/admin/{$this->organization->slug}/work-orders")->assertForbidden();
    $this->get("/admin/{$this->organization->slug}/service-requests")->assertForbidden();
});

it('redirects technician from admin panel to Livewire technician workspace and scopes assigned jobs', function () {
    $tech1 = createTeamUser($this->organization, UserRole::Technician);
    $tech2 = createTeamUser($this->organization, UserRole::Technician);

    $customer = Customer::create([
        'organization_id' => $this->organization->id,
        'name' => 'Metro Mall',
    ]);

    $woTech1 = WorkOrder::create([
        'organization_id' => $this->organization->id,
        'customer_id' => $customer->id,
        'assigned_to_user_id' => $tech1->id,
        'title' => 'HVAC Maintenance for Tech 1',
        'status' => WorkOrderStatus::New,
    ]);

    $woTech2 = WorkOrder::create([
        'organization_id' => $this->organization->id,
        'customer_id' => $customer->id,
        'assigned_to_user_id' => $tech2->id,
        'title' => 'Elevator Service for Tech 2',
        'status' => WorkOrderStatus::New,
    ]);

    $this->actingAs($tech1);

    // Technician is intercepted and redirected from admin panel to Livewire workspace
    $this->get("/admin/{$this->organization->slug}")->assertRedirect(route('technician.workspace'));

    // Livewire workspace is accessible and renders
    $this->get(route('technician.workspace'))->assertOk();

    // Livewire component verifies technician can only select their own assigned work orders
    Livewire::actingAs($tech1)
        ->test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $woTech1->id)
        ->assertSet('selectedWorkOrderId', $woTech1->id)
        ->assertOk();

    // Selecting another technician's work order is denied (403)
    Livewire::actingAs($tech1)
        ->test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $woTech2->id)
        ->assertStatus(403);
});

it('enforces role assignment restrictions and protects the final organization owner', function () {
    $owner = createTeamUser($this->organization, UserRole::OrganizationOwner);
    $admin = createTeamUser($this->organization, UserRole::Admin);
    $targetTech = createTeamUser($this->organization, UserRole::Technician);

    $targetMembership = $targetTech->memberships()->first();

    // Admin cannot assign OrganizationOwner
    $this->actingAs($admin);
    Filament::setTenant($this->organization);

    $test = Livewire::test(ManageTeamMembers::class)
        ->callTableAction('edit', $targetMembership, data: [
            'name' => $targetTech->name,
            'email' => $targetTech->email,
            'roles' => [UserRole::OrganizationOwner->value],
        ]);
    $test->assertHasTableActionErrors(['roles.0']);

    // Final owner cannot have owner role removed
    $ownerMembership = $owner->memberships()->first();
    $this->actingAs($owner);

    $test2 = Livewire::test(ManageTeamMembers::class)
        ->callTableAction('edit', $ownerMembership, data: [
            'name' => $owner->name,
            'email' => $owner->email,
            'roles' => [UserRole::Admin->value],
        ]);
    $test2->assertHasErrors(['roles']);
});

it('customizes dashboard widgets according to role permissions', function () {
    $admin = createTeamUser($this->organization, UserRole::Admin);
    $dispatcher = createTeamUser($this->organization, UserRole::Dispatcher);
    $serviceManager = createTeamUser($this->organization, UserRole::ServiceManager);
    $accountant = createTeamUser($this->organization, UserRole::Accountant);

    // 1. As Admin: fleet widgets are visible
    $this->actingAs($admin);
    Filament::setTenant($this->organization);

    expect(RecentServiceRequestsWidget::canView())->toBeTrue()
        ->and(TechnicianUtilizationWidget::canView())->toBeTrue()
        ->and(UpcomingMaintenanceWidget::canView())->toBeTrue()
        ->and(ServiceOverviewWidget::canView())->toBeTrue()
        ->and(UnassignedWorkOrdersWidget::canView())->toBeTrue();

    // 2. As Dispatcher: operational widgets are visible, financials hidden
    $this->actingAs($dispatcher);
    Filament::setTenant($this->organization);

    expect(RecentServiceRequestsWidget::canView())->toBeTrue()
        ->and(TechnicianUtilizationWidget::canView())->toBeTrue()
        ->and(UpcomingMaintenanceWidget::canView())->toBeTrue()
        ->and(UnassignedWorkOrdersWidget::canView())->toBeTrue()
        ->and(FinanceOverviewWidget::canView())->toBeFalse()
        ->and(RecentPaymentsWidget::canView())->toBeFalse();

    // 3. As Service Manager: operational widgets visible, financials hidden
    $this->actingAs($serviceManager);
    Filament::setTenant($this->organization);

    expect(RecentServiceRequestsWidget::canView())->toBeTrue()
        ->and(TechnicianUtilizationWidget::canView())->toBeTrue()
        ->and(UpcomingMaintenanceWidget::canView())->toBeTrue()
        ->and(UnassignedWorkOrdersWidget::canView())->toBeTrue()
        ->and(FinanceOverviewWidget::canView())->toBeFalse();

    // 4. As Accountant: financial widgets visible, operational hidden
    $this->actingAs($accountant);
    Filament::setTenant($this->organization);

    expect(FinanceOverviewWidget::canView())->toBeTrue()
        ->and(RecentPaymentsWidget::canView())->toBeTrue()
        ->and(RecentServiceRequestsWidget::canView())->toBeFalse()
        ->and(TechnicianUtilizationWidget::canView())->toBeFalse()
        ->and(UpcomingMaintenanceWidget::canView())->toBeFalse()
        ->and(UnassignedWorkOrdersWidget::canView())->toBeFalse();
});
