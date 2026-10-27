<?php

namespace Database\Seeders;

use App\MembershipStatus;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::firstOrCreate(
            ['slug' => 'serviceflow-hq'],
            ['name' => 'ServiceFlow HQ']
        );

        $branch = Branch::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Main Branch',
            ],
            [
                'code' => 'MAIN',
            ]
        );

        /** @var array<int, array{email: string, name: string, role: UserRole}> $roleAccounts */
        $roleAccounts = [
            [
                'email' => 'superadmin@example.com',
                'name' => 'Super Administrator',
                'role' => UserRole::PlatformAdmin,
            ],
            [
                'email' => 'organization@example.com',
                'name' => 'Organization Owner',
                'role' => UserRole::OrganizationOwner,
            ],
            [
                'email' => 'admin@example.com',
                'name' => 'Organization Administrator',
                'role' => UserRole::Admin,
            ],
            [
                'email' => 'dispatcher@example.com',
                'name' => 'Service Dispatcher',
                'role' => UserRole::Dispatcher,
            ],
            [
                'email' => 'servicemanager@example.com',
                'name' => 'Service Manager',
                'role' => UserRole::ServiceManager,
            ],
            [
                'email' => 'manager@example.com',
                'name' => 'Service Manager (Alt)',
                'role' => UserRole::ServiceManager,
            ],
            [
                'email' => 'technician@example.com',
                'name' => 'Service Technician',
                'role' => UserRole::Technician,
            ],
            [
                'email' => 'accountant@example.com',
                'name' => 'Organization Accountant',
                'role' => UserRole::Accountant,
            ],
        ];

        foreach ($roleAccounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make('12345678'),
                    'must_change_password' => false,
                    'email_verified_at' => now(),
                ]
            );

            $membership = Membership::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => MembershipStatus::Active,
                ]
            );

            $membership->branches()->syncWithoutDetaching([
                $branch->id => ['organization_id' => $organization->id],
            ]);

            if ($account['role'] === UserRole::PlatformAdmin) {
                $user->assignPlatformRole(UserRole::PlatformAdmin);
            } else {
                $membership->assignRole($account['role']);
            }
        }
    }
}
