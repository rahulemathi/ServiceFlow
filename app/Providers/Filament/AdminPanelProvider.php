<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard as ServiceFlowDashboard;
use App\Http\Controllers\Auth\ForceChangePasswordController;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Models\Organization;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('ServiceFlow')
            ->login()
            ->tenant(Organization::class, slugAttribute: 'slug', ownershipRelationship: 'organization')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->userMenuItems([
                'logout' => MenuItem::make()
                    ->label('Sign out')
                    ->icon(Heroicon::OutlinedArrowLeftStartOnRectangle)
                    ->color('danger')
                    ->postAction(fn (): string => Filament::getLogoutUrl()),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                ServiceFlowDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('/password/change', [ForceChangePasswordController::class, 'show'])
                    ->name('password.change');
                Route::post('/password/change', [ForceChangePasswordController::class, 'update'])
                    ->name('password.update');
            })
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordIsChanged::class,
            ]);
    }
}
