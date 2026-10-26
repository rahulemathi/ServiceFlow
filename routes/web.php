<?php

use App\Livewire\Technician\TechnicianWorkspace;
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
    return view('welcome');
})->name('home');

Route::redirect('/login', '/admin/login')->name('login');
Route::redirect('/dashboard', '/admin')->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/technician', TechnicianWorkspace::class)->name('technician.workspace');
    Route::get('/technician/{workOrder}', TechnicianWorkspace::class)->name('technician.workspace.show');
});
