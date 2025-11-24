<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PostCategoryController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
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
    Route::get('/{lang}', [PostController::class, 'index']);           // Danh sách bài viết
    Route::get('/{id}', [PostController::class, 'show']);        // Chi tiết bài viết theo ID
});
Route::prefix('product')->group(function () {
    Route::get('/{lang}/type/{type}', [ProductController::class, 'index']);                 // Danh sách sản phẩm
    Route::get('/slug/{slug}/lang/{lang}', [ProductController::class, 'showProductByCategory']);           // Danh sách sản phẩm theo category
    Route::get('/id/{id}', [ProductController::class, 'showProductById']);           // Danh sách sản phẩm theo id
    Route::get('/{slug}/lang/{lang}', [ProductController::class, 'show']);           // Chi tiết sản phẩm
    Route::put('/{id}', [ProductController::class, 'update']);                      // Cập nhật sản phẩm
    Route::post('/', [ProductController::class, 'store']);                      // Tạo sản phẩm
    Route::delete('/{id}', [ProductController::class, 'destroy']);
    Route::get('/sales-count', [ProductController::class, 'getSalesCount']);           // Chi tiết sản phẩm
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
Route::prefix('coupon')->group(function () {
    Route::get('/', [CouponController::class, 'index']);
    Route::get('/active-coupons', [CouponController::class, 'getActiveCoupons']);
    Route::get('/{id}', [CouponController::class, 'show']);
    Route::post('/', [CouponController::class, 'store']);
    Route::put('/status/{id}', [CouponController::class, 'updateStatus']);
    Route::put('/active/{id}', [CouponController::class, 'updateActive']);
    Route::delete('/{id}', [CouponController::class, 'destroy']);
});

Route::prefix('post-categories')->group(function () {
    Route::get('/{lang}', [PostCategoryController::class, 'index']);                // Danh sách sản phẩm
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
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('/update-role/{role}', [UserController::class, 'updateRole']);
    Route::get('/dsnv/customer', [UserController::class, 'getDsnv']);

    // Quản lý đơn hàng
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/user/lang/{lang}', [OrderController::class, 'userOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

    Route::prefix('payment')->group(function () {
        Route::post('/vnpay', [PaymentController::class, 'vnpay_payment']);
        Route::get('/vnpay/status/{order_id}', [PaymentController::class, 'vnpay_status']);
    });
    
    Route::prefix('review')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
        Route::get('/', [ReviewController::class, 'index']);
        Route::delete('/{id}', [ReviewController::class, 'destroy']);
    });

    // Quản lý danh mục bài viết
    Route::apiResource('post-categories', PostCategoryController::class)
        ->only(['store', 'update', 'destroy']);
    
});
Route::prefix('payment')->group(function () {
    Route::get('/vnpay/callback', [PaymentController::class, 'vnpay_callback']);
});



use App\Http\Controllers\ChatRoomController;
use App\Http\Controllers\ChatMessageController;

Route::middleware('auth:api')->group(function () {
    Route::post('chat/rooms/open', [ChatRoomController::class, 'openRoom']);

    // Danh sách phòng chat của user hiện tại
    Route::get('chat/rooms', [ChatRoomController::class, 'index']);

    // Mở / tạo phòng chat giữa customer & staff
    Route::post('chat/rooms/{roomId}/join', [ChatRoomController::class, 'joinRoom']);

    // Xem thông tin 1 room
    Route::get('chat/rooms/{room}', [ChatRoomController::class, 'show']);

    // Lấy tin nhắn trong room
    Route::get('chat/rooms/{room}/messages', [ChatMessageController::class, 'index']);

    // Gửi tin nhắn
    Route::post('chat/rooms/{room}/messages', [ChatMessageController::class, 'store']);

    // Đánh dấu đã đọc (optional)
    Route::post('chat/rooms/{room}/read', [ChatMessageController::class, 'markAsRead']);
});