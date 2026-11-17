<?php

use App\Events\WorkOrderAssigned;
use App\Events\WorkOrderUpdated;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderAssignedNotification;
use App\Notifications\WorkOrderStatusChangedNotification;
use App\UserRole;
use App\WorkOrderStatus;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->orgA = Organization::create([
        'name' => 'Metro Air Systems',
        'slug' => 'metro-air-systems',
    ]);

    $this->branchA = Branch::create([
        'organization_id' => $this->orgA->id,
        'name' => 'North Depot',
    ]);

    $this->techUserA = User::factory()->create([
        'name' => 'Ravi Technician',
        'email' => 'ravi.tech@metroair.test',
    ]);

    $m = Membership::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->techUserA->id,
    ]);
    $m->assignRole(UserRole::Technician);
    $m->assignBranch($this->branchA);

    $this->customerA = Customer::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'name' => 'Apex Tower',
        'email' => 'admin@apextower.test',
    ]);

    // Org B
    $this->orgB = Organization::create([
        'name' => 'Global Climate Services',
        'slug' => 'global-climate-services',
    ]);

    $this->techUserB = User::factory()->create([
        'name' => 'Other Tech',
        'email' => 'other@globalclimate.test',
    ]);
});

test('work order assignment dispatches notification to assigned technician', function (): void {
    Notification::fake();

    $wo = WorkOrder::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customerA->id,
        'assigned_to_user_id' => $this->techUserA->id,
        'title' => 'Emergency Compressor Repair',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => today()->setTime(10, 0),
        'scheduled_end_at' => today()->setTime(12, 0),
    ]);

    WorkOrderAssigned::dispatch($wo);

    Notification::assertSentTo(
        $this->techUserA,
        WorkOrderAssignedNotification::class,
        function (WorkOrderAssignedNotification $notification) use ($wo): bool {
            return $notification->workOrder->id === $wo->id
                && $notification->workOrder->organization_id === $this->orgA->id;
        }
    );

    // Tech B in Org B must NEVER receive notifications for Org A
    Notification::assertNotSentTo($this->techUserB, WorkOrderAssignedNotification::class);
});

test('notification preferences allow technician to toggle email delivery', function (): void {
    $wo = WorkOrder::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customerA->id,
        'assigned_to_user_id' => $this->techUserA->id,
        'title' => 'Filter Check',
        'status' => WorkOrderStatus::Scheduled,
    ]);

    $notification = new WorkOrderAssignedNotification($wo);

    // By default, both mail and database channels are active
    expect($notification->via($this->techUserA))->toContain('mail', 'database');

    // Technician opts out of email
    NotificationPreference::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->techUserA->id,
        'event_type' => 'work_order_assigned',
        'channel' => 'mail',
        'is_enabled' => false,
    ]);

    // Now only database channel is returned
    expect($notification->via($this->techUserA))->toBe(['database']);
});

test('work order status update dispatches status change notification', function (): void {
    Notification::fake();

    $wo = WorkOrder::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customerA->id,
        'assigned_to_user_id' => $this->techUserA->id,
        'title' => 'Thermostat Inspection',
        'status' => WorkOrderStatus::InProgress,
    ]);

    WorkOrderUpdated::dispatch($wo, 'Work order status updated to on_hold.');

    Notification::assertSentTo(
        $this->techUserA,
        WorkOrderStatusChangedNotification::class,
        function (WorkOrderStatusChangedNotification $notification) use ($wo): bool {
            return $notification->workOrder->id === $wo->id
                && $notification->workOrder->organization_id === $this->orgA->id;
        }
    );
});
