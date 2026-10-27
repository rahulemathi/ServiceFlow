<?php

use App\CommercialStatus;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('records payments from the tenant payment resource action', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $user = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    $membership->assignRole(UserRole::OrganizationOwner);
    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Asha Rao',
    ]);
    $invoice = Invoice::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
    ]);
    $invoice->items()->create([
        'description' => 'Repair',
        'quantity' => 1,
        'unit_price' => 100,
    ]);
    $invoice->forceFill(['status' => CommercialStatus::Sent])->save();
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    Livewire::test(ManagePayments::class)
        ->callAction(TestAction::make('recordPayment'), data: [
            'invoice_id' => $invoice->id,
            'amount' => 40,
            'received_at' => today()->toDateString(),
            'method' => 'cash',
            'reference' => 'POS-001',
            'notes' => null,
        ]);

    $this->assertDatabaseHas('payments', [
        'organization_id' => $organization->id,
        'invoice_id' => $invoice->id,
        'amount' => 40,
        'reference' => 'POS-001',
    ]);
    expect($invoice->fresh()->status)->toBe(CommercialStatus::PartiallyPaid)
        ->and($invoice->fresh()->balance)->toBe(60.0);
});

it('marks an invoice as paid when full balance is recorded', function () {
    $organization = Organization::create([
        'name' => 'Southside Service',
        'slug' => 'southside-service',
    ]);
    $user = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    $membership->assignRole(UserRole::OrganizationOwner);
    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Rahul Sharma',
    ]);
    $invoice = Invoice::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
    ]);
    $invoice->items()->create([
        'description' => 'Service Fee',
        'quantity' => 1,
        'unit_price' => 100,
    ]);
    $invoice->forceFill(['status' => CommercialStatus::Sent])->save();
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    Livewire::test(ManagePayments::class)
        ->callAction(TestAction::make('recordPayment'), data: [
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'received_at' => today()->toDateString(),
            'method' => 'card',
            'reference' => 'FULL-001',
            'notes' => null,
        ]);

    expect($invoice->fresh()->status)->toBe(CommercialStatus::Paid)
        ->and($invoice->fresh()->balance)->toBe(0.0);
});

it('automatically sets and defaults amount to the invoice balance when recording payment', function () {
    $organization = Organization::create([
        'name' => 'Downtown Service',
        'slug' => 'downtown-service',
    ]);
    $user = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    $membership->assignRole(UserRole::OrganizationOwner);
    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Priya Patel',
    ]);
    $invoice = Invoice::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
    ]);
    $invoice->items()->create([
        'description' => 'Diagnostic',
        'quantity' => 1,
        'unit_price' => 250,
    ]);
    $invoice->forceFill(['status' => CommercialStatus::Sent])->save();
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    // Call recordPayment without explicitly passing amount - action should use invoice balance
    Livewire::test(ManagePayments::class)
        ->callAction(TestAction::make('recordPayment'), data: [
            'invoice_id' => $invoice->id,
            'received_at' => today()->toDateString(),
            'method' => 'card',
            'reference' => 'AUTO-001',
            'notes' => 'Auto amount test',
        ]);

    $this->assertDatabaseHas('payments', [
        'organization_id' => $organization->id,
        'invoice_id' => $invoice->id,
        'amount' => 250,
    ]);
    expect($invoice->fresh()->status)->toBe(CommercialStatus::Paid);
});
