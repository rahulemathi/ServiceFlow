<?php

use App\MaintenanceContractStatus;
use App\Models\MaintenanceContract;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('serviceflow:generate-maintenance-work-orders', function () {
    $today = now()->toDateString();
    $contracts = MaintenanceContract::query()
        ->where('status', MaintenanceContractStatus::Active->value)
        ->whereNotNull('next_service_at')
        ->where('next_service_at', '<=', $today)
        ->get();

    $count = 0;
    foreach ($contracts as $contract) {
        $contract->generateWorkOrder();
        $count++;
    }

    $this->info("Generated {$count} maintenance work order(s).");
})->purpose('Generate recurring work orders for active maintenance contracts due on or before today');
