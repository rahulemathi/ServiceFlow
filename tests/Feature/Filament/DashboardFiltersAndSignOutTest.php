<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\RecentServiceRequestsWidget;
use App\Filament\Widgets\ServiceOverviewWidget;
use App\Filament\Widgets\TechnicianUtilizationWidget;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->organization = Organization::create([
        'name' => 'Acme Services',
        'slug' => 'acme-services',
    ]);

    $this->branch = Branch::create([
        'organization_id' => $this->organization->id,
        'name' => 'Downtown Branch',
        'code' => 'DT-01',
    ]);

    $this->user = User::factory()->create([
        'name' => 'Jane Service Admin',
        'email' => 'jane@example.com',
    ]);

    $membership = Membership::create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->user->id,
    ]);
    $membership->assignRole(UserRole::Admin);

    $this->actingAs($this->user);
    Filament::setTenant($this->organization);
});

it('renders dashboard with standardized From Date and To Date pickers and aligned branch filter', function () {
    $response = $this->get("/admin/{$this->organization->slug}");
    $response->assertOk();

    $content = $response->getContent();

    // Check labels
    expect($content)->toContain('From Date')
        ->toContain('To Date')
        ->toContain('Branch');

    // Check inputs have calendar date-time picker class and uniform placeholder
    expect($content)->toContain('fi-fo-date-time-picker')
        ->toContain('placeholder="YYYY-MM-DD"')
        ->toContain('fi-fo-date-time-picker-panel')
        ->toContain('relative z-20 overflow-visible');

    // Check responsive grid columns: 1 column default, 2 columns on md, 7 columns on lg
    expect($content)->toContain('--cols-default: repeat(1, minmax(0, 1fr))')
        ->toContain('--cols-md: repeat(2, minmax(0, 1fr))')
        ->toContain('--cols-lg: repeat(7, minmax(0, 1fr))')
        ->toContain('--col-span-lg: span 2 / span 2')
        ->toContain('--col-span-lg: span 3 / span 3')
        ->toContain('--col-span-md: span 2 / span 2');
});

it('validates date range on the dashboard filters form', function () {
    $dashboard = app(Dashboard::class);
    $form = $dashboard->getFiltersForm();
    $form->rawState([
        'startDate' => '2026-05-15',
        'endDate' => '2026-05-20',
        'branchId' => $this->branch->id,
    ]);

    expect($form->getState()['startDate'])->toBe('2026-05-15')
        ->and($form->getState()['endDate'])->toBe('2026-05-20')
        ->and($form->getState()['branchId'])->toBe($this->branch->id);
});

it('prevents invalid date range where From Date is after To Date', function () {
    $dashboard = app(Dashboard::class);
    $form = $dashboard->getFiltersForm();
    $form->rawState([
        'startDate' => '2026-06-25',
        'endDate' => '2026-06-10',
    ]);

    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class);
});

it('prevents invalid date range where To Date is before From Date', function () {
    $dashboard = app(Dashboard::class);
    $form = $dashboard->getFiltersForm();
    $form->rawState([
        'startDate' => '2026-06-20',
        'endDate' => '2026-06-10',
    ]);

    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class);
});

it('renders account widget with redesigned sign-out button', function () {
    Livewire::test(AccountWidget::class)
        ->assertSee('Sign out')
        ->assertSee(Filament::getLogoutUrl(), escape: false)
        ->assertSee('method="post"', escape: false)
        ->assertSee('text-rose-700')
        ->assertSee('bg-rose-50');
});

it('configures user menu item with danger styled POST logout action', function () {
    $userMenuItems = Filament::getUserMenuItems();
    expect($userMenuItems)->toHaveKey('logout');

    $logoutItem = $userMenuItems['logout'];
    expect($logoutItem->getLabel())->toBe('Sign out')
        ->and($logoutItem->getColor())->toBe('danger')
        ->and($logoutItem->getUrl())->toBe(Filament::getLogoutUrl())
        ->and($logoutItem->shouldPostToUrl())->toBeTrue()
        ->and($logoutItem->getIcon())->toBe(Heroicon::OutlinedArrowLeftStartOnRectangle);

    $response = $this->get("/admin/{$this->organization->slug}");
    $response->assertOk();

    $content = $response->getContent();
    expect($content)->toContain(Filament::getLogoutUrl())
        ->toContain('Sign out')
        ->toContain('fi-color-danger');
});

it('performs real POST logout, terminates session, and blocks dashboard access', function () {
    $this->assertAuthenticated();

    $response = $this->post('/admin/logout');
    $response->assertRedirect('/admin/login');

    $this->assertGuest();

    // Verify unauthenticated user cannot access dashboard route
    $this->get("/admin/{$this->organization->slug}")
        ->assertRedirect('/admin/login');
});

it('allows dashboard widgets to process both date and datetime formats from filters', function () {
    Livewire::withQueryParams([
        'filters' => [
            'startDate' => '2026-10-01 00:00:00',
            'endDate' => '2026-11-23 23:59:59',
            'branchId' => $this->branch->id,
        ],
    ]);

    Livewire::test(ServiceOverviewWidget::class)
        ->assertSuccessful();

    Livewire::test(RecentServiceRequestsWidget::class)
        ->assertSuccessful();

    Livewire::test(TechnicianUtilizationWidget::class)
        ->assertSuccessful();
});
