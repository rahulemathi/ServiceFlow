<?php

use App\Filament\Pages\MailSettings;
use App\Models\Membership;
use App\Models\Organization;
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
        'email' => 'platform-test@example.com',
        'must_change_password' => false,
    ]);
    $this->platformAdmin->assignPlatformRole(UserRole::PlatformAdmin);

    $this->orgAdmin = User::factory()->create([
        'email' => 'org-admin-test@example.com',
        'must_change_password' => false,
    ]);
    $membership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->orgAdmin->id,
        'status' => 'active',
    ]);
    $membership->assignRole(UserRole::Admin);
});

it('allows only platform administrators to access the mail settings page', function () {
    $this->actingAs($this->platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    expect(MailSettings::canAccess())->toBeTrue();

    $this->get(MailSettings::getUrl(tenant: $this->organization))
        ->assertOk();
});

it('denies access to non-platform admin users', function () {
    $this->actingAs($this->orgAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    expect(MailSettings::canAccess())->toBeFalse();

    $this->get(MailSettings::getUrl(tenant: $this->organization))
        ->assertForbidden();
});

it('saves Gmail configuration to .env with preset host, port, and APP_NAME as from name', function () {
    $this->actingAs($this->platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    $component = Livewire::test(MailSettings::class)
        ->fillForm([
            'provider' => 'gmail',
            'mail_username' => 'testuser@gmail.com',
            'mail_password' => 'abcd efgh ijkl mnop',
            'mail_from_address' => 'testuser@gmail.com',
        ])
        ->call('save');

    $component->assertHasNoFormErrors();

    $envContent = file_get_contents(base_path('.env'));
    expect($envContent)->toContain('MAIL_MAILER=smtp')
        ->and($envContent)->toContain('MAIL_HOST=smtp.gmail.com')
        ->and($envContent)->toContain('MAIL_PORT=587')
        ->and($envContent)->toContain('MAIL_SCHEME=tls')
        ->and($envContent)->toContain('MAIL_USERNAME=testuser@gmail.com')
        ->and($envContent)->toContain('MAIL_FROM_NAME="${APP_NAME}"');
});

it('saves custom SMTP configuration when other provider is selected', function () {
    $this->actingAs($this->platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    $component = Livewire::test(MailSettings::class)
        ->fillForm([
            'provider' => 'other',
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.custom-mail.com',
            'mail_port' => 465,
            'mail_scheme' => 'ssl',
            'mail_username' => 'custom-smtp-user',
            'mail_password' => 'secret-smtp-password',
            'mail_from_address' => 'alerts@custom-domain.com',
        ])
        ->call('save');

    $component->assertHasNoFormErrors();

    $envContent = file_get_contents(base_path('.env'));
    expect($envContent)->toContain('MAIL_HOST=smtp.custom-mail.com')
        ->and($envContent)->toContain('MAIL_PORT=465')
        ->and($envContent)->toContain('MAIL_SCHEME=ssl')
        ->and($envContent)->toContain('MAIL_USERNAME=custom-smtp-user')
        ->and($envContent)->toContain('MAIL_FROM_ADDRESS=alerts@custom-domain.com')
        ->and($envContent)->toContain('MAIL_FROM_NAME="${APP_NAME}"');
});
