<?php

use App\CommercialStatus;
use App\Filament\Resources\Estimates\Pages\ManageEstimates;
use App\Filament\Resources\Invoices\Pages\ManageInvoices;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('creates an invoice with copied line items after an estimate is approved', function () {
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
    $estimate = Estimate::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'tax_rate' => 18,
    ]);
    $estimate->items()->create([
        'description' => 'Compressor service',
        'quantity' => 2,
        'unit_price' => 100,
    ]);
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    $page = Livewire::test(ManageEstimates::class);
    $page->callAction(TestAction::make('send')->table($estimate));
    $page->callAction(TestAction::make('approve')->table($estimate));
    $page->callAction(TestAction::make('createInvoice')->table($estimate));

    $invoice = $estimate->invoice()->firstOrFail();
    Livewire::test(ManageInvoices::class)
        ->callAction(TestAction::make('send')->table($invoice));
    $invoice->refresh();

    expect($estimate->fresh()->status)->toBe(CommercialStatus::Approved)
        ->and($invoice->status)->toBe(CommercialStatus::Sent)
        ->and($invoice->issued_at->toDateString())->toBe(today()->toDateString())
        ->and($invoice->subtotal)->toBe('200.00')
        ->and($invoice->tax_amount)->toBe('36.00')
        ->and($invoice->total)->toBe('236.00')
        ->and($invoice->items()->count())->toBe(1);
});
