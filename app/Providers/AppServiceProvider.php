<?php

namespace App\Providers;

use App\Events\WorkOrderAssigned;
use App\Events\WorkOrderUpdated;
use App\Http\Responses\LoginResponse;
use App\Listeners\SendWorkOrderAssignedNotification;
use App\Listeners\SendWorkOrderUpdatedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \Filament\Auth\Http\Responses\Contracts\LoginResponse::class,
            LoginResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Event::listen(WorkOrderAssigned::class, SendWorkOrderAssignedNotification::class);
        Event::listen(WorkOrderUpdated::class, SendWorkOrderUpdatedNotification::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
