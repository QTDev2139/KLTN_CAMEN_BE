<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductController;
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
Route::prefix('product')->group(function () {
    Route::get('/{lang}', [ProductController::class, 'index']);                 // Danh sách sản phẩm
    Route::get('/slug/{slug}/lang/{lang}', [ProductController::class, 'showProductByCategory']);           // Danh sách sản phẩm theo category
    Route::get('/id/{id}', [ProductController::class, 'showProductById']);           // Danh sách sản phẩm theo id
    Route::get('/{slug}/lang/{lang}', [ProductController::class, 'show']);           // Chi tiết sản phẩm
    Route::put('/{id}', [ProductController::class, 'update']);                      // Cập nhật sản phẩm
    Route::post('/', [ProductController::class, 'store']);                      // Tạo sản phẩm
    Route::delete('/{id}', [ProductController::class, 'destroy']);              // Xóa sản phẩm
});
Route::prefix('category')->group(function () {
    Route::get('/{lang}', [CategoryController::class, 'index']);                // Danh sách sản phẩm
});
Route::prefix('cart')->group(function () {
    Route::get('/{lang}', [CartController::class, 'index']);              
    Route::post('/', [CartController::class, 'store']);              
    Route::put('/{id}', [CartController::class, 'update']);              
    Route::delete('/{id}', [CartController::class, 'destroy']);              
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
