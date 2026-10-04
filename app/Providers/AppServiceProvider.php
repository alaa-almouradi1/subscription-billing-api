<?php

namespace App\Providers;

use App\Domain\Payments\PaymentGateway;
use App\Infrastructure\Payments\FakePaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, fn () => match (config('billing.payments.gateway')) {
            'fake' => new FakePaymentGateway,
            default => throw new InvalidArgumentException('Unsupported payment gateway ['.config('billing.payments.gateway').'].'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mutable dates are a classic source of billing bugs
        // ($end = $start->addMonth() silently changes $start too).
        Date::use(CarbonImmutable::class);
    }
}
