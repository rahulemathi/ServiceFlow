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
