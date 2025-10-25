<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    protected $fillable = [
        'is_active',
        'user_id',
    ];

    //1 Cart -> 1 User
    public function user(): BelongsTo {
        return $this -> belongsTo(User::class, 'user_id');
    }

    // 1 Cart -> n CartItems
    public function cartitems() {
        return $this -> hasMany(Cartitem::class, 'cart_id');
    }
}
