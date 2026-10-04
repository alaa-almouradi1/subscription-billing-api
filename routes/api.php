<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PspWebhookController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.key', 'throttle:api'])->group(function () {
    Route::middleware('api.scope:billing.read')->group(function () {
        Route::get('customers/{customer}', [CustomerController::class, 'show']);
        Route::get('customers/{customer}/invoices', [InvoiceController::class, 'index']);
        Route::get('plans', [PlanController::class, 'index']);
        Route::get('plans/{plan}', [PlanController::class, 'show']);
        Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show']);
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    });

    // Writes: an Idempotency-Key header is honoured when sent.
    Route::middleware('idempotent')->group(function () {
        Route::middleware('api.scope:customers.write')->group(function () {
            Route::post('customers', [CustomerController::class, 'store']);
            Route::patch('customers/{customer}', [CustomerController::class, 'update']);
        });

        Route::post('plans', [PlanController::class, 'store'])->middleware('api.scope:plans.write');

        Route::middleware('api.scope:subscriptions.write')->group(function () {
            Route::post('subscriptions', [SubscriptionController::class, 'store']);
            Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
            Route::post('subscriptions/{subscription}/change-plan', [SubscriptionController::class, 'changePlan']);
        });

        Route::middleware('api.scope:invoices.write')->group(function () {
            Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void']);
            Route::post('invoices/{invoice}/mark-uncollectible', [InvoiceController::class, 'markUncollectible']);
        });
    });

    // Moving money: the header is mandatory.
    Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])
        ->middleware(['api.scope:payments.write', 'throttle:payments', 'idempotent:required']);
});

// Called by the payment provider; authenticated by signature, not API key.
Route::post('webhooks/psp', PspWebhookController::class)->middleware('throttle:webhooks');

// Readiness for the orchestrator: status only, no business data.
Route::get('health/ready', [OperationsController::class, 'ready']);

// Business metrics are not public: Prometheus scrapes with a Bearer key.
Route::get('metrics', [OperationsController::class, 'metrics'])->middleware(['api.key', 'api.scope:ops.read']);
