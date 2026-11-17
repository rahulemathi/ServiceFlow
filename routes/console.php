<?php

use App\MaintenanceContractStatus;
use App\Models\MaintenanceContract;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('serviceflow:generate-maintenance-work-orders', function () {
    $today = now()->toDateString();

    // 1. Mark contracts past ends_at as expired
    MaintenanceContract::query()
        ->where('status', MaintenanceContractStatus::Active->value)
        ->whereNotNull('ends_at')
        ->where('ends_at', '<', $today)
        ->update([
            'status' => MaintenanceContractStatus::Expired->value,
            'next_service_at' => null,
        ]);

    // 2. Generate work orders for active contracts due today or earlier
    $contracts = MaintenanceContract::query()
        ->where('status', MaintenanceContractStatus::Active->value)
        ->whereNotNull('next_service_at')
        ->where('next_service_at', '<=', $today)
        ->get();

    $generated = 0;
    $skipped = 0;
    foreach ($contracts as $contract) {
        $wo = $contract->generateWorkOrder();
        if ($wo) {
            $generated++;
        } else {
            $skipped++;
        }
    }

    $this->info("Generated {$generated} maintenance work order(s).");

    if ($skipped > 0) {
        $this->comment("Skipped {$skipped} contract(s) that were already generated for today.");
    }

    // 3. Check for observable expiration reminders
    $expiringCount = MaintenanceContract::query()
        ->where('status', MaintenanceContractStatus::Active->value)
        ->whereNotNull('ends_at')
        ->whereBetween('ends_at', [$today, now()->addDays(30)->toDateString()])
        ->count();

    if ($expiringCount > 0) {
        $this->comment("Notice: {$expiringCount} active contract(s) are expiring within the next 30 days.");
    }
})->purpose('Generate recurring work orders for active maintenance contracts due on or before today');

Schedule::command('telescope:prune --hours=3')->everyThreeHours();
