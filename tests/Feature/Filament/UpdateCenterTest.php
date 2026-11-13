<?php

use App\Filament\Pages\UpdateCenter;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SystemSetting;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('allows only platform administrators to access the update center', function () {
    $organization = Organization::create([
        'name' => 'ServiceFlow HQ',
        'slug' => 'serviceflow-hq',
    ]);

    $platformAdmin = User::factory()->create([
        'must_change_password' => false,
    ]);
    $platformAdmin->assignPlatformRole(UserRole::PlatformAdmin);

    $organizationAdmin = User::factory()->create([
        'must_change_password' => false,
    ]);
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $organizationAdmin->id,
        'status' => 'active',
    ]);
    $membership->assignRole(UserRole::Admin);

    $this->actingAs($platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    $this->get(UpdateCenter::getUrl(tenant: $organization))
        ->assertOk();

    $this->actingAs($organizationAdmin);

    expect(UpdateCenter::canAccess())->toBeFalse();

    $this->get(UpdateCenter::getUrl(tenant: $organization))
        ->assertForbidden();
});

it('saves the GitHub repository and automatic notification preference', function () {
    $organization = Organization::create([
        'name' => 'ServiceFlow HQ',
        'slug' => 'serviceflow-hq',
    ]);
    $platformAdmin = User::factory()->create([
        'must_change_password' => false,
    ]);
    $platformAdmin->assignPlatformRole(UserRole::PlatformAdmin);

    $this->actingAs($platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    Livewire::test(UpdateCenter::class)
        ->fillForm([
            'repository_url' => 'https://github.com/acme/service-flow',
            'auto_check' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Update settings saved');

    expect(SystemSetting::get('updates_repository_url'))->toBe('https://github.com/acme/service-flow')
        ->and(filter_var(SystemSetting::get('updates_auto_check'), FILTER_VALIDATE_BOOLEAN))->toBeTrue();
});

it('rejects repository URLs that do not point to GitHub', function () {
    $organization = Organization::create([
        'name' => 'ServiceFlow HQ',
        'slug' => 'serviceflow-hq',
    ]);
    $platformAdmin = User::factory()->create([
        'must_change_password' => false,
    ]);
    $platformAdmin->assignPlatformRole(UserRole::PlatformAdmin);

    $this->actingAs($platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    Livewire::test(UpdateCenter::class)
        ->fillForm([
            'repository_url' => 'https://example.com/acme/service-flow',
            'auto_check' => false,
        ])
        ->call('save')
        ->assertHasFormErrors(['repository_url']);

    expect(SystemSetting::get('updates_repository_url'))->toBeNull();
});

it('notifies about a newer release and does not alert again after skipping it', function () {
    $organization = Organization::create([
        'name' => 'ServiceFlow HQ',
        'slug' => 'serviceflow-hq',
    ]);
    $platformAdmin = User::factory()->create([
        'must_change_password' => false,
    ]);
    $platformAdmin->assignPlatformRole(UserRole::PlatformAdmin);
    SystemSetting::set('updates_repository_url', 'https://github.com/acme/service-flow', 'updates');
    SystemSetting::set('updates_auto_check', true, 'updates');
    config(['app.version' => '1.0.0']);

    $this->actingAs($platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    Cache::flush();
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/repos/acme/service-flow/releases/latest' => Http::response([
            'tag_name' => 'v2.0.0',
            'name' => 'Version 2.0.0',
            'html_url' => 'https://github.com/acme/service-flow/releases/tag/v2.0.0',
            'published_at' => '2026-11-01T10:00:00Z',
        ]),
    ]);

    Livewire::test(UpdateCenter::class)
        ->assertNotified('New update available')
        ->assertSet('isUpdateAvailable', true)
        ->assertSee('Update Now')
        ->assertSee('https://github.com/acme/service-flow/releases/tag/v2.0.0')
        ->dispatch('updatesSkipRelease');

    expect(SystemSetting::get('updates_skipped_version'))->toBe('v2.0.0');

    Livewire::test(UpdateCenter::class)
        ->assertNotNotified('New update available');
});

it('does not notify when the latest release is not newer than the installed version', function () {
    $organization = Organization::create([
        'name' => 'ServiceFlow HQ',
        'slug' => 'serviceflow-hq',
    ]);
    $platformAdmin = User::factory()->create([
        'must_change_password' => false,
    ]);
    $platformAdmin->assignPlatformRole(UserRole::PlatformAdmin);
    SystemSetting::set('updates_repository_url', 'https://github.com/acme/service-flow', 'updates');
    SystemSetting::set('updates_auto_check', true, 'updates');
    config(['app.version' => '2.0.0']);

    $this->actingAs($platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    Cache::flush();
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/repos/acme/service-flow/releases/latest' => Http::response([
            'tag_name' => 'v1.9.9',
            'name' => 'Version 1.9.9',
            'html_url' => 'https://github.com/acme/service-flow/releases/tag/v1.9.9',
            'published_at' => '2026-10-01T10:00:00Z',
        ]),
    ]);

    Livewire::test(UpdateCenter::class)
        ->assertSet('isUpdateAvailable', false)
        ->assertNotNotified('New update available');
});

it('reminds the administrator a day later when they defer a release alert', function () {
    $organization = Organization::create([
        'name' => 'ServiceFlow HQ',
        'slug' => 'serviceflow-hq',
    ]);
    $platformAdmin = User::factory()->create([
        'must_change_password' => false,
    ]);
    $platformAdmin->assignPlatformRole(UserRole::PlatformAdmin);
    SystemSetting::set('updates_repository_url', 'https://github.com/acme/service-flow', 'updates');
    SystemSetting::set('updates_auto_check', true, 'updates');
    config(['app.version' => '1.0.0']);
    $this->travelTo(now()->setDate(2026, 11, 1)->setTime(10, 0));
    $expectedReminderTime = now()->addDay()->toDateTimeString();

    $this->actingAs($platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    Cache::flush();
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/repos/acme/service-flow/releases/latest' => Http::response([
            'tag_name' => 'v2.0.0',
            'name' => 'Version 2.0.0',
            'html_url' => 'https://github.com/acme/service-flow/releases/tag/v2.0.0',
            'published_at' => '2026-11-01T10:00:00Z',
        ]),
    ]);

    Livewire::test(UpdateCenter::class)
        ->dispatch('updatesRemindLater');

    expect(SystemSetting::get('updates_remind_until'))->toBe($expectedReminderTime);
});
