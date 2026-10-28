<?php

use App\Livewire\Technician\TechnicianWorkspace;
use App\MembershipStatus;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEntry;
use App\UserRole;
use App\WorkOrderStatus;
use Livewire\Livewire;

beforeEach(function () {
    $this->organization = Organization::create([
        'name' => 'Metro Service Pro',
        'slug' => 'metro-service-pro',
    ]);

    $this->technician = User::factory()->create([
        'must_change_password' => false,
    ]);

    $membership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->technician->id,
        'status' => MembershipStatus::Active,
    ]);
    $membership->assignRole(UserRole::Technician);

    $this->customer = Customer::create([
        'organization_id' => $this->organization->id,
        'name' => 'City Hospital',
    ]);

    $this->workOrder = WorkOrder::create([
        'organization_id' => $this->organization->id,
        'customer_id' => $this->customer->id,
        'assigned_to_user_id' => $this->technician->id,
        'title' => 'Chiller Preventive Maintenance',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => now()->startOfDay()->addHours(10),
        'scheduled_end_at' => now()->startOfDay()->addHours(12),
    ]);
});

it('denies non-technicians from mounting the technician workspace', function () {
    $admin = User::factory()->create(['must_change_password' => false]);
    $membership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $admin->id,
        'status' => MembershipStatus::Active,
    ]);
    $membership->assignRole(UserRole::Admin);

    Livewire::actingAs($admin)
        ->test(TechnicianWorkspace::class)
        ->assertStatus(403);
});

it('loads technician workspace with assigned jobs and metrics', function () {
    Livewire::actingAs($this->technician)
        ->test(TechnicianWorkspace::class)
        ->assertOk()
        ->assertSee('Chiller Preventive Maintenance')
        ->assertSee('City Hospital');
});

it('allows technician to transition job status from new to in_progress and on_hold', function () {
    Livewire::actingAs($this->technician)
        ->test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $this->workOrder->id)
        ->call('transitionStatus', WorkOrderStatus::InProgress->value)
        ->assertSet('feedbackMessage', 'Work order updated to status: in_progress.');

    expect($this->workOrder->fresh()->status)->toBe(WorkOrderStatus::InProgress);

    Livewire::actingAs($this->technician)
        ->test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $this->workOrder->id)
        ->call('transitionStatus', WorkOrderStatus::OnHold->value)
        ->assertSet('feedbackMessage', 'Work order updated to status: on_hold.');

    expect($this->workOrder->fresh()->status)->toBe(WorkOrderStatus::OnHold);
});

it('allows technician to record labour minutes and description', function () {
    Livewire::actingAs($this->technician)
        ->test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $this->workOrder->id)
        ->set('labourMinutes', '90')
        ->set('labourDescription', 'Disassembled filter housing and cleaned condenser coils')
        ->call('addLabourEntry')
        ->assertSet('feedbackMessage', 'Labour entry recorded successfully.');

    expect(WorkOrderEntry::where('work_order_id', $this->workOrder->id)->where('type', 'labour')->exists())->toBeTrue()
        ->and(WorkOrderEntry::where('work_order_id', $this->workOrder->id)->first()->minutes)->toBe(90);
});

it('allows technician to add job notes', function () {
    Livewire::actingAs($this->technician)
        ->test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $this->workOrder->id)
        ->set('noteDescription', 'Water pressure valve shows slight wear, recommended replacement on next cycle.')
        ->call('addNoteEntry')
        ->assertSet('feedbackMessage', 'Job note added.');

    expect(WorkOrderEntry::where('work_order_id', $this->workOrder->id)->where('type', 'note')->exists())->toBeTrue();
});

it('requires completion notes and completes work order', function () {
    $this->workOrder->transitionTo(WorkOrderStatus::InProgress);

    // Fails with empty completion notes
    Livewire::actingAs($this->technician)
        ->test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $this->workOrder->id)
        ->set('completionNotes', '')
        ->call('completeWorkOrder')
        ->assertHasErrors(['completionNotes']);

    // Succeeds with notes
    Livewire::actingAs($this->technician)
        ->test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $this->workOrder->id)
        ->set('completionNotes', 'Fully tested all safety valves, system restored to normal operation.')
        ->call('completeWorkOrder')
        ->assertSet('feedbackMessage', 'Service completion report submitted and work order completed!');

    $fresh = $this->workOrder->fresh();
    expect($fresh->status)->toBe(WorkOrderStatus::Completed)
        ->and($fresh->completion_notes)->toBe('Fully tested all safety valves, system restored to normal operation.')
        ->and($fresh->completed_at)->not->toBeNull();
});

it('locks completed work orders against further modifications by technician', function () {
    $this->workOrder->transitionTo(WorkOrderStatus::InProgress);
    $this->workOrder->transitionTo(WorkOrderStatus::Completed);

    Livewire::actingAs($this->technician)
        ->test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $this->workOrder->id)
        ->call('transitionStatus', WorkOrderStatus::InProgress->value)
        ->assertSet('errorMessage', 'Completed work orders cannot be modified.');

    Livewire::actingAs($this->technician)
        ->test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $this->workOrder->id)
        ->set('labourMinutes', '30')
        ->set('labourDescription', 'Extra work')
        ->call('addLabourEntry')
        ->assertSet('errorMessage', 'Cannot record labour on completed work orders.');
});

it('shows start work button and allows starting work for newly assigned work orders', function () {
    $newWorkOrder = WorkOrder::create([
        'organization_id' => $this->organization->id,
        'customer_id' => $this->customer->id,
        'assigned_to_user_id' => $this->technician->id,
        'title' => 'Emergency AC Leak',
        'status' => WorkOrderStatus::New,
    ]);

    Livewire::actingAs($this->technician)
        ->test(TechnicianWorkspace::class)
        ->call('selectWorkOrder', $newWorkOrder->id)
        ->assertSee('Start Work (In Progress)')
        ->call('transitionStatus', WorkOrderStatus::InProgress->value)
        ->assertSet('feedbackMessage', 'Work order updated to status: in_progress.');

    expect($newWorkOrder->fresh()->status)->toBe(WorkOrderStatus::InProgress);
});
