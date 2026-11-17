<?php

use App\Models\User;
use App\UserRole;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleUserSeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;

it('seeds users for each role with the standard credentials and active roles in testing environment', function () {
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

it('prevents direct invocation of demo seeder in production environment', function () {
    $originalEnv = App::environment();
    App::detectEnvironment(fn () => 'production');

    expect(App::environment('production'))->toBeTrue();

    try {
        expect(fn () => (new RoleUserSeeder)->run())
            ->toThrow(RuntimeException::class, 'Demo accounts and known credentials cannot be seeded in [production] environment.');

        expect(fn () => $this->artisan('db:seed', ['--class' => RoleUserSeeder::class, '--force' => true]))
            ->toThrow(RuntimeException::class, 'Demo accounts and known credentials cannot be seeded in [production] environment.');
    } finally {
        App::detectEnvironment(fn () => $originalEnv);
    }
});

it('prevents direct invocation of demo seeder in staging environment', function () {
    $originalEnv = App::environment();
    App::detectEnvironment(fn () => 'staging');

    expect(App::environment('staging'))->toBeTrue();

    try {
        expect(fn () => (new RoleUserSeeder)->run())
            ->toThrow(RuntimeException::class, 'Demo accounts and known credentials cannot be seeded in [staging] environment.');

        expect(fn () => $this->artisan('db:seed', ['--class' => RoleUserSeeder::class, '--force' => true]))
            ->toThrow(RuntimeException::class, 'Demo accounts and known credentials cannot be seeded in [staging] environment.');
    } finally {
        App::detectEnvironment(fn () => $originalEnv);
    }
});

it('does not provision demo accounts when DatabaseSeeder runs in production', function () {
    $originalEnv = App::environment();
    App::detectEnvironment(fn () => 'production');

    try {
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        expect(User::where('email', 'superadmin@example.com')->exists())->toBeFalse()
            ->and(User::where('email', 'admin@example.com')->exists())->toBeFalse();
    } finally {
        App::detectEnvironment(fn () => $originalEnv);
    }
});

it('does not provision demo accounts when DatabaseSeeder runs in staging', function () {
    $originalEnv = App::environment();
    App::detectEnvironment(fn () => 'staging');

    try {
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        expect(User::where('email', 'superadmin@example.com')->exists())->toBeFalse()
            ->and(User::where('email', 'admin@example.com')->exists())->toBeFalse();
    } finally {
        App::detectEnvironment(fn () => $originalEnv);
    }
});
