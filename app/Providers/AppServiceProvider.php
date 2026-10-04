<?php

namespace App\Providers;

use App\Domain\Payments\PaymentGateway;
use App\Infrastructure\Payments\FakePaymentGateway;
use App\Outbox\EventPublisher;
use App\Outbox\Publishers\InMemoryEventPublisher;
use App\Outbox\Publishers\KafkaEventPublisher;
use App\Outbox\Publishers\LogEventPublisher;
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

        $this->app->singleton(EventPublisher::class, fn () => match (config('billing.events.driver')) {
            'kafka' => new KafkaEventPublisher(
                config('billing.events.kafka.brokers'),
                config('billing.events.kafka.topic'),
            ),
            'log' => new LogEventPublisher,
            'memory' => new InMemoryEventPublisher,
            default => throw new InvalidArgumentException('Unsupported events driver ['.config('billing.events.driver').'].'),
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
