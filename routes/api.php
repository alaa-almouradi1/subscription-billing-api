<?php

use App\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.key')->group(function () {
    Route::get('plans', [PlanController::class, 'index']);
    Route::post('plans', [PlanController::class, 'store']);
    Route::get('plans/{plan}', [PlanController::class, 'show']);
});
