<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'default_payment_method' => $request->validated('payment_method'),
        ]);

        return CustomerResource::make($customer)->response()->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        return CustomerResource::make($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->fill(array_filter([
            'name' => $request->validated('name'),
            'default_payment_method' => $request->validated('payment_method'),
        ], fn ($value) => $value !== null))->save();

        return CustomerResource::make($customer);
    }
}
