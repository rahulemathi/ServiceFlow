<?php

use App\Http\Controllers\EquipmentLookupController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\OrganizationInvitationController;
use App\Http\Controllers\ReportDownloadController;
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

Route::get('/health', [HealthCheckController::class, 'check'])->name('health');

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::redirect('/login', '/admin/login')->name('login');
Route::redirect('/dashboard', '/admin')->name('dashboard');

Route::get('/invitations/{token}', [OrganizationInvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [OrganizationInvitationController::class, 'accept'])->name('invitations.accept');

Route::get('/equipment/lookup/{serial}', [EquipmentLookupController::class, 'show'])->name('equipment.lookup');

Route::middleware(['auth'])->group(function () {
    Route::get('/technician', TechnicianWorkspace::class)->name('technician.workspace');
    Route::get('/technician/{workOrder}', TechnicianWorkspace::class)->name('technician.workspace.show');
    Route::get('/reports/download/{id}', ReportDownloadController::class)->name('reports.download');
});
