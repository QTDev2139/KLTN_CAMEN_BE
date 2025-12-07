<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'rating',
        'comment',
        'images',
        'reply_content',
        'user_id',
        'product_id',
        'order_item_id',
    ];

    protected $casts = [
        'rating' => 'integer',
        'images' => 'array', // Nếu lưu nhiều ảnh dạng JSON
    ];

    // n Reviews -> 1 User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // n Reviews -> 1 Product
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // 1 Reviews -> 1 OrderItem 
    public function orderItem() 
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
