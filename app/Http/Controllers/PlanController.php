<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(
            Plan::query()->where('active', true)->orderBy('amount')->get(),
        );
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = Plan::create($request->validated());

        return PlanResource::make($plan)->response()->setStatusCode(201);
    }

    public function show(Plan $plan): PlanResource
    {
        return PlanResource::make($plan);
    }
}
