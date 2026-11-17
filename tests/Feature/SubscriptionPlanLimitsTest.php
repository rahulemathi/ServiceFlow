<?php

use App\MembershipStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

test('enforces active user limit on organization', function () {
    $org = Organization::create([
        'name' => 'Starter Org',
        'slug' => 'starter-org',
        'plan' => 'starter',
        'subscription_status' => 'active',
        'max_users' => 2,
    ]);

    $user1 = User::factory()->create();
    $membership1 = Membership::create([
        'organization_id' => $org->id,
        'user_id' => $user1->id,
        'status' => MembershipStatus::Active->value,
    ]);
    expect($membership1->id)->toBeGreaterThan(0);

    $user2 = User::factory()->create();
    $membership2 = Membership::create([
        'organization_id' => $org->id,
        'user_id' => $user2->id,
        'status' => MembershipStatus::Active->value,
    ]);
    expect($membership2->id)->toBeGreaterThan(0);

    // 3rd user should exceed limit
    $user3 = User::factory()->create();
    expect(fn () => Membership::create([
        'organization_id' => $org->id,
        'user_id' => $user3->id,
        'status' => MembershipStatus::Active->value,
    ]))->toThrow(ValidationException::class);
});

test('enforces branch limit on organization', function () {
    $org = Organization::create([
        'name' => 'Single Branch Org',
        'slug' => 'single-branch-org',
        'plan' => 'starter',
        'subscription_status' => 'active',
        'max_branches' => 1,
    ]);

    $branch1 = Branch::create([
        'organization_id' => $org->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
    ]);
    expect($branch1->id)->toBeGreaterThan(0);

    // 2nd branch should exceed limit
    expect(fn () => Branch::create([
        'organization_id' => $org->id,
        'name' => 'Secondary Branch',
        'code' => 'SEC',
    ]))->toThrow(ValidationException::class);
});

test('enforces monthly work order limit and suspension behavior', function () {
    $org = Organization::create([
        'name' => 'Quota Org',
        'slug' => 'quota-org',
        'plan' => 'starter',
        'subscription_status' => 'active',
        'max_monthly_work_orders' => 2,
    ]);

    $customer = Customer::create([
        'organization_id' => $org->id,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $wo1 = WorkOrder::create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'title' => 'First Order',
    ]);
    expect($wo1->id)->toBeGreaterThan(0);

    $wo2 = WorkOrder::create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'title' => 'Second Order',
    ]);
    expect($wo2->id)->toBeGreaterThan(0);

    // 3rd order exceeds monthly quota
    expect(fn () => WorkOrder::create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'title' => 'Third Order',
    ]))->toThrow(ValidationException::class);

    // If org is suspended, cannot create work order even if quota remaining
    $org->update([
        'max_monthly_work_orders' => 10,
        'subscription_status' => 'suspended',
    ]);

    expect(fn () => WorkOrder::create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'title' => 'Suspended Order',
    ]))->toThrow(ValidationException::class);
});

test('grace period permits work order creation while past due until grace expires', function () {
    $org = Organization::create([
        'name' => 'Grace Org',
        'slug' => 'grace-org',
        'plan' => 'pro',
        'subscription_status' => 'past_due',
        'grace_period_ends_at' => now()->addDays(5),
    ]);

    $customer = Customer::create([
        'organization_id' => $org->id,
        'name' => 'Mark Grace',
        'email' => 'mark@example.com',
    ]);

    $wo = WorkOrder::create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'title' => 'Order in Grace Period',
    ]);
    expect($wo->id)->toBeGreaterThan(0);

    // Expire grace period
    $org->update(['grace_period_ends_at' => now()->subMinute()]);

    expect(fn () => WorkOrder::create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'title' => 'Order Past Grace',
    ]))->toThrow(ValidationException::class);
});

test('billing webhook processes events with signature verification and idempotency', function () {
    $org = Organization::create([
        'name' => 'Webhook Org',
        'slug' => 'webhook-org',
        'plan' => 'starter',
        'subscription_status' => 'past_due',
    ]);

    $eventId = (string) Str::ulid();
    $payload = [
        'event_id' => $eventId,
        'event' => 'subscription.activated',
        'organization_id' => $org->id,
        'plan' => 'enterprise',
        'max_users' => 50,
        'max_branches' => 10,
        'max_monthly_work_orders' => 500,
    ];

    $content = json_encode($payload);
    $signature = hash_hmac('sha256', $content, config('app.key'));

    // 1. Invalid signature rejects with 401
    $response = $this->withHeaders([
        'X-Signature' => 'invalid-signature',
        'Content-Type' => 'application/json',
    ])->postJson('/api/webhooks/billing', $payload);

    $response->assertStatus(401);

    // 2. Valid signature activates subscription
    $response = $this->withHeaders([
        'X-Signature' => $signature,
        'Content-Type' => 'application/json',
    ])->call('POST', '/api/webhooks/billing', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => $signature,
    ], $content);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'success']);

    $org->refresh();
    expect($org->subscription_status)->toBe('active')
        ->and($org->plan)->toBe('enterprise')
        ->and($org->max_users)->toBe(50);

    // 3. Repeated webhook with same event_id is idempotent
    $repeatResponse = $this->call('POST', '/api/webhooks/billing', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => $signature,
    ], $content);

    $repeatResponse->assertStatus(200);
    $repeatResponse->assertJson(['status' => 'already_processed']);

    // 4. Payment failed event sets past_due and 7-day grace period
    $failedEventId = (string) Str::ulid();
    $failedPayload = [
        'event_id' => $failedEventId,
        'event' => 'subscription.payment_failed',
        'organization_id' => $org->id,
    ];
    $failedContent = json_encode($failedPayload);
    $failedSignature = hash_hmac('sha256', $failedContent, config('app.key'));

    $this->call('POST', '/api/webhooks/billing', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => $failedSignature,
    ], $failedContent)->assertStatus(200);

    $org->refresh();
    expect($org->subscription_status)->toBe('past_due')
        ->and($org->grace_period_ends_at)->not->toBeNull()
        ->and($org->isInGracePeriod())->toBeTrue();
});

test('platform admin cross-tenant audit access logging', function () {
    $platformAdmin = User::factory()->create();
    $platformAdmin->assignPlatformRole(UserRole::PlatformAdmin);

    $org = Organization::create([
        'name' => 'Audited Org',
        'slug' => 'audited-org',
        'subscription_status' => 'active',
    ]);

    $log = $org->recordAuditAccess($platformAdmin, 'tenant_impersonation', ['reason' => 'investigate_billing_ticket']);

    expect($log)->toBeInstanceOf(PlatformAuditLog::class)
        ->and($log->user_id)->toBe($platformAdmin->id)
        ->and($log->organization_id)->toBe($org->id)
        ->and($log->action)->toBe('tenant_impersonation')
        ->and($log->details['reason'])->toBe('investigate_billing_ticket');
});
