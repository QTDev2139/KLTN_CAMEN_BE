<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orderitem extends Model
{
    protected $fillable = [
        'qty',
        'unit_price',
        'subtotal',
    ];
}
