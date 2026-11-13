<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;

it('denies guests access to telescope even in the local environment', function () {
    $this->app->detectEnvironment(fn (): string => 'local');
    $request = Request::create('/telescope');

    expect(Telescope::check($request))->toBeFalse();
});

it('denies telescope access to users without the platform administrator role', function () {
    $user = User::factory()->create();
    $request = Request::create('/telescope');
    $request->setUserResolver(fn (): User => $user);

    expect(Telescope::check($request))->toBeFalse();
});

it('allows telescope access to platform administrators', function () {
    $user = User::factory()->create();
    $user->assignPlatformRole(UserRole::PlatformAdmin);
    $request = Request::create('/telescope');
    $request->setUserResolver(fn (): User => $user);

    expect(Telescope::check($request))->toBeTrue();
});
