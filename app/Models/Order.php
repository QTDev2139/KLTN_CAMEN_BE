<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'code',
        'status',
        'subtotal',
        'discount_total',
        'grand_total',
        'payment_method',
        'payment_status',
        'transaction_code',
        'shipping_address',
        'note',
        'user_id',
        'coupon_id',
    ];

    // casts chuyển đổi kiểu dữ liệu
    protected $casts = [
        'subtotal'       => 'decimal:2',
        'discount_total' => 'decimal:2',
        'grand_total'    => 'decimal:2',
        'shipping_address' => 'array',
    ];

    // n Orders -> 1 User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // n Orders -> 1 Coupon (có thể null)
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    // 1 Order -> n OrderItems
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
}
