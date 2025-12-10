<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Review;

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
        return $this -> hasMany(CartItem::class, 'product_id'); 
    }

    // 1 Product -> n OrderItems
    public function orderitems(): HasMany {
        return $this -> hasMany(OrderItem::class, 'product_id');
    }

    // 1 Products -> n Reviews
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    public function quantityDeliveries(): HasMany
    {
        return $this->hasMany(QuantityDelivery::class, 'product_id');
    }

    public function quantityImports(): HasMany
    {
        return $this->hasMany(QuantityImport::class, 'product_id');
    }
    // Tính rating trung bình
    public function averageRating()
    {
        return $this->reviews()->avg('rating');
    }

    // // Đếm số lượng review
    // public function reviewsCount()
    // {
    //     return $this->reviews()->count();
    // }
}
