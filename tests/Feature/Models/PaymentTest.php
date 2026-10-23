<?php

use App\CommercialStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

it('records a partial payment and updates the invoice balance', function () {
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
    ]);
    $invoice->items()->create([
        'description' => 'Repair',
        'quantity' => 1,
        'unit_price' => 100,
    ]);
    $invoice->forceFill(['status' => CommercialStatus::Sent])->save();

    $payment = Payment::recordAgainstInvoice($invoice, [
        'amount' => 40,
        'received_at' => today()->toDateString(),
        'method' => 'cash',
    ]);
    $invoice->refresh();

    $this->assertModelExists($payment);
    expect($invoice->status)->toBe(CommercialStatus::PartiallyPaid)
        ->and($invoice->balance)->toBe(60.0);
});

it('rejects payments that exceed the invoice balance without writing a payment', function () {
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
    ]);
    $invoice->items()->create([
        'description' => 'Repair',
        'quantity' => 1,
        'unit_price' => 100,
    ]);
    $invoice->forceFill(['status' => CommercialStatus::Sent])->save();

    expect(fn () => Payment::recordAgainstInvoice($invoice, [
        'amount' => 100.01,
        'received_at' => today()->toDateString(),
        'method' => 'cash',
    ]))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('payments', 0);
    expect($invoice->fresh()->status)->toBe(CommercialStatus::Sent);
});

it('prevents deleting an invoice with payment history', function () {
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
    ]);
    $invoice->items()->create([
        'description' => 'Repair',
        'quantity' => 1,
        'unit_price' => 100,
    ]);
    $invoice->forceFill(['status' => CommercialStatus::Sent])->save();
    $payment = Payment::recordAgainstInvoice($invoice, [
        'amount' => 25,
        'received_at' => today()->toDateString(),
        'method' => 'cash',
    ]);

    expect(fn () => $invoice->delete())->toThrow(QueryException::class);

    $this->assertModelExists($payment);
    $this->assertModelExists($invoice);
});

it('marks an unpaid invoice overdue after its due date', function () {
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
    ]);
    $invoice->items()->create([
        'description' => 'Repair',
        'quantity' => 1,
        'unit_price' => 100,
    ]);

    $invoice->update([
        'status' => CommercialStatus::Sent,
        'issued_at' => today()->subDays(10),
        'due_at' => today()->subDay(),
    ]);

    expect($invoice->fresh()->status)->toBe(CommercialStatus::Overdue);
});
