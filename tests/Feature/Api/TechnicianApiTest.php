<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklist;
use App\UserRole;
use App\WorkOrderStatus;

beforeEach(function (): void {
    $this->orgA = Organization::create([
        'name' => 'Mobile Field Services',
        'slug' => 'mobile-field-services',
    ]);

    $this->branchA = Branch::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Downtown Service Hub',
    ]);

    $this->tech = User::factory()->create([
        'name' => 'Leo Mobile Tech',
        'email' => 'leo.tech@mobilefield.test',
        'password' => bcrypt('StrongSecret123!'),
    ]);

    $m = Membership::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->tech->id,
    ]);
    $m->assignRole(UserRole::Technician);
    $m->assignBranch($this->branchA);

    $this->customer = Customer::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'name' => 'Prestige Heights',
        'email' => 'office@prestige.test',
    ]);

    // Foreign Organization
    $this->orgB = Organization::create([
        'name' => 'Competitor Facilities',
        'slug' => 'competitor-facilities',
    ]);

    $this->foreignWorkOrder = WorkOrder::create([
        'organization_id' => $this->orgB->id,
        'customer_id' => Customer::create(['organization_id' => $this->orgB->id, 'name' => 'Foreign Client'])->id,
        'title' => 'Secret Foreign Job',
        'status' => WorkOrderStatus::New,
    ]);
});

test('api login authenticates user and returns bearer token', function (): void {
    $response = $this->postJson(route('api.v1.auth.login'), [
        'email' => 'leo.tech@mobilefield.test',
        'password' => 'StrongSecret123!',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'user' => ['id', 'name', 'email'],
                'organization' => ['id', 'name', 'slug'],
            ],
        ]);

    // Invalid password returns 422
    $failResponse = $this->postJson(route('api.v1.auth.login'), [
        'email' => 'leo.tech@mobilefield.test',
        'password' => 'WrongPassword!',
    ]);

    $failResponse->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('api logout revokes token and invalidates subsequent requests', function (): void {
    $token = $this->tech->createToken('Mobile App')->plainTextToken;

    $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.v1.auth.logout'));

    $logoutResponse->assertOk()
        ->assertJsonPath('success', true);

    $this->app['auth']->forgetGuards();

    // Subsequent call with revoked token returns 401
    $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.v1.auth.me'));

    $meResponse->assertStatus(401);
});

test('technician api retrieves assigned work orders in tenant with pagination', function (): void {
    $token = $this->tech->createToken('Mobile App')->plainTextToken;

    $wo = WorkOrder::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'assigned_to_user_id' => $this->tech->id,
        'title' => 'HVAC Filter Replacement',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => today()->setTime(10, 0),
        'scheduled_end_at' => today()->setTime(12, 0),
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'X-Organization-Id' => (string) $this->orgA->id,
    ])->getJson(route('api.v1.work-orders.index'));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $wo->id)
        ->assertJsonPath('data.0.title', 'HVAC Filter Replacement')
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'work_order_number', 'title', 'status', 'customer'],
            ],
            'meta' => ['current_page', 'last_page', 'total'],
        ]);
});

test('technician api enforces cross-tenant boundary and header validation', function (): void {
    $token = $this->tech->createToken('Mobile App')->plainTextToken;

    // Attempting to access Org B with Org A technician token returns 403
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'X-Organization-Id' => (string) $this->orgB->id,
    ])->getJson(route('api.v1.work-orders.index'));

    $response->assertStatus(403)
        ->assertJsonPath('success', false);

    // Attempting to view foreign work order returns 404
    $detailResponse = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'X-Organization-Id' => (string) $this->orgA->id,
    ])->getJson(route('api.v1.work-orders.show', $this->foreignWorkOrder->id));

    $detailResponse->assertStatus(404);
});

test('technician api executes checklist toggle, labour entry, signature, and status transition', function (): void {
    $token = $this->tech->createToken('Mobile App')->plainTextToken;

    $wo = WorkOrder::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'assigned_to_user_id' => $this->tech->id,
        'title' => 'Sensor Calibration Job',
        'status' => WorkOrderStatus::Scheduled,
        'scheduled_start_at' => today()->setTime(9, 0),
        'scheduled_end_at' => today()->setTime(11, 0),
    ]);

    $chk = WorkOrderChecklist::create([
        'organization_id' => $this->orgA->id,
        'work_order_id' => $wo->id,
        'item' => 'Verify pressure sensor readings',
        'is_completed' => false,
    ]);

    $headers = [
        'Authorization' => "Bearer {$token}",
        'X-Organization-Id' => (string) $this->orgA->id,
    ];

    // 1. Transition to InProgress
    $startResp = $this->withHeaders($headers)
        ->postJson(route('api.v1.work-orders.status', $wo->id), [
            'status' => 'in_progress',
        ]);
    $startResp->assertOk()->assertJsonPath('data.status', 'in_progress');

    // 2. Toggle checklist item to completed
    $toggleResp = $this->withHeaders($headers)
        ->postJson(route('api.v1.work-orders.checklists.toggle', [$wo->id, $chk->id]));
    $toggleResp->assertOk()->assertJsonPath('data.is_completed', true);

    // 3. Record labour entry
    $entryResp = $this->withHeaders($headers)
        ->postJson(route('api.v1.work-orders.entries', $wo->id), [
            'type' => 'labor',
            'description' => 'Recalibrated digital sensor node',
            'minutes' => 60,
        ]);
    $entryResp->assertStatus(201)->assertJsonPath('data.type', 'labor');

    // 4. Record customer signature
    $signResp = $this->withHeaders($headers)
        ->postJson(route('api.v1.work-orders.sign', $wo->id), [
            'customer_signature_name' => 'Kavita Manager',
        ]);
    $signResp->assertOk()->assertJsonPath('data.customer_signature_name', 'Kavita Manager');

    // 5. Complete work order with completion notes
    $completeResp = $this->withHeaders($headers)
        ->postJson(route('api.v1.work-orders.status', $wo->id), [
            'status' => 'completed',
            'completion_notes' => 'Pressure sensor calibrated within ±0.01 bar tolerance.',
        ]);
    $completeResp->assertOk()->assertJsonPath('data.status', 'completed');

    expect($wo->fresh()->status)->toBe(WorkOrderStatus::Completed);
    expect($wo->fresh()->isCustomerSigned())->toBeTrue();
});
