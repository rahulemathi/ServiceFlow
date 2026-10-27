<?php

use App\Filament\Pages\BrandingSettings;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SystemSetting;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->organization = Organization::firstOrCreate(
        ['slug' => 'serviceflow-hq'],
        ['name' => 'ServiceFlow HQ']
    );

    $this->platformAdmin = User::factory()->create([
        'email' => 'platform-branding-test@example.com',
        'must_change_password' => false,
    ]);
    $this->platformAdmin->assignPlatformRole(UserRole::PlatformAdmin);

    $this->orgAdmin = User::factory()->create([
        'email' => 'org-branding-test@example.com',
        'must_change_password' => false,
    ]);
    $membership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->orgAdmin->id,
        'status' => 'active',
    ]);
    $membership->assignRole(UserRole::Admin);
});

it('allows platform administrators to access the branding settings page', function () {
    $this->actingAs($this->platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    expect(BrandingSettings::canAccess())->toBeTrue();

    $this->get(BrandingSettings::getUrl(tenant: $this->organization))
        ->assertOk();
});

it('denies access to non-platform admin users', function () {
    $this->actingAs($this->orgAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    expect(BrandingSettings::canAccess())->toBeFalse();

    $this->get(BrandingSettings::getUrl(tenant: $this->organization))
        ->assertForbidden();
});

it('saves branding and portal identity settings to system_settings table', function () {
    $this->actingAs($this->platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    Livewire::test(BrandingSettings::class)
        ->fillForm([
            'brand_name' => 'Acme Service Flow',
            'brand_tagline' => 'Next-gen field operations',
            'brand_logo_height' => '2.75rem',
            'primary_color' => 'emerald',
            'support_email' => 'help@acme-flow.com',
            'footer_text' => '© 2026 Acme Corp. All rights reserved.',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Branding settings saved');

    expect(SystemSetting::get('brand_name'))->toBe('Acme Service Flow')
        ->and(SystemSetting::get('brand_tagline'))->toBe('Next-gen field operations')
        ->and(SystemSetting::get('brand_logo_height'))->toBe('2.75rem')
        ->and(SystemSetting::get('primary_color'))->toBe('emerald')
        ->and(SystemSetting::get('support_email'))->toBe('help@acme-flow.com')
        ->and(SystemSetting::get('footer_text'))->toBe('© 2026 Acme Corp. All rights reserved.');
});

it('resets branding settings back to defaults', function () {
    $this->actingAs($this->platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    // Set custom setting first
    SystemSetting::set('brand_name', 'Custom Brand', 'branding');
    SystemSetting::set('primary_color', 'rose', 'branding');

    Livewire::test(BrandingSettings::class)
        ->call('resetDefaults')
        ->assertNotified('Branding reset to defaults');

    expect(SystemSetting::get('brand_name'))->toBe('ServiceFlow')
        ->and(SystemSetting::get('primary_color'))->toBe('blue');
});
