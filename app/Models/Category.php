<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'id',   
    ];

    // 1 Category -> n Products
    public function products(): HasMany{
        return $this -> hasMany(Product::class, 'category_id');
    }
    // 1 Category -> n CategoryTranslation
    public function categoryTranslation(): HasMany{
        return $this -> hasMany(CategoryTranslation::class, 'category_id');
    }
}
