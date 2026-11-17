<?php

use App\Filament\Resources\Branches\Pages\ManageBranches;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Resources\Support\FilamentOptions;
use App\Filament\Resources\WorkOrders\Pages\ManageWorkOrders;
use App\Livewire\Technician\TechnicianWorkspace;
use App\MembershipStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    // Primary test organization
    $this->org = Organization::create([
        'name' => 'Apex Field Solutions',
        'slug' => 'apex-field',
    ]);

    // Branch A & Branch B
    $this->branchA = Branch::create([
        'organization_id' => $this->org->id,
        'name' => 'Downtown Branch',
        'code' => 'DT-01',
    ]);

    $this->branchB = Branch::create([
        'organization_id' => $this->org->id,
        'name' => 'Uptown Branch',
        'code' => 'UP-02',
    ]);

    // Organization Admin
    $this->admin = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@apexfield.com',
    ]);
    $adminMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->admin->id,
        'status' => MembershipStatus::Active,
    ]);
    $adminMembership->assignRole(UserRole::Admin);

    // Dispatcher assigned ONLY to Branch A
    $this->dispatcherA = User::factory()->create([
        'name' => 'Dispatcher A',
        'email' => 'dispatchera@apexfield.com',
    ]);
    $dispatcherMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->dispatcherA->id,
        'status' => MembershipStatus::Active,
    ]);
    $dispatcherMembership->assignRole(UserRole::Dispatcher);
    $dispatcherMembership->assignBranch($this->branchA);

    // Technician assigned ONLY to Branch A
    $this->techA = User::factory()->create([
        'name' => 'Technician A',
        'email' => 'techa@apexfield.com',
    ]);
    $techAMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->techA->id,
        'status' => MembershipStatus::Active,
    ]);
    $techAMembership->assignRole(UserRole::Technician);
    $techAMembership->assignBranch($this->branchA);

    // Technician assigned ONLY to Branch B
    $this->techB = User::factory()->create([
        'name' => 'Technician B',
        'email' => 'techb@apexfield.com',
    ]);
    $techBMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->techB->id,
        'status' => MembershipStatus::Active,
    ]);
    $techBMembership->assignRole(UserRole::Technician);
    $techBMembership->assignBranch($this->branchB);

    // Customer
    $this->customer = Customer::create([
        'organization_id' => $this->org->id,
        'name' => 'Metro Hospital',
    ]);

    // Cross-tenant organization and branch
    $this->otherOrg = Organization::create([
        'name' => 'Other Org',
        'slug' => 'other-org',
    ]);
    $this->otherBranch = Branch::create([
        'organization_id' => $this->otherOrg->id,
        'name' => 'Foreign Branch',
        'code' => 'FB-99',
    ]);
});

it('scopes branch options to assigned branches for branch-restricted users', function () {
    // Admin sees all branches
    $this->actingAs($this->admin);
    Filament::setTenant($this->org);
    $adminBranches = FilamentOptions::branches();
    expect($adminBranches)->toHaveKeys([$this->branchA->id, $this->branchB->id]);

    // Dispatcher A sees only Branch A
    $this->actingAs($this->dispatcherA);
    Filament::setTenant($this->org);
    $dispatcherBranches = FilamentOptions::branches();
    expect($dispatcherBranches)->toHaveKey($this->branchA->id)
        ->and($dispatcherBranches)->not->toHaveKey($this->branchB->id);
});

it('restricts work orders visible to dispatchers by their assigned branch', function () {
    $woA = WorkOrder::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'work_order_number' => 'WO-A001',
        'title' => 'Downtown Repair',
    ]);

    $woB = WorkOrder::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branchB->id,
        'customer_id' => $this->customer->id,
        'work_order_number' => 'WO-B002',
        'title' => 'Uptown Repair',
    ]);

    $woUnassigned = WorkOrder::create([
        'organization_id' => $this->org->id,
        'branch_id' => null,
        'customer_id' => $this->customer->id,
        'work_order_number' => 'WO-U003',
        'title' => 'Unassigned Branch Repair',
    ]);

    $this->actingAs($this->dispatcherA);
    Filament::setTenant($this->org);

    Livewire::test(ManageWorkOrders::class)
        ->assertCanSeeTableRecords([$woA, $woUnassigned])
        ->assertCanNotSeeTableRecords([$woB]);
});

it('rejects work order creation with a branch outside the dispatchers permission', function () {
    $this->actingAs($this->dispatcherA);
    Filament::setTenant($this->org);

    Livewire::test(ManageWorkOrders::class)
        ->callAction('create', [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branchB->id,
            'title' => 'Unauthorized Branch Work Order',
        ])
        ->assertHasActionErrors(['branch_id']);
});

it('rejects work order creation with a cross-tenant branch ID', function () {
    $this->actingAs($this->admin);
    Filament::setTenant($this->org);

    Livewire::test(ManageWorkOrders::class)
        ->callAction('create', [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->otherBranch->id,
            'title' => 'Cross-tenant Branch Attack',
        ])
        ->assertHasActionErrors(['branch_id']);
});

it('prevents assigning a technician who does not belong to the selected branch', function () {
    $this->actingAs($this->admin);
    Filament::setTenant($this->org);

    // Tech B belongs to Branch B, so assigning to a Branch A work order must fail validation
    Livewire::test(ManageWorkOrders::class)
        ->callAction('create', [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branchA->id,
            'assigned_to_user_id' => $this->techB->id,
            'title' => 'Mismatched Branch Technician Assignment',
        ])
        ->assertHasActionErrors(['assigned_to_user_id']);
});

it('allows assigning a technician belonging to the selected branch', function () {
    $this->actingAs($this->admin);
    Filament::setTenant($this->org);

    Livewire::test(ManageWorkOrders::class)
        ->callAction('create', [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branchA->id,
            'assigned_to_user_id' => $this->techA->id,
            'title' => 'Valid Branch Technician Assignment',
            'entries' => [],
        ])
        ->assertHasNoActionErrors();

    expect(WorkOrder::where('title', 'Valid Branch Technician Assignment')->exists())->toBeTrue();
});

it('scopes equipment and service requests by permitted branches', function () {
    $eqA = Equipment::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'name' => 'HVAC Unit Downtown',
    ]);

    $eqB = Equipment::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branchB->id,
        'customer_id' => $this->customer->id,
        'name' => 'HVAC Unit Uptown',
    ]);

    $srA = ServiceRequest::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'request_number' => 'SR-A001',
        'title' => 'Downtown Leaking',
        'description' => 'Fix pipe',
    ]);

    $srB = ServiceRequest::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branchB->id,
        'customer_id' => $this->customer->id,
        'request_number' => 'SR-B002',
        'title' => 'Uptown Noise',
        'description' => 'Check motor',
    ]);

    $this->actingAs($this->dispatcherA);
    Filament::setTenant($this->org);

    $eqQuery = EquipmentResource::getEloquentQuery();
    $visibleEqIds = $eqQuery->pluck('id')->all();
    expect($visibleEqIds)->toContain($eqA->id)
        ->and($visibleEqIds)->not->toContain($eqB->id);

    $srQuery = ServiceRequestResource::getEloquentQuery();
    $visibleSrIds = $srQuery->pluck('id')->all();
    expect($visibleSrIds)->toContain($srA->id)
        ->and($visibleSrIds)->not->toContain($srB->id);
});

it('prevents technicians from accessing work orders assigned to a branch they do not belong to', function () {
    // Work order belonging to Branch B, assigned to Tech A (e.g. invalid override or legacy)
    $woBranchB = WorkOrder::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branchB->id,
        'customer_id' => $this->customer->id,
        'assigned_to_user_id' => $this->techA->id,
        'work_order_number' => 'WO-OUT-01',
        'title' => 'Out of Branch Work Order',
    ]);

    $this->actingAs($this->techA);
    Filament::setTenant($this->org);

    Livewire::test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $woBranchB->id)
        ->assertForbidden();
});

it('prevents deleting a branch with assigned team members or active operational records', function () {
    $this->actingAs($this->admin);
    Filament::setTenant($this->org);

    // 1. Branch A has Tech A and Dispatcher A assigned
    Livewire::test(ManageBranches::class)
        ->callTableAction('delete', $this->branchA);

    expect(Branch::find($this->branchA->id))->not->toBeNull();

    // 2. Unlinked empty branch can be deleted
    $emptyBranch = Branch::create([
        'organization_id' => $this->org->id,
        'name' => 'Empty Test Branch',
        'code' => 'EMPTY-99',
    ]);

    Livewire::test(ManageBranches::class)
        ->callTableAction('delete', $emptyBranch);

    expect(Branch::find($emptyBranch->id))->toBeNull();
});

it('enforces branch code uniqueness within the organization while allowing it across different organizations', function () {
    $this->actingAs($this->admin);
    Filament::setTenant($this->org);

    // Duplicate code in same organization must fail
    Livewire::test(ManageBranches::class)
        ->callAction('create', [
            'name' => 'Duplicate Downtown',
            'code' => 'DT-01',
        ])
        ->assertHasActionErrors(['code']);

    // Same code in a different organization is allowed
    $crossOrgBranch = Branch::create([
        'organization_id' => $this->otherOrg->id,
        'name' => 'Other Org Downtown',
        'code' => 'DT-01',
    ]);

    expect($crossOrgBranch->id)->not->toBeNull();
});
