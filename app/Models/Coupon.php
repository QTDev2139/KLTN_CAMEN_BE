<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'usage_limit',
        'used_count',
        'start_date',
        'end_date',
        'state',
        'is_active',
        'user_id',
    ];

    protected $casts = [
        'discount_value'   => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'used_count'       => 'integer',
        'usage_limit'      => 'integer',
        'is_active'        => 'boolean',
        'start_date'       => 'datetime',
        'end_date'         => 'datetime',
    ];

    // 1 Coupon -> n Orders
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'coupon_id');
    }
    // n Coupon -> 1 User
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }
}
