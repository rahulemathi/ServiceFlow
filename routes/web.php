<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| ServiceFlow's operational interface is powered by Filament, which
| automatically registers all tenant-scoped resource and page routes under
| '/admin' via App\Providers\Filament\AdminPanelProvider.
|
| The routes below provide top-level web entry points, standard Laravel route
| names (such as 'login'), and convenience redirects.
|
*/

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/admin');
    }

    return view('welcome');
})->name('home');

Route::redirect('/login', '/admin/login')->name('login');
Route::redirect('/dashboard', '/admin')->name('dashboard');
