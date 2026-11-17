<?php

use App\EquipmentStatus;
use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Filament\Resources\Equipment\Pages\ManageEquipment;
use App\MembershipStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerNote;
use App\Models\Equipment;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::create([
        'name' => 'Apex Healthcare Facilities',
        'slug' => 'apex-healthcare',
    ]);

    $this->branchA = Branch::create([
        'organization_id' => $this->org->id,
        'name' => 'Main Hospital',
        'code' => 'MAIN-HOSP',
    ]);

    $this->branchB = Branch::create([
        'organization_id' => $this->org->id,
        'name' => 'West Wing Clinic',
        'code' => 'WEST-CLINIC',
    ]);

    $this->admin = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@apexhealth.com',
    ]);
    $adminMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->admin->id,
        'status' => MembershipStatus::Active,
    ]);
    $adminMembership->assignRole(UserRole::Admin);

    // Dispatcher restricted to Branch A
    $this->dispatcherA = User::factory()->create([
        'name' => 'Branch A Dispatcher',
        'email' => 'dispatchera@apexhealth.com',
    ]);
    $dispMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $this->dispatcherA->id,
        'status' => MembershipStatus::Active,
    ]);
    $dispMembership->assignRole(UserRole::Dispatcher);
    $dispMembership->assignBranch($this->branchA);

    // Other organization for cross-tenant testing
    $this->otherOrg = Organization::create([
        'name' => 'Other Health Corp',
        'slug' => 'other-health',
    ]);
    $this->otherUser = User::factory()->create([
        'name' => 'Foreign User',
        'email' => 'user@otherhealth.com',
    ]);
    $otherMembership = Membership::create([
        'organization_id' => $this->otherOrg->id,
        'user_id' => $this->otherUser->id,
        'status' => MembershipStatus::Active,
    ]);
    $otherMembership->assignRole(UserRole::Admin);
});

it('supports multiple customer contacts and primary contact assignment', function () {
    $customer = Customer::create([
        'organization_id' => $this->org->id,
        'name' => 'Apollo Medical Center',
    ]);

    $contact1 = CustomerContact::create([
        'organization_id' => $this->org->id,
        'customer_id' => $customer->id,
        'name' => 'Dr. Sharma',
        'email' => 'sharma@apollomed.com',
        'phone' => '+91 9876543210',
        'role_or_title' => 'Chief of Surgery',
        'is_primary' => true,
    ]);

    $contact2 = CustomerContact::create([
        'organization_id' => $this->org->id,
        'customer_id' => $customer->id,
        'name' => 'Mr. Verma',
        'email' => 'verma@apollomed.com',
        'phone' => '+91 9876543211',
        'role_or_title' => 'Facilities Lead',
        'is_primary' => false,
    ]);

    expect($customer->contacts()->count())->toBe(2)
        ->and($customer->contacts()->where('is_primary', true)->first()->id)->toBe($contact1->id);
});

it('creates and scopes customer notes with author attribution', function () {
    $customer = Customer::create([
        'organization_id' => $this->org->id,
        'name' => 'Fortis Care',
    ]);

    $note = CustomerNote::create([
        'organization_id' => $this->org->id,
        'customer_id' => $customer->id,
        'user_id' => $this->admin->id,
        'note' => 'Prefers early morning technician visits between 6 AM and 8 AM.',
        'is_pinned' => true,
    ]);

    expect($customer->notes()->count())->toBe(1)
        ->and($note->author->id)->toBe($this->admin->id)
        ->and($note->is_pinned)->toBeTrue();
});

it('generates a unified, chronological service and financial timeline for a customer', function () {
    $customer = Customer::create([
        'organization_id' => $this->org->id,
        'name' => 'Max Super Specialty',
    ]);

    // 1. Service Request
    $sr = ServiceRequest::create([
        'organization_id' => $this->org->id,
        'customer_id' => $customer->id,
        'request_number' => 'SR-MAX-001',
        'title' => 'MRI Cooling Malfunction',
        'description' => 'Coolant pressure dropping',
        'priority' => 'urgent',
        'status' => 'open',
    ]);

    // 2. Work Order
    $wo = WorkOrder::create([
        'organization_id' => $this->org->id,
        'customer_id' => $customer->id,
        'service_request_id' => $sr->id,
        'work_order_number' => 'WO-MAX-001',
        'title' => 'Replace MRI Chiller Valve',
    ]);

    // 3. Estimate
    $estimate = Estimate::create([
        'organization_id' => $this->org->id,
        'customer_id' => $customer->id,
        'estimate_number' => 'EST-MAX-001',
        'tax_rate' => 18,
    ]);
    $estimate->items()->create([
        'organization_id' => $this->org->id,
        'description' => 'Compressor Valve Kit',
        'quantity' => 1,
        'unit_price' => 25000,
    ]);
    $estimate->recalculateTotals();

    // 4. Invoice
    $invoice = Invoice::create([
        'organization_id' => $this->org->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-MAX-001',
        'tax_rate' => 18,
    ]);
    $invoice->items()->create([
        'organization_id' => $this->org->id,
        'description' => 'Emergency Service Charge',
        'quantity' => 2,
        'unit_price' => 5000,
    ]);
    $invoice->recalculateTotals();

    // 5. Note
    CustomerNote::create([
        'organization_id' => $this->org->id,
        'customer_id' => $customer->id,
        'user_id' => $this->admin->id,
        'note' => 'Emergency response dispatched within 45 minutes.',
    ]);

    $timeline = $customer->timeline();

    expect($timeline->count())->toBe(5);

    $types = $timeline->pluck('type')->all();
    expect($types)->toContain('Service Request')
        ->and($types)->toContain('Work Order')
        ->and($types)->toContain('Estimate')
        ->and($types)->toContain('Invoice')
        ->and($types)->toContain('Customer Note');
});

it('tracks equipment lifecycle status and warranty correctly', function () {
    $customer = Customer::create([
        'organization_id' => $this->org->id,
        'name' => 'Apex Diagnostic Center',
    ]);

    // Active under warranty
    $eqActive = Equipment::create([
        'organization_id' => $this->org->id,
        'customer_id' => $customer->id,
        'name' => 'GE Healthcare CT Scanner',
        'serial_number' => 'CT-GE-9901',
        'status' => EquipmentStatus::Active,
        'warranty_expires_at' => now()->addMonths(6),
    ]);

    expect($eqActive->isUnderWarranty())->toBeTrue()
        ->and($eqActive->status)->toBe(EquipmentStatus::Active)
        ->and($eqActive->warrantyStatusLabel())->toContain('Under warranty until');

    // In repair with expired warranty
    $eqRepair = Equipment::create([
        'organization_id' => $this->org->id,
        'customer_id' => $customer->id,
        'name' => 'Siemens X-Ray Unit',
        'serial_number' => 'XR-SIE-1200',
        'status' => EquipmentStatus::InRepair,
        'warranty_expires_at' => now()->subMonths(2),
    ]);

    expect($eqRepair->isUnderWarranty())->toBeFalse()
        ->and($eqRepair->status)->toBe(EquipmentStatus::InRepair)
        ->and($eqRepair->warrantyStatusLabel())->toContain('Warranty expired on');
});

it('handles equipment QR lookup securely and prevents cross-tenant discovery', function () {
    $customer = Customer::create([
        'organization_id' => $this->org->id,
        'name' => 'City General Hospital',
    ]);

    $equipmentA = Equipment::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $customer->id,
        'name' => 'Philips Defibrillator',
        'serial_number' => 'DF-PHI-555',
        'status' => EquipmentStatus::Active,
    ]);

    // 1. Unauthenticated lookup redirects to login
    $guestResponse = $this->get(route('equipment.lookup', $equipmentA->serial_number));
    $guestResponse->assertRedirect('/admin/login');

    // 2. Authenticated user in the same organization and branch can view
    $this->actingAs($this->admin);
    Filament::setTenant($this->org);

    $authResponse = $this->get(route('equipment.lookup', $equipmentA->serial_number));
    $authResponse->assertOk();
    $authResponse->assertSee('Philips Defibrillator');
    $authResponse->assertSee('DF-PHI-555');

    // 3. User from another organization receives 404 (strict cross-tenant protection)
    $this->actingAs($this->otherUser);
    Filament::setTenant($this->otherOrg);

    $crossTenantResponse = $this->get(route('equipment.lookup', $equipmentA->serial_number));
    $crossTenantResponse->assertNotFound();

    // 4. User from same organization restricted to Branch B receives 403 for Branch A equipment
    $branchBUser = User::factory()->create([
        'name' => 'Branch B Tech',
        'email' => 'branchb@apexhealth.com',
    ]);
    $bMembership = Membership::create([
        'organization_id' => $this->org->id,
        'user_id' => $branchBUser->id,
        'status' => MembershipStatus::Active,
    ]);
    $bMembership->assignRole(UserRole::Technician);
    $bMembership->assignBranch($this->branchB);

    $this->actingAs($branchBUser);
    Filament::setTenant($this->org);

    $branchBlockedResponse = $this->get(route('equipment.lookup', $equipmentA->serial_number));
    $branchBlockedResponse->assertForbidden();
});

it('renders timeline action on customer resource table', function () {
    $this->actingAs($this->admin);
    Filament::setTenant($this->org);

    $customer = Customer::create([
        'organization_id' => $this->org->id,
        'name' => 'Global Heart Institute',
    ]);

    Livewire::test(ManageCustomers::class)
        ->assertTableActionExists('viewTimeline');
});

it('renders QR lookup action on equipment resource table', function () {
    $this->actingAs($this->admin);
    Filament::setTenant($this->org);

    $customer = Customer::create([
        'organization_id' => $this->org->id,
        'name' => 'Ortho Clinic',
    ]);

    $eq = Equipment::create([
        'organization_id' => $this->org->id,
        'customer_id' => $customer->id,
        'name' => 'Ultrasound Machine',
        'serial_number' => 'US-001',
    ]);

    Livewire::test(ManageEquipment::class)
        ->assertTableActionExists('qrLookup');
});
