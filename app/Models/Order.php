<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'code',
        'status',
        'subtotal',
        'discount_total',
        'grand_total',
        'payment_method',
        'shipping_address',
        'note',
    ];
}
