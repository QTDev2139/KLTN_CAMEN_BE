<?php

use App\Http\Controllers\LanguagesController;
use App\Http\Controllers\PostController;
use ILLuminate\support\Facades\Route;

Route::apiResource('language', LanguagesController::class);
Route::apiResource('posts', PostController::class);

// Trong đó:
// get là phương thức HTTP.
// /language là URL.
// ProductController::class là controller mà ta sẽ dùng.
// 'index' là hàm trong controller sẽ được gọi.
// name('products.index') là tên của route (bạn có thể đặt tên tùy ý).