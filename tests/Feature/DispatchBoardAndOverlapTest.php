<?php

use App\Filament\Pages\DispatchBoard;
use App\Livewire\Technician\TechnicianWorkspace;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use App\WorkOrderStatus;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->orgA = Organization::create([
        'name' => 'Alpha Dispatch HVAC',
        'slug' => 'alpha-dispatch-hvac',
    ]);

    $this->branchA = Branch::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Alpha Central',
    ]);

    $this->adminUser = User::factory()->create([
        'name' => 'Alpha Admin',
        'email' => 'admin@alphadispatch.test',
    ]);
    $mAdmin = Membership::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->adminUser->id,
    ]);
    $mAdmin->assignRole(UserRole::Admin);

    $this->dispatcherUser = User::factory()->create([
        'name' => 'Alpha Dispatcher',
        'email' => 'dispatcher@alphadispatch.test',
    ]);
    $mDisp = Membership::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->dispatcherUser->id,
    ]);
    $mDisp->assignRole(UserRole::Dispatcher);

    $this->sharedTech = User::factory()->create([
        'name' => 'Sam Multi-Tech',
        'email' => 'sam.tech@multitenant.test',
    ]);

    // Membership in Org A
    $mTechA = Membership::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->sharedTech->id,
    ]);
    $mTechA->assignRole(UserRole::Technician);
    $mTechA->assignBranch($this->branchA);

    $this->customerA = Customer::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'name' => 'Alpha Commercial Plaza',
        'email' => 'plaza@alpha.test',
    ]);

    // Second Organization (Org B)
    $this->orgB = Organization::create([
        'name' => 'Beta Cooling Solutions',
        'slug' => 'beta-cooling-solutions',
    ]);

    $this->branchB = Branch::create([
        'organization_id' => $this->orgB->id,
        'name' => 'Beta Harbor',
    ]);

    // Same technician has membership in Org B
    $mTechB = Membership::create([
        'organization_id' => $this->orgB->id,
        'user_id' => $this->sharedTech->id,
    ]);
    $mTechB->assignRole(UserRole::Technician);
    $mTechB->assignBranch($this->branchB);

    $this->customerB = Customer::create([
        'organization_id' => $this->orgB->id,
        'branch_id' => $this->branchB->id,
        'name' => 'Beta Marina',
        'email' => 'marina@beta.test',
    ]);
});

test('prevents overlapping bookings for the same technician without an override', function (): void {
    $start = today()->setTime(10, 0);
    $end = today()->setTime(12, 0);

    // Initial booking: 10:00 to 12:00
    WorkOrder::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customerA->id,
        'assigned_to_user_id' => $this->sharedTech->id,
        'title' => 'Routine Morning Maintenance',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => $start,
        'scheduled_end_at' => $end,
    ]);

    // Overlapping booking: 11:00 to 13:00 (overlaps by 1 hour)
    expect(function () use ($start, $end) {
        WorkOrder::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $this->customerA->id,
            'assigned_to_user_id' => $this->sharedTech->id,
            'title' => 'Overlapping Service Call',
            'status' => WorkOrderStatus::Scheduled,
            'scheduled_start_at' => $start->copy()->addHour(), // 11:00
            'scheduled_end_at' => $end->copy()->addHour(),     // 13:00
        ]);
    })->toThrow(ValidationException::class);
});

test('allows overlapping booking with audited supervisor override', function (): void {
    $start = today()->setTime(14, 0);
    $end = today()->setTime(16, 0);

    WorkOrder::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customerA->id,
        'assigned_to_user_id' => $this->sharedTech->id,
        'title' => 'First Call',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => $start,
        'scheduled_end_at' => $end,
    ]);

    // Override without reason fails
    expect(function () use ($start, $end) {
        WorkOrder::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $this->customerA->id,
            'assigned_to_user_id' => $this->sharedTech->id,
            'title' => 'Overlapping Emergency',
            'status' => WorkOrderStatus::Scheduled,
            'scheduled_start_at' => $start->copy()->addMinutes(30),
            'scheduled_end_at' => $end->copy()->addMinutes(30),
            'has_overlap_override' => true,
            'overlap_override_reason' => '', // blank
        ]);
    })->toThrow(ValidationException::class);

    // Override with valid reason succeeds and records audit data
    $woOverridden = WorkOrder::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customerA->id,
        'assigned_to_user_id' => $this->sharedTech->id,
        'title' => 'Overlapping Emergency with Override',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => $start->copy()->addMinutes(30),
        'scheduled_end_at' => $end->copy()->addMinutes(30),
        'has_overlap_override' => true,
        'overlap_override_reason' => 'Critical hospital generator emergency override by service manager',
        'overlap_override_by_id' => $this->dispatcherUser->id,
    ]);

    expect($woOverridden->fresh()->has_overlap_override)->toBeTrue();
    expect($woOverridden->fresh()->overlap_override_reason)->toBe('Critical hospital generator emergency override by service manager');
    expect($woOverridden->fresh()->overlap_override_by_id)->toBe($this->dispatcherUser->id);
});

test('completed and cancelled work orders do not trigger overlap conflicts', function (): void {
    $start = today()->setTime(9, 0);
    $end = today()->setTime(11, 0);

    WorkOrder::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customerA->id,
        'assigned_to_user_id' => $this->sharedTech->id,
        'title' => 'Cancelled job',
        'status' => WorkOrderStatus::Cancelled,
        'status_reason' => 'Client called off',
        'scheduled_start_at' => $start,
        'scheduled_end_at' => $end,
    ]);

    // A new job in the same slot should succeed without conflict
    $newJob = WorkOrder::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customerA->id,
        'assigned_to_user_id' => $this->sharedTech->id,
        'title' => 'Replacement slot job',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => $start,
        'scheduled_end_at' => $end,
    ]);

    expect($newJob->id)->toBeGreaterThan(0);
});

test('technician with multiple memberships only accesses assigned work in the active tenant', function (): void {
    // Job in Org A
    $jobA = WorkOrder::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customerA->id,
        'assigned_to_user_id' => $this->sharedTech->id,
        'title' => 'Org A Job',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => today()->setTime(10, 0),
        'scheduled_end_at' => today()->setTime(12, 0),
    ]);

    // Job in Org B
    $jobB = WorkOrder::create([
        'organization_id' => $this->orgB->id,
        'branch_id' => $this->branchB->id,
        'customer_id' => $this->customerB->id,
        'assigned_to_user_id' => $this->sharedTech->id,
        'title' => 'Org B Job',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => today()->setTime(14, 0),
        'scheduled_end_at' => today()->setTime(16, 0),
    ]);

    // Set tenant to Org A
    $this->actingAs($this->sharedTech);
    Filament::setTenant($this->orgA);

    // In Org A, tech can select jobA
    Livewire::test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $jobA->id)
        ->assertSet('selectedWorkOrderId', $jobA->id);

    // Direct Livewire call against Org B's job fails with 403 Forbidden
    Livewire::test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $jobB->id)
        ->assertForbidden();
});

test('dispatch board authorization rejects unauthorized technicians', function (): void {
    $this->actingAs($this->dispatcherUser);
    Filament::setTenant($this->orgA);

    expect(DispatchBoard::canAccess())->toBeTrue();

    $this->actingAs($this->sharedTech);
    expect(DispatchBoard::canAccess())->toBeFalse();
});
