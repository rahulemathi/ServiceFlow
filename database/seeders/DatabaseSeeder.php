<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Demo accounts and known credentials are only provisioned in local, testing, or development environments
        if (app()->environment(['local', 'testing', 'development'])) {
            $this->call(RoleUserSeeder::class);
        }
    }
}
