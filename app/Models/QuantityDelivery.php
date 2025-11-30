<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuantityDelivery extends Model
{
    protected $fillable = [
        'quantity',
        'sent_qty',
        'received_qty',
        'shortage_qty',
        'delivery_id',
        'product_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }
}