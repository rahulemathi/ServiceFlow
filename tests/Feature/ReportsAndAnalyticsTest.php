<?php

use App\Filament\Pages\ReportsCenter;
use App\Jobs\GenerateReportExportJob;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');

    $this->orgA = Organization::create([
        'name' => 'Nexus Facility Engineering',
        'slug' => 'nexus-facility-engineering',
    ]);

    $this->branchA = Branch::create([
        'organization_id' => $this->orgA->id,
        'name' => 'South Zone',
    ]);

    $this->accountant = User::factory()->create([
        'name' => 'Priya Finance',
        'email' => 'priya.finance@nexus.test',
    ]);

    $mAcc = Membership::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->accountant->id,
    ]);
    $mAcc->assignRole(UserRole::Accountant);

    $this->tech = User::factory()->create([
        'name' => 'Arun Tech',
        'email' => 'arun.tech@nexus.test',
    ]);

    $mTech = Membership::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->tech->id,
    ]);
    $mTech->assignRole(UserRole::Technician);

    $this->customer = Customer::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'name' => 'Orion Tower',
        'email' => 'info@oriontower.test',
    ]);

    // Org B
    $this->orgB = Organization::create([
        'name' => 'Pacific MEP',
        'slug' => 'pacific-mep',
    ]);

    $this->userB = User::factory()->create([
        'name' => 'External User',
        'email' => 'external@pacific.test',
    ]);

    $mB = Membership::create([
        'organization_id' => $this->orgB->id,
        'user_id' => $this->userB->id,
    ]);
    $mB->assignRole(UserRole::Admin);
});

test('reports center access is restricted by role', function (): void {
    $this->actingAs($this->accountant);
    Filament::setTenant($this->orgA);
    expect(ReportsCenter::canAccess())->toBeTrue();

    $this->actingAs($this->tech);
    expect(ReportsCenter::canAccess())->toBeFalse();
});

test('report export job generates csv and reconciles rows', function (): void {
    // Seed sample invoices
    $inv1 = Invoice::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'tax_rate' => 0,
    ]);
    $inv1->items()->create([
        'organization_id' => $this->orgA->id,
        'description' => 'Chiller maintenance',
        'quantity' => 1,
        'unit_price' => 15000,
    ]);
    $inv1->recalculateTotals();

    $export = ReportExport::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->accountant->id,
        'type' => 'financial',
        'status' => 'pending',
    ]);

    // Run export job synchronously
    $job = new GenerateReportExportJob($export);
    $job->handle();

    $export->refresh();
    expect($export->status)->toBe('completed');
    expect($export->row_count)->toBe(1);
    expect($export->file_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($export->file_path))->toBeTrue();

    $content = Storage::disk('local')->get($export->file_path);
    expect($content)->toContain('Invoice Number', $inv1->invoice_number, '15000');
});

test('report download enforces tenant access boundary', function (): void {
    $export = ReportExport::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->accountant->id,
        'type' => 'financial',
        'status' => 'pending',
    ]);

    $job = new GenerateReportExportJob($export);
    $job->handle();

    // Authenticated accountant in Org A can download
    $responseA = $this->actingAs($this->accountant)
        ->get(route('reports.download', $export->id));

    $responseA->assertOk();
    $responseA->assertHeader('content-type', 'text/csv; charset=UTF-8');

    // User in Org B is rejected with 403 Forbidden
    $responseB = $this->actingAs($this->userB)
        ->get(route('reports.download', $export->id));

    $responseB->assertForbidden();
});
