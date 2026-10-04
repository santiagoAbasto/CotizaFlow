<?php

use App\Http\Controllers\CompanySelectionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteLookupController;
use App\Http\Controllers\QuotePdfController;
use App\Http\Controllers\QuotePreviewController;
use App\Http\Controllers\QuoteStatusController;
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
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('customers', CustomerController::class)->except('destroy');
    Route::patch('/customers/{customer}/status', [CustomerController::class, 'status'])->name('customers.status');
    Route::resource('products', ProductController::class)->except('destroy');
    Route::patch('/products/{product}/status', [ProductController::class, 'status'])->name('products.status');
    Route::resource('price-lists', PriceListController::class)->except('destroy')->parameters(['price-lists' => 'priceList']);
    Route::patch('/price-lists/{priceList}/status', [PriceListController::class, 'status'])->name('price-lists.status');
    Route::put('/price-lists/{priceList}/products/{product}', [PriceListController::class, 'setProductPrice'])->name('price-lists.products.update');
    Route::delete('/price-lists/{priceList}/products/{product}', [PriceListController::class, 'removeProductPrice'])->name('price-lists.products.destroy');
    Route::get('/quotes/customers/search', [QuoteLookupController::class, 'customers'])->name('quotes.customers.search');
    Route::get('/quotes/products/search', [QuoteLookupController::class, 'products'])->name('quotes.products.search');
    Route::post('/quotes/preview', [QuotePreviewController::class, 'create'])->name('quotes.preview');
    Route::post('/quotes/{quote}/preview', [QuotePreviewController::class, 'update'])->name('quotes.preview.update');
    Route::get('/quotes/{quote}/pdf', QuotePdfController::class)->name('quotes.pdf');
    Route::post('/quotes/{quote}/send', [QuoteStatusController::class, 'send'])->name('quotes.send');
    Route::post('/quotes/{quote}/accept', [QuoteStatusController::class, 'accept'])->name('quotes.accept');
    Route::post('/quotes/{quote}/reject', [QuoteStatusController::class, 'reject'])->name('quotes.reject');
    Route::resource('quotes', QuoteController::class)->except('destroy');
    Route::get('/follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
    Route::post('/quotes/{quote}/follow-ups', [FollowUpController::class, 'store'])->name('quotes.follow-ups.store');
    Route::put('/follow-ups/{followUp}', [FollowUpController::class, 'update'])->name('follow-ups.update');
    Route::post('/follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('follow-ups.complete');
    Route::post('/follow-ups/{followUp}/cancel', [FollowUpController::class, 'cancel'])->name('follow-ups.cancel');
});
