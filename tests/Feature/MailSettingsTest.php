<?php

use App\Filament\Pages\MailSettings;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

    $this->orgOwner = User::factory()->create([
        'email' => 'org-owner-test@example.com',
        'must_change_password' => false,
    ]);
    $ownerMembership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->orgOwner->id,
        'status' => 'active',
    ]);
    $ownerMembership->assignRole(UserRole::OrganizationOwner);

    $this->orgAdmin = User::factory()->create([
        'email' => 'org-admin-test@example.com',
        'must_change_password' => false,
    ]);
    $adminMembership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->orgAdmin->id,
        'status' => 'active',
    ]);
    $adminMembership->assignRole(UserRole::Admin);
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

it('denies access to organization owners and organization admins', function () {
    // Org Owner
    $this->actingAs($this->orgOwner);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    expect(MailSettings::canAccess())->toBeFalse();
    $this->get(MailSettings::getUrl(tenant: $this->organization))
        ->assertForbidden();

    // Org Admin
    $this->actingAs($this->orgAdmin);
    Filament::setTenant($this->organization);

    expect(MailSettings::canAccess())->toBeFalse();
    $this->get(MailSettings::getUrl(tenant: $this->organization))
        ->assertForbidden();
});

it('loads deployment-managed configuration from Laravel config and does not expose secrets', function () {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'smtp.deploy-server.com',
        'mail.mailers.smtp.port' => 587,
        'mail.mailers.smtp.scheme' => 'tls',
        'mail.mailers.smtp.username' => 'deploy-user@example.com',
        'mail.mailers.smtp.password' => 'super-secret-deployment-password',
        'mail.from.address' => 'system@serviceflow.com',
        'mail.from.name' => 'ServiceFlow Platform',
    ]);

    $this->actingAs($this->platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    $component = Livewire::test(MailSettings::class);

    $component->assertSchemaStateSet([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.deploy-server.com',
        'mail_port' => '587',
        'mail_scheme' => 'tls',
        'mail_username' => 'deploy-user@example.com',
        'mail_from_address' => 'system@serviceflow.com',
        'mail_from_name' => 'ServiceFlow Platform',
    ]);

    // Password must never be exposed
    $state = $component->get('data');
    expect($state)->not->toHaveKey('mail_password')
        ->and($state['mail_password_status'])->not->toContain('super-secret-deployment-password')
        ->and($state['mail_password_status'])->toContain('••••••••');
});

it('does not allow requests to write SMTP credentials into .env', function () {
    $this->actingAs($this->platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    $envFileBefore = file_exists(base_path('.env')) ? file_get_contents(base_path('.env')) : '';

    $component = Livewire::test(MailSettings::class);

    // Assert that 'save' method does not exist or cannot be called to modify .env
    expect(method_exists(MailSettings::class, 'save'))->toBeFalse();
    expect(method_exists(MailSettings::class, 'updateEnvironmentFile'))->toBeFalse();

    $envFileAfter = file_exists(base_path('.env')) ? file_get_contents(base_path('.env')) : '';
    expect($envFileAfter)->toBe($envFileBefore);
});

it('allows platform administrators to dispatch a test email using server configuration', function () {
    Mail::spy();

    $this->actingAs($this->platformAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    Livewire::test(MailSettings::class)
        ->callAction('testEmail', data: [
            'recipient' => 'diagnostics@serviceflow.com',
        ])
        ->assertNotified('Test email dispatched');

    Mail::shouldHaveReceived('raw');
});

it('rejects test email execution from unauthorized users', function () {
    Mail::fake();

    $this->actingAs($this->orgAdmin);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->organization);

    expect(fn () => (new MailSettings)->sendTestEmail('hacker@example.com'))
        ->toThrow(HttpException::class);

    Mail::assertNothingSent();
});
