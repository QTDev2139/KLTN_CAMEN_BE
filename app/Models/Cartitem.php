<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'qty',
        'unit_price',
        'subtotal',
        'cart_id',
        'product_id',
    ];

    protected $table = 'cartitems';

    // 1 CartItem -> n Cart
    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    // 1 CartItem -> n Product
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
