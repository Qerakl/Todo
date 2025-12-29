<?php

use App\Http\Controllers\Api\Auth\UserController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [UserController::class, 'register']);
Route::post('/auth/login', [UserController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'me']);
    Route::post('/auth/logout', [UserController::class, 'logout']);

    Route::apiResource('tasks', TaskController::class)->only([
        'index', 'store', 'show', 'update', 'destroy'
    ]);
});

