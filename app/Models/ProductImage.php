<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'id',
        'image_url',
        'sort_order',
        'product_id'
    ];

    //n Product_images  -> 1 Product
    public function product(): BelongsTo{
        return $this -> belongsTo(Product::class, 'product_id');
    }
}
