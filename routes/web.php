<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
// Route::get('/product', [ProductController::class, 'index'])->name('products.index');
// Route::get('/product/create', [ProductController::class, 'create'])->name('products.create');

// Trong đó:
// get là phương thức HTTP.
// /products là URL.
// ProductController::class là controller mà ta sẽ dùng.
// 'index' là hàm trong controller sẽ được gọi.
// name('products.index') là tên của route (bạn có thể đặt tên tùy ý).
