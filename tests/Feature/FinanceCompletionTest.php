<?php

use App\CommercialStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->org = Organization::create([
        'name' => 'Sterling HVAC & MEP',
        'slug' => 'sterling-hvac-mep',
    ]);

    $this->branch = Branch::create([
        'organization_id' => $this->org->id,
        'name' => 'Bangalore Central',
        'state' => 'Karnataka',
    ]);

    $this->customer = Customer::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branch->id,
        'name' => 'Infosys Technology Park',
        'email' => 'accounts@infosystech.test',
    ]);

    $this->address = CustomerAddress::create([
        'organization_id' => $this->org->id,
        'customer_id' => $this->customer->id,
        'address_line_1' => 'Electronic City Phase 1',
        'city' => 'Bangalore',
        'region' => 'Karnataka',
        'postal_code' => '560100',
        'is_primary' => true,
    ]);

    $this->accountant = User::factory()->create([
        'name' => 'Finance Controller',
        'email' => 'controller@sterling.test',
    ]);
});

test('invoice totals and GST tax breakdown calculate accurately', function (): void {
    $invoice = Invoice::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'status' => CommercialStatus::Draft,
        'tax_rate' => 18, // 18% GST
    ]);

    // Add items: 2 compressor motors @ 5000 = 10,000 subtotal
    $invoice->items()->create([
        'organization_id' => $this->org->id,
        'description' => 'Compressor Motor 5HP',
        'quantity' => 2,
        'unit_price' => 5000,
    ]);

    $invoice->refresh();
    $invoice->recalculateTotals();

    expect((float) $invoice->subtotal)->toBe(10000.0);
    expect((float) $invoice->tax_amount)->toBe(1800.0);
    expect((float) $invoice->total)->toBe(11800.0);
    expect((float) $invoice->balance)->toBe(11800.0);

    // Intra-state GST breakdown (Karnataka to Karnataka): 9% CGST + 9% SGST
    $breakdown = $invoice->getTaxBreakdown();
    expect($breakdown['type'])->toBe('intra_state');
    expect($breakdown['rate'])->toBe(18.0);
    expect($breakdown['cgst'])->toBe(900.0);
    expect($breakdown['sgst'])->toBe(900.0);
    expect($breakdown['igst'])->toBe(0.0);
});

test('partial payments and balance recalculation update invoice lifecycle', function (): void {
    $invoice = Invoice::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'status' => CommercialStatus::Draft,
        'tax_rate' => 0,
    ]);

    $invoice->items()->create([
        'organization_id' => $this->org->id,
        'description' => 'Duct Cleaning Service',
        'quantity' => 1,
        'unit_price' => 10000,
    ]);

    $invoice->recalculateTotals();
    $invoice->update(['status' => CommercialStatus::Sent]);

    expect($invoice->balance)->toBe(10000.0);

    // Partial Payment 1: 4,000
    $payment1 = Payment::recordAgainstInvoice($invoice, [
        'amount' => 4000,
        'received_at' => today()->toDateString(),
        'method' => 'bank_transfer',
        'reference' => 'UTR123456',
    ]);

    expect((float) $payment1->amount)->toBe(4000.0);
    expect($invoice->fresh()->status)->toBe(CommercialStatus::PartiallyPaid);
    expect((float) $invoice->fresh()->balance)->toBe(6000.0);

    // Partial Payment 2: Remaining 6,000
    Payment::recordAgainstInvoice($invoice, [
        'amount' => 6000,
        'received_at' => today()->toDateString(),
        'method' => 'upi',
        'reference' => 'UPI-789012',
    ]);

    expect($invoice->fresh()->status)->toBe(CommercialStatus::Paid);
    expect((float) $invoice->fresh()->balance)->toBe(0.0);
});

test('refund records credit adjustment and updates invoice status correctly', function (): void {
    $invoice = Invoice::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'status' => CommercialStatus::Sent,
        'tax_rate' => 0,
    ]);

    $invoice->items()->create([
        'organization_id' => $this->org->id,
        'description' => 'Annual Maintenance Package',
        'quantity' => 1,
        'unit_price' => 20000,
    ]);

    $invoice->recalculateTotals();

    // Pay full 20,000
    Payment::recordAgainstInvoice($invoice, [
        'amount' => 20000,
        'received_at' => today()->toDateString(),
        'method' => 'bank_transfer',
    ]);

    expect($invoice->fresh()->status)->toBe(CommercialStatus::Paid);
    expect($invoice->fresh()->balance)->toBe(0.0);

    // Attempting to refund more than 20,000 must fail
    expect(fn () => Payment::recordRefund($invoice, [
        'amount' => 25000,
        'received_at' => today()->toDateString(),
    ]))->toThrow(ValidationException::class);

    // Partial Refund of 5,000
    $refund = Payment::recordRefund($invoice, [
        'amount' => 5000,
        'received_at' => today()->toDateString(),
        'method' => 'refund',
        'reference' => 'REF-5000-X',
    ]);

    expect((float) $refund->amount)->toBe(-5000.0);
    expect($invoice->fresh()->status)->toBe(CommercialStatus::PartiallyPaid);
    expect((float) $invoice->fresh()->balance)->toBe(5000.0);
    expect((float) $invoice->fresh()->total_paid)->toBe(15000.0);

    // Full remaining refund of 15,000
    Payment::recordRefund($invoice, [
        'amount' => 15000,
        'received_at' => today()->toDateString(),
    ]);

    // Back to Sent status with full balance outstanding
    expect($invoice->fresh()->status)->toBe(CommercialStatus::Sent);
    expect((float) $invoice->fresh()->balance)->toBe(20000.0);
    expect((float) $invoice->fresh()->total_paid)->toBe(0.0);
});

test('converting approved estimate to invoice is transactional and preserves items and taxes', function (): void {
    $estimate = Estimate::create([
        'organization_id' => $this->org->id,
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'status' => CommercialStatus::Draft,
        'tax_rate' => 12,
        'notes' => 'Special seasonal discount applied',
    ]);

    $estimate->items()->create([
        'organization_id' => $this->org->id,
        'description' => 'Thermostat replacement',
        'quantity' => 3,
        'unit_price' => 1500,
    ]);

    $estimate->recalculateTotals();
    $estimate->update(['status' => CommercialStatus::Approved]);

    expect((float) $estimate->subtotal)->toBe(4500.0);
    expect((float) $estimate->total)->toBe(5040.0);

    // Convert to invoice
    $invoice = Invoice::create([
        'organization_id' => $estimate->organization_id,
        'branch_id' => $estimate->branch_id,
        'customer_id' => $estimate->customer_id,
        'estimate_id' => $estimate->id,
        'tax_rate' => $estimate->tax_rate,
        'notes' => $estimate->notes,
        'status' => CommercialStatus::Draft,
    ]);

    foreach ($estimate->items as $item) {
        $invoice->items()->create([
            'organization_id' => $invoice->organization_id,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
        ]);
    }

    $invoice->recalculateTotals();

    expect((float) $invoice->subtotal)->toBe(4500.0);
    expect((float) $invoice->total)->toBe(5040.0);
    expect($invoice->estimate_id)->toBe($estimate->id);
    expect($estimate->invoice()->exists())->toBeTrue();
});

test('cross-tenant payment and refund attempts are rejected', function (): void {
    $otherOrg = Organization::create([
        'name' => 'Foreign Electrical Corp',
        'slug' => 'foreign-electrical-corp',
    ]);

    $foreignInvoice = Invoice::create([
        'organization_id' => $otherOrg->id,
        'customer_id' => Customer::create(['organization_id' => $otherOrg->id, 'name' => 'Other Customer'])->id,
        'status' => CommercialStatus::Sent,
        'tax_rate' => 0,
    ]);

    $foreignInvoice->items()->create([
        'organization_id' => $otherOrg->id,
        'description' => 'Foreign Service',
        'quantity' => 1,
        'unit_price' => 5000,
    ]);
    $foreignInvoice->recalculateTotals();

    // Act as user in Sterling HVAC
    $this->actingAs($this->accountant);
    Filament::setTenant($this->org);

    // Attempting to pay foreign invoice through Sterling session must fail (cannot find model in tenant)
    expect(function () use ($foreignInvoice) {
        Payment::recordAgainstInvoice($foreignInvoice, [
            'amount' => 1000,
            'received_at' => today()->toDateString(),
            'method' => 'cash',
        ]);
    })->toThrow(ModelNotFoundException::class);
});
