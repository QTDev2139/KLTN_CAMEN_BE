<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use ILLuminate\support\Facades\Route;

Route::apiResource('posts', PostController::class)->only(['index', 'show']);
// private (yêu cầu JWT)
Route::apiResource('posts', PostController::class)
    ->only(['store', 'update', 'destroy'])
    ->middleware('auth:api');

Route::get('post/slug/{slug}', [PostController::class, 'getKey']);
Route::get('post', [PostController::class, 'showByLangAndKey']);
// Trong đó:
// get là phương thức HTTP.
// posts/lang/{code}/key/{key} là URL.
// ProductController::class là controller mà ta sẽ dùng.
// 'showByLangAndKey' là hàm trong controller sẽ được gọi.



Route::group([

    'middleware' => 'api',
    'prefix' => 'auth'

], function ($router) {

    Route::post('register/request-otp', [AuthController::class, 'requestOtp']);
    Route::post('register/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('profile', [AuthController::class, 'profile']);
});

