<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'id',
        'is_active',
        'price',
        'compare_at_price',
        'stock_quantity',
        'origin',
        'quantity_per_pack',
        'shipping_from',
        'category_id',
        'type'

    ];

    //n Products  -> 1 Category
    public function category(): BelongsTo{
        return $this -> belongsTo(Category::class, 'category_id');
    } 
    // 1 Product -> n ProductTranslation
    public function product_translations(): HasMany{
        return $this -> hasMany(ProductTranslation::class, 'product_id');
    }
    // 1 Product -> n ProductImage
    public function product_images(): HasMany{
        return $this -> hasMany(ProductImage::class, 'product_id');
    }

    // 1 Product -> n CartItems
    public function cartitems(): HasMany {
        return $this -> hasMany(Cartitem::class, 'product_id'); 
    }

    // 1 Product -> n OrderItems
    public function orderitems(): HasMany {
        return $this -> hasMany(OrderItem::class, 'product_id');
    }
}
