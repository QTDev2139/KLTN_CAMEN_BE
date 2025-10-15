<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| status code:
| 200–299: thành công → FE xử lý trong try
| >=400: thất bại → FE xử lý trong catch
|
*/

// ====================
// 📰 PUBLIC ROUTES (Không cần đăng nhập)
// ====================

Route::prefix('posts')->group(function () {
    Route::get('/', [PostController::class, 'index']);           // Danh sách bài viết
    Route::get('/{id}', [PostController::class, 'show']);        // Chi tiết bài viết theo ID
    Route::get('/slug/{slug}', [PostController::class, 'getKey']); // Lấy bài viết theo slug
    Route::get('/lang/{code}/key/{key}', [PostController::class, 'showByLangAndKey']); // Bài viết theo ngôn ngữ + key
});

// ====================
// 🔐 AUTH ROUTES
// ====================

Route::group([
    'prefix' => 'auth',
    'middleware' => 'api',
], function () {
    // Đăng ký
    Route::post('register/request-otp', [AuthController::class, 'requestOtpForRegister']);
    Route::post('register/verify-otp', [AuthController::class, 'verifyOtpForRegister']);
    Route::post('register/resend-otp', [AuthController::class, 'resendOtpForRegister']);

    // Quên mật khẩu
    Route::post('forgotten-password/request-otp', [AuthController::class, 'requestOtpForForgottenPassword']);
    Route::post('forgotten-password/verify-otp', [AuthController::class, 'verifyOtpForForgottenPassword']);
    Route::post('forgotten-password/resend-otp', [AuthController::class, 'resendOtpForForgottenPassword']);
    Route::post('forgotten-password/reset-password', [AuthController::class, 'resetPassword']);

    // Đổi mật khẩu sau khi đăng nhập
    Route::post('change-password', [AuthController::class, 'changePassword']);

    // Đăng nhập / Đăng xuất / Refresh token
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    // Lấy thông tin người dùng hiện tại
    Route::get('profile', [AuthController::class, 'profile']);
});

// ====================
// 👨‍💼 PRIVATE ROUTES (Yêu cầu JWT đăng nhập)
// ====================

Route::middleware('auth:api')->group(function () {

    // CRUD bài viết (chỉ admin / nhân viên)
    Route::apiResource('posts', PostController::class)
        ->only(['store', 'update', 'destroy']);

    // Quản lý nhân viên / người dùng
    Route::apiResource('users', UserController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy', 'updateRole']);
    Route::post('/update-role/{role}', [UserController::class, 'updateRole']);
});
