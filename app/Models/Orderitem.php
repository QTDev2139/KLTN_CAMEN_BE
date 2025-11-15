<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{

    protected $table = 'orderitems';

    protected $fillable = [
        'order_id',
        'product_id',
        'qty',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'qty'        => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
    ];

    // n OrderItems -> 1 Order
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // n OrderItems -> 1 Product
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // 1 OrderItem -> 1 Review
    public function review()
    {
        return $this->hasOne(Review::class, 'order_item_id');
    }
}
