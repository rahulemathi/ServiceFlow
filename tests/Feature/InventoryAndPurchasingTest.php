<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Part;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEntry;
use App\StockMovementType;
use App\UserRole;
use App\WorkOrderStatus;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->orgA = Organization::create([
        'name' => 'Metro HVAC Solutions',
        'slug' => 'metro-hvac-solutions',
    ]);

    $this->branchA = Branch::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Central Hub',
    ]);

    $this->user = User::factory()->create([
        'name' => 'Inventory Manager',
        'email' => 'inventory@metrohvac.test',
    ]);

    $m = Membership::create([
        'organization_id' => $this->orgA->id,
        'user_id' => $this->user->id,
    ]);
    $m->assignRole(UserRole::Admin);

    $this->customer = Customer::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'name' => 'Skyline Mall',
        'email' => 'ops@skylinemall.test',
    ]);

    $this->warehouse = StockLocation::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'name' => 'Central Warehouse',
    ]);

    $this->serviceVan = StockLocation::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'name' => 'Service Van #1',
    ]);

    $this->part = Part::create([
        'organization_id' => $this->orgA->id,
        'sku' => 'FILTER-MERV13',
        'name' => 'MERV 13 Air Filter 20x25x4',
        'cost_price' => 450,
        'selling_price' => 850,
        'unit' => 'piece',
        'min_stock_level' => 10,
    ]);
});

test('part stock adjustment records attributable movement and updates balance', function (): void {
    expect($this->part->totalStock())->toBe(0.0);
    expect($this->part->isLowStock())->toBeTrue();

    // Add 50 filters to warehouse
    $movement = $this->part->adjustStock($this->warehouse, 50, 'Initial delivery receipt', $this->user);

    expect($this->part->fresh()->totalStock())->toBe(50.0);
    expect($this->part->stockAt($this->warehouse))->toBe(50.0);
    expect($this->part->fresh()->isLowStock())->toBeFalse();

    expect($movement->type)->toBe(StockMovementType::Adjustment);
    expect($movement->quantity)->toBe(50.0);
    expect($movement->balance_after)->toBe(50.0);
    expect($movement->user_id)->toBe($this->user->id);
    expect($movement->organization_id)->toBe($this->orgA->id);
});

test('stock cannot become negative through adjustment or consumption', function (): void {
    $this->part->adjustStock($this->warehouse, 10, 'Stock in', $this->user);

    // Adjusting below zero throws ValidationException
    expect(fn () => $this->part->adjustStock($this->warehouse, -15, 'Invalid reduction', $this->user))
        ->toThrow(ValidationException::class);

    // Consuming more than available throws ValidationException
    expect(fn () => $this->part->consumeStock($this->warehouse, 15, null, 'Overconsumption', $this->user))
        ->toThrow(ValidationException::class);

    expect($this->part->stockAt($this->warehouse))->toBe(10.0);
});

test('stock transfer between locations updates both balances and records movements', function (): void {
    $this->part->adjustStock($this->warehouse, 20, 'Initial receipt', $this->user);

    $this->part->transferStock($this->warehouse, $this->serviceVan, 5, 'Van replenishment', $this->user);

    expect($this->part->stockAt($this->warehouse))->toBe(15.0);
    expect($this->part->stockAt($this->serviceVan))->toBe(5.0);
    expect($this->part->totalStock())->toBe(20.0);

    // Verify attributable movements
    $movements = StockMovement::where('part_id', $this->part->id)->latest('id')->take(2)->get();
    expect($movements->count())->toBe(2);

    $inbound = $movements->firstWhere('type', StockMovementType::TransferIn);
    $outbound = $movements->firstWhere('type', StockMovementType::TransferOut);

    expect($inbound)->not->toBeNull();
    expect($inbound->stock_location_id)->toBe($this->serviceVan->id);
    expect($inbound->quantity)->toBe(5.0);

    expect($outbound)->not->toBeNull();
    expect($outbound->stock_location_id)->toBe($this->warehouse->id);
    expect($outbound->quantity)->toBe(-5.0);
});

test('work order part entry consumes stock transactionally', function (): void {
    $this->part->adjustStock($this->serviceVan, 10, 'Van stock', $this->user);

    $wo = WorkOrder::create([
        'organization_id' => $this->orgA->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'title' => 'Filter Replacement Service',
        'status' => WorkOrderStatus::InProgress,
    ]);

    // Create entry that consumes 2 filters
    $entry = WorkOrderEntry::create([
        'organization_id' => $this->orgA->id,
        'work_order_id' => $wo->id,
        'user_id' => $this->user->id,
        'type' => 'part',
        'part_id' => $this->part->id,
        'stock_location_id' => $this->serviceVan->id,
        'description' => 'Installed MERV-13 filters on AHU-1',
        'quantity' => 2,
        'unit_price' => 850,
    ]);

    expect($entry->id)->toBeGreaterThan(0);
    expect($this->part->stockAt($this->serviceVan))->toBe(8.0);

    $consumptionMovement = StockMovement::where('part_id', $this->part->id)
        ->where('type', StockMovementType::WorkOrderConsumption->value)
        ->latest('id')
        ->first();

    expect($consumptionMovement)->not->toBeNull();
    expect($consumptionMovement->quantity)->toBe(-2.0);
    expect($consumptionMovement->balance_after)->toBe(8.0);
    expect($consumptionMovement->reference_type)->toBe(WorkOrder::class);
    expect($consumptionMovement->reference_id)->toBe($wo->id);
});

test('cross-tenant inventory operations are strictly rejected', function (): void {
    $orgB = Organization::create([
        'name' => 'Zenith Refrigeration',
        'slug' => 'zenith-refrigeration',
    ]);

    $warehouseB = StockLocation::create([
        'organization_id' => $orgB->id,
        'name' => 'Zenith Depot',
    ]);

    // Cannot adjust part stock using another organization's location
    expect(fn () => $this->part->adjustStock($warehouseB, 10))
        ->toThrow(LogicException::class);

    // Can create part with the same SKU in another organization without collision
    $partB = Part::create([
        'organization_id' => $orgB->id,
        'sku' => 'FILTER-MERV13', // Same SKU as Org A
        'name' => 'Zenith Filter',
        'cost_price' => 400,
        'selling_price' => 800,
    ]);

    expect($partB->id)->toBeGreaterThan(0);
    expect($partB->organization_id)->toBe($orgB->id);
});
