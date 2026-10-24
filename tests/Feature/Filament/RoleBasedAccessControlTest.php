<?php

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Estimates\EstimateResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\MaintenanceContracts\MaintenanceContractResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Resources\TeamMembers\TeamMemberResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Widgets\RecentServiceRequestsWidget;
use App\Filament\Widgets\TechnicianUtilizationWidget;
use App\Filament\Widgets\UpcomingMaintenanceWidget;
use App\MembershipStatus;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use App\WorkOrderStatus;
use Filament\Facades\Filament;

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

it('allows admin to access all management and financial resources', function () {
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
        ->and(WorkOrderResource::canCreate())->toBeTrue()
        ->and(CustomerResource::canCreate())->toBeTrue();

    // Route checks
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
        ->and(CustomerResource::canCreate())->toBeTrue();

    // Hidden from dispatchers
    expect(BranchResource::canViewAny())->toBeFalse()
        ->and(TeamMemberResource::canViewAny())->toBeFalse()
        ->and(InvoiceResource::canViewAny())->toBeFalse()
        ->and(PaymentResource::canViewAny())->toBeFalse();

    // Forbidden direct route accesses
    $this->get("/admin/{$this->organization->slug}/branches")->assertForbidden();
    $this->get("/admin/{$this->organization->slug}/team-members")->assertForbidden();
    $this->get("/admin/{$this->organization->slug}/invoices")->assertForbidden();
    $this->get("/admin/{$this->organization->slug}/payments")->assertForbidden();

    // Allowed direct route accesses
    $this->get("/admin/{$this->organization->slug}/work-orders")->assertOk();
    $this->get("/admin/{$this->organization->slug}/service-requests")->assertOk();
    $this->get("/admin/{$this->organization->slug}/estimates")->assertOk();
});

it('scopes technician view only to their assigned work orders and hides unneeded resources', function () {
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
    Filament::setTenant($this->organization);

    // Hidden resources for technician
    expect(BranchResource::canViewAny())->toBeFalse()
        ->and(TeamMemberResource::canViewAny())->toBeFalse()
        ->and(InvoiceResource::canViewAny())->toBeFalse()
        ->and(PaymentResource::canViewAny())->toBeFalse()
        ->and(EstimateResource::canViewAny())->toBeFalse()
        ->and(ServiceRequestResource::canViewAny())->toBeFalse()
        ->and(MaintenanceContractResource::canViewAny())->toBeFalse();

    // Permissions
    expect(WorkOrderResource::canCreate())->toBeFalse()
        ->and(CustomerResource::canCreate())->toBeFalse()
        ->and(CustomerResource::canDelete($customer))->toBeFalse();

    // Query scoping: Tech 1 should only see their own work order
    $scopedWorkOrders = WorkOrderResource::getEloquentQuery()->pluck('id')->all();
    expect($scopedWorkOrders)->toContain($woTech1->id)
        ->and($scopedWorkOrders)->not->toContain($woTech2->id);

    // Direct HTTP route checks: blocked from admin/financial routes
    $this->get("/admin/{$this->organization->slug}/branches")->assertForbidden();
    $this->get("/admin/{$this->organization->slug}/team-members")->assertForbidden();
    $this->get("/admin/{$this->organization->slug}/invoices")->assertForbidden();
    $this->get("/admin/{$this->organization->slug}/payments")->assertForbidden();
    $this->get("/admin/{$this->organization->slug}/service-requests")->assertForbidden();

    // Allowed work orders route
    $this->get("/admin/{$this->organization->slug}/work-orders")->assertOk();
});

it('customizes dashboard widgets according to role permissions', function () {
    $admin = createTeamUser($this->organization, UserRole::Admin);
    $dispatcher = createTeamUser($this->organization, UserRole::Dispatcher);
    $technician = createTeamUser($this->organization, UserRole::Technician);

    // 1. As Admin: fleet widgets are visible
    $this->actingAs($admin);
    Filament::setTenant($this->organization);

    expect(RecentServiceRequestsWidget::canView())->toBeTrue()
        ->and(TechnicianUtilizationWidget::canView())->toBeTrue()
        ->and(UpcomingMaintenanceWidget::canView())->toBeTrue();

    // 2. As Dispatcher: operational widgets are visible
    $this->actingAs($dispatcher);
    Filament::setTenant($this->organization);

    expect(RecentServiceRequestsWidget::canView())->toBeTrue()
        ->and(TechnicianUtilizationWidget::canView())->toBeTrue()
        ->and(UpcomingMaintenanceWidget::canView())->toBeTrue();

    // 3. As Technician: fleet triage widgets are hidden
    $this->actingAs($technician);
    Filament::setTenant($this->organization);

    expect(RecentServiceRequestsWidget::canView())->toBeFalse()
        ->and(TechnicianUtilizationWidget::canView())->toBeFalse()
        ->and(UpcomingMaintenanceWidget::canView())->toBeFalse();
});
