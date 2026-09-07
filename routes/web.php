<?php

use App\Http\Controllers\CompanySelectionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SessionController;
use App\Http\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/companies/select')->name('tenancy.home');
Route::middleware('guest')->withoutMiddleware(ResolveTenantContext::class)->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('tenancy.logout');
    Route::get('/companies/select', [CompanySelectionController::class, 'index'])->name('tenancy.select');
    Route::post('/companies/select', [CompanySelectionController::class, 'store'])->name('tenancy.activate');
    Route::resource('customers', CustomerController::class)->except('destroy');
    Route::patch('/customers/{customer}/status', [CustomerController::class, 'status'])->name('customers.status');
});
