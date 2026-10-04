<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelSubscriptionRequest;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $subscription = $this->subscriptions->subscribe(
            Customer::findOrFail($request->validated('customer_id')),
            Plan::findOrFail($request->validated('plan_id')),
        );

        return SubscriptionResource::make($subscription->load(['plan', 'latestInvoice']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Subscription $subscription): SubscriptionResource
    {
        return SubscriptionResource::make($subscription->load(['plan', 'latestInvoice']));
    }

    public function cancel(CancelSubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        $subscription = $this->subscriptions->cancel(
            $subscription,
            atPeriodEnd: $request->boolean('at_period_end', true),
        );

        return SubscriptionResource::make($subscription->load(['plan', 'latestInvoice']));
    }
}
