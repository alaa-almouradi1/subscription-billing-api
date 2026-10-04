<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.key')->group(function () {
    Route::post('customers', [CustomerController::class, 'store']);
    Route::get('customers/{customer}', [CustomerController::class, 'show']);
    Route::patch('customers/{customer}', [CustomerController::class, 'update']);
    Route::get('customers/{customer}/invoices', [InvoiceController::class, 'index']);

    Route::get('plans', [PlanController::class, 'index']);
    Route::post('plans', [PlanController::class, 'store']);
    Route::get('plans/{plan}', [PlanController::class, 'show']);

    Route::post('subscriptions', [SubscriptionController::class, 'store']);
    Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show']);
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('subscriptions/{subscription}/change-plan', [SubscriptionController::class, 'changePlan']);

    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay']);
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void']);
    Route::post('invoices/{invoice}/mark-uncollectible', [InvoiceController::class, 'markUncollectible']);
});
