<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $changePasswordRouteName = 'filament.admin.password.change';
        $changePasswordRoutePattern = 'filament.admin.password.*';
        $logoutRouteName = 'filament.admin.auth.logout';

        $isChangePasswordRoute = $request->routeIs($changePasswordRoutePattern);
        $isLogoutRoute = $request->routeIs($logoutRouteName);

        if ($user->must_change_password) {
            if (! $isChangePasswordRoute && ! $isLogoutRoute) {
                return redirect()->route($changePasswordRouteName);
            }

            return $next($request);
        }

        if ($isChangePasswordRoute) {
            return redirect()->to(Filament::getUrl() ?? '/admin');
        }

        return $next($request);
    }
}
