<?php

use App\Models\User;
use App\UserRole;
use Database\Seeders\RoleUserSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds users for each role with the standard credentials and active roles', function () {
    $this->seed(RoleUserSeeder::class);

    $expectedRoles = [
        'superadmin@example.com' => UserRole::PlatformAdmin,
        'organization@example.com' => UserRole::OrganizationOwner,
        'admin@example.com' => UserRole::Admin,
        'dispatcher@example.com' => UserRole::Dispatcher,
        'servicemanager@example.com' => UserRole::ServiceManager,
        'manager@example.com' => UserRole::ServiceManager,
        'technician@example.com' => UserRole::Technician,
        'accountant@example.com' => UserRole::Accountant,
    ];

    foreach ($expectedRoles as $email => $role) {
        $user = User::where('email', $email)->first();

        expect($user)->not->toBeNull()
            ->and($user->must_change_password)->toBeFalse()
            ->and(Hash::check('12345678', $user->password))->toBeTrue();

        if ($role === UserRole::PlatformAdmin) {
            expect($user->hasPlatformRole(UserRole::PlatformAdmin))->toBeTrue();
        } else {
            expect($user->hasOrganizationRole($role))->toBeTrue();
        }
    }
});

it('can be run multiple times idempotently without duplicating accounts', function () {
    $this->seed(RoleUserSeeder::class);
    $this->seed(RoleUserSeeder::class);

    expect(User::where('email', 'admin@example.com')->count())->toBe(1)
        ->and(User::where('email', 'superadmin@example.com')->count())->toBe(1);
});
