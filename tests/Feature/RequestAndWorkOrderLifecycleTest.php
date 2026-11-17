<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklist;
use App\Models\WorkOrderStatusLog;
use App\ServicePriority;
use App\ServiceRequestStatus;
use App\UserRole;
use App\WorkOrderStatus;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->organization = Organization::create([
        'name' => 'Apex Lifecycle HVAC',
        'slug' => 'apex-lifecycle-hvac',
    ]);

    $this->branch = Branch::create([
        'organization_id' => $this->organization->id,
        'name' => 'Downtown Service Hub',
    ]);

    $this->adminUser = User::factory()->create([
        'name' => 'Apex Admin',
        'email' => 'admin@apexlifecycle.test',
    ]);

    $adminMembership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->adminUser->id,
    ]);
    $adminMembership->assignRole(UserRole::Admin);

    $this->techUser = User::factory()->create([
        'name' => 'Apex Technician',
        'email' => 'tech@apexlifecycle.test',
    ]);

    $techMembership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->techUser->id,
    ]);
    $techMembership->assignRole(UserRole::Technician);
    $techMembership->assignBranch($this->branch);

    $this->customer = Customer::create([
        'organization_id' => $this->organization->id,
        'branch_id' => $this->branch->id,
        'name' => 'Metropolis Towers',
        'email' => 'building@metropolistowers.test',
    ]);
});

test('service request enforces state transitions and cancellation reason', function (): void {
    $sr = ServiceRequest::create([
        'organization_id' => $this->organization->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'title' => 'Chiller pump vibration',
        'description' => 'Unusual harmonic noise from pump #2',
        'priority' => ServicePriority::High,
        'status' => ServiceRequestStatus::Open,
    ]);

    // Transition to Reviewing
    $sr->transitionTo(ServiceRequestStatus::Reviewing);
    expect($sr->fresh()->status)->toBe(ServiceRequestStatus::Reviewing);

    // Cancel requires a reason
    expect(fn () => $sr->transitionTo(ServiceRequestStatus::Cancelled))
        ->toThrow(ValidationException::class);

    $sr->transitionTo(ServiceRequestStatus::Cancelled, 'Duplicate client ticket');
    expect($sr->fresh()->status)->toBe(ServiceRequestStatus::Cancelled);
    expect($sr->fresh()->cancellation_reason)->toBe('Duplicate client ticket');

    // Cancelled cannot transition to any other status
    expect(fn () => $sr->transitionTo(ServiceRequestStatus::Open))
        ->toThrow(ValidationException::class);
});

test('service request can receive internal and client-visible comments', function (): void {
    $sr = ServiceRequest::create([
        'organization_id' => $this->organization->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'title' => 'Thermostat offline',
        'description' => 'Thermostat displaying error code E04',
        'priority' => ServicePriority::Normal,
        'status' => ServiceRequestStatus::Open,
    ]);

    $internalComment = $sr->addComment($this->adminUser, 'Dispatching tech after parts arrival', true);
    $publicComment = $sr->addComment($this->techUser, 'Arrival ETA 2:30 PM', false);

    expect($sr->comments()->count())->toBe(2);
    expect($internalComment->is_internal)->toBeTrue();
    expect($internalComment->organization_id)->toBe($this->organization->id);
    expect($publicComment->is_internal)->toBeFalse();
    expect($publicComment->user_id)->toBe($this->techUser->id);
});

test('work order requires reason for on-hold and cancelled states', function (): void {
    $wo = WorkOrder::create([
        'organization_id' => $this->organization->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'assigned_to_user_id' => $this->techUser->id,
        'title' => 'Compressor overhaul',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => now(),
        'scheduled_end_at' => now()->addHours(3),
    ]);

    $wo->transitionTo(WorkOrderStatus::InProgress, null, $this->techUser);

    // Put on hold without reason must throw
    expect(fn () => $wo->transitionTo(WorkOrderStatus::OnHold, null, $this->techUser))
        ->toThrow(ValidationException::class);

    // With reason succeeds and creates a status log
    $wo->transitionTo(WorkOrderStatus::OnHold, 'Waiting for replacement valve', $this->techUser);
    expect($wo->fresh()->status)->toBe(WorkOrderStatus::OnHold);
    expect($wo->fresh()->status_reason)->toBe('Waiting for replacement valve');

    $latestLog = WorkOrderStatusLog::where('work_order_id', $wo->id)->latest('id')->first();
    expect($latestLog)->not->toBeNull();
    expect($latestLog->from_status)->toBe(WorkOrderStatus::InProgress->value);
    expect($latestLog->to_status)->toBe(WorkOrderStatus::OnHold->value);
    expect($latestLog->reason)->toBe('Waiting for replacement valve');
    expect($latestLog->user_id)->toBe($this->techUser->id);
});

test('work order completion requires all checklists to be completed', function (): void {
    $wo = WorkOrder::create([
        'organization_id' => $this->organization->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'assigned_to_user_id' => $this->techUser->id,
        'title' => 'Electrical safety audit',
        'status' => WorkOrderStatus::InProgress,
        'scheduled_start_at' => now(),
        'scheduled_end_at' => now()->addHours(2),
    ]);

    $item1 = WorkOrderChecklist::create([
        'work_order_id' => $wo->id,
        'item' => 'Inspect main breaker connections',
        'is_completed' => false,
    ]);

    $item2 = WorkOrderChecklist::create([
        'work_order_id' => $wo->id,
        'item' => 'Verify earthing resistance',
        'is_completed' => false,
    ]);

    $wo->completion_notes = 'Audit conducted according to ISO-45001';

    // Cannot complete while checklists are pending
    expect(fn () => $wo->transitionTo(WorkOrderStatus::Completed, null, $this->techUser))
        ->toThrow(ValidationException::class);

    // Complete item 1
    $item1->markCompleted($this->techUser);
    expect($wo->fresh()->allChecklistsCompleted())->toBeFalse();

    // Complete item 2
    $item2->markCompleted($this->techUser);
    expect($wo->fresh()->allChecklistsCompleted())->toBeTrue();

    // Now transition to Completed succeeds
    $wo->transitionTo(WorkOrderStatus::Completed, null, $this->techUser);
    expect($wo->fresh()->status)->toBe(WorkOrderStatus::Completed);
    expect($wo->fresh()->completed_at)->not->toBeNull();
});

test('work order supports customer signature and supervisor sign-off', function (): void {
    $wo = WorkOrder::create([
        'organization_id' => $this->organization->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'assigned_to_user_id' => $this->techUser->id,
        'title' => 'Filter replacement',
        'status' => WorkOrderStatus::InProgress,
        'completion_notes' => 'Replaced intake HEPA filters.',
    ]);

    expect($wo->isCustomerSigned())->toBeFalse();
    $wo->signCustomer('Alice Facility Manager');
    expect($wo->fresh()->isCustomerSigned())->toBeTrue();
    expect($wo->fresh()->customer_signature_name)->toBe('Alice Facility Manager');
    expect($wo->fresh()->customer_signed_at)->not->toBeNull();

    $wo->transitionTo(WorkOrderStatus::Completed, null, $this->techUser);

    expect($wo->isSupervisorApproved())->toBeFalse();
    $wo->approveSupervisor($this->adminUser, 'Quality check verified on site');

    expect($wo->fresh()->isSupervisorApproved())->toBeTrue();
    expect($wo->fresh()->supervisor_approved_by)->toBe($this->adminUser->id);
    expect($wo->fresh()->supervisor_signature_notes)->toBe('Quality check verified on site');
});

test('service request conversion creates linked work order and updates request status', function (): void {
    $sr = ServiceRequest::create([
        'organization_id' => $this->organization->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'title' => 'Emergency water pipe burst',
        'description' => 'Basement server room flooding risk',
        'priority' => ServicePriority::Urgent,
        'status' => ServiceRequestStatus::Open,
    ]);

    $sr->transitionTo(ServiceRequestStatus::Reviewing);

    $wo = WorkOrder::create([
        'organization_id' => $sr->organization_id,
        'branch_id' => $sr->branch_id,
        'service_request_id' => $sr->id,
        'customer_id' => $sr->customer_id,
        'title' => $sr->title,
        'description' => $sr->description,
        'status' => WorkOrderStatus::New,
    ]);

    $sr->transitionTo(ServiceRequestStatus::Converted);

    expect($sr->fresh()->status)->toBe(ServiceRequestStatus::Converted);
    expect($wo->service_request_id)->toBe($sr->id);
    expect($wo->organization_id)->toBe($this->organization->id);
});

test('cross-tenant mutation is prohibited for service requests and work orders', function (): void {
    $otherOrg = Organization::create([
        'name' => 'Competitor Services',
        'slug' => 'competitor-services',
    ]);

    $otherBranch = Branch::create([
        'organization_id' => $otherOrg->id,
        'name' => 'Uptown Branch',
    ]);

    $otherCustomer = Customer::create([
        'organization_id' => $otherOrg->id,
        'branch_id' => $otherBranch->id,
        'name' => 'Other Customer',
        'email' => 'other@customer.test',
    ]);

    $otherSr = ServiceRequest::create([
        'organization_id' => $otherOrg->id,
        'branch_id' => $otherBranch->id,
        'customer_id' => $otherCustomer->id,
        'title' => 'Other Org Ticket',
        'description' => 'Should never be accessible',
        'priority' => ServicePriority::Low,
        'status' => ServiceRequestStatus::Open,
    ]);

    // Attempting to assign technician from Apex to Competitor's work order should fail validation
    expect(function () use ($otherOrg, $otherBranch, $otherCustomer) {
        WorkOrder::create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'customer_id' => $otherCustomer->id,
            'assigned_to_user_id' => $this->techUser->id,
            'title' => 'Cross-tenant assignment attempt',
            'status' => WorkOrderStatus::New,
        ]);
    })->toThrow(ValidationException::class);
});
