<?php

namespace App\Providers;

use App\Auth\ApiClient;
use App\Auth\ApiClientRegistry;
use App\Domain\Payments\PaymentGateway;
use App\Infrastructure\Payments\FakePaymentGateway;
use App\Outbox\EventPublisher;
use App\Outbox\Publishers\InMemoryEventPublisher;
use App\Outbox\Publishers\KafkaEventPublisher;
use App\Outbox\Publishers\LogEventPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ApiClientRegistry::class, fn () => new ApiClientRegistry(
            array_values(array_filter((array) config('billing.api_clients'), 'is_array')),
        ));

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

        $this->configureRateLimits();
    }

    private function configureRateLimits(): void
    {
        $tooMany = fn (Request $request, array $headers) => response()->json([
            'error' => ['code' => 'rate_limited', 'message' => 'Too many requests; retry after the Retry-After delay.'],
        ], 429, $headers);

        RateLimiter::for('api', function (Request $request) use ($tooMany) {
            $client = $request->attributes->get('api_client');

            return Limit::perMinute((int) config('billing.rate_limits.per_client'))
                ->by('client:'.($client instanceof ApiClient ? $client->name : $request->ip()))
                ->response($tooMany);
        });

        RateLimiter::for('payments', function (Request $request) use ($tooMany) {
            $invoice = $request->route('invoice');
            $invoiceId = $invoice instanceof Model ? (string) $invoice->getKey() : (string) $invoice;

            return Limit::perMinute((int) config('billing.rate_limits.payments_per_invoice'))
                ->by('pay:'.$invoiceId)
                ->response($tooMany);
        });

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute((int) config('billing.rate_limits.webhooks_per_ip'))
            ->by('webhook:'.$request->ip())
            ->response($tooMany));
    }
}
