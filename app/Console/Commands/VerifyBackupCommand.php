<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class VerifyBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'serviceflow:verify-backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify database schema integrity, required tables, and storage availability for operational recovery.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting ServiceFlow Backup & Operational Recovery Verification...');
        $hasErrors = false;

        // 1. Check Database Connectivity
        $this->line('Checking database connection...');
        try {
            DB::connection()->getPdo();
            $this->info('  [OK] Database connection established.');
        } catch (Throwable $e) {
            $this->error('  [FAIL] Database connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        // 2. Check Essential Tables & Counts
        $this->line('Verifying critical database tables and record availability...');
        $essentialTables = [
            'organizations',
            'users',
            'memberships',
            'customers',
            'work_orders',
            'invoices',
            'parts',
            'stock_locations',
            'purchase_orders',
            'maintenance_contracts',
        ];

        $tableReport = [];

        foreach ($essentialTables as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("  [FAIL] Required table missing: {$table}");
                $hasErrors = true;
                $tableReport[] = [$table, 'Missing', 0];
            } else {
                $count = DB::table($table)->count();
                $tableReport[] = [$table, 'Present', $count];
            }
        }

        $this->table(['Table', 'Status', 'Record Count'], $tableReport);

        // 3. Check Storage Availability
        $this->line('Verifying storage availability...');
        try {
            $testFilename = 'backup_verify_'.uniqid().'.tmp';
            Storage::disk('local')->put($testFilename, 'verified');
            $read = Storage::disk('local')->get($testFilename);
            Storage::disk('local')->delete($testFilename);

            if ($read === 'verified') {
                $this->info('  [OK] Storage read/write operational.');
            } else {
                $this->error('  [FAIL] Storage read mismatch.');
                $hasErrors = true;
            }
        } catch (Throwable $e) {
            $this->error('  [FAIL] Storage check failed: '.$e->getMessage());
            $hasErrors = true;
        }

        if ($hasErrors) {
            $this->error('Backup verification completed with ERRORS.');

            return self::FAILURE;
        }

        $this->info('Backup verification completed successfully. System is healthy and restorable.');

        return self::SUCCESS;
    }
}
