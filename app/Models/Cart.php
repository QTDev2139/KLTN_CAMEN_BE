<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    protected $fillable = [
        'is_active',
    ];

    //1 Cart -> 1 User
    public function user(): BelongsTo {
        return $this -> belongsTo(User::class, 'user_id');
    }
}
