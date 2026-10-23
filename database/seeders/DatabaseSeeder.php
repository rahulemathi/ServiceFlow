<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;
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
        $organization = Organization::firstOrCreate(
            ['slug' => 'serviceflow-hq'],
            ['name' => 'ServiceFlow HQ']
        );

        Branch::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Main Branch',
            ],
            [
                'code' => 'MAIN',
            ]
        );

        $users = User::all();
        if ($users->isEmpty()) {
            $user = User::factory()->create([
                'name' => 'Admin User',
                'email' => 'admin@serviceflow.com',
            ]);
            $users = collect([$user]);
        }

        foreach ($users as $user) {
            $membership = Membership::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => 'active',
                ]
            );

            $membership->assignRole(UserRole::OrganizationOwner);
        }
    }
}
