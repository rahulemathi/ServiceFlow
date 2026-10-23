<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Database\QueryException;

it('calculates invoice totals from saved items and tax', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $customer = Customer::create([
        'organization_id' => $organization->id,
        'name' => 'Asha Rao',
    ]);
    $invoice = Invoice::create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'tax_rate' => 18,
    ]);

    $invoice->items()->create([
        'description' => 'Compressor service',
        'quantity' => 2,
        'unit_price' => 100,
    ]);

    expect($invoice->fresh()->subtotal)->toBe('200.00')
        ->and($invoice->fresh()->tax_amount)->toBe('36.00')
        ->and($invoice->fresh()->total)->toBe('236.00')
        ->and($invoice->fresh()->balance)->toBe(236.0);

    $invoice->update(['tax_rate' => 10]);

    expect($invoice->fresh()->tax_amount)->toBe('20.00')
        ->and($invoice->fresh()->total)->toBe('220.00');
});

it('rejects a customer branch from another organization', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $otherOrganization = Organization::create([
        'name' => 'Westside Service',
        'slug' => 'westside-service',
    ]);
    $branch = Branch::create([
        'organization_id' => $otherOrganization->id,
        'name' => 'West branch',
    ]);

    expect(fn () => Customer::create([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'name' => 'Asha Rao',
    ]))->toThrow(QueryException::class);
});
