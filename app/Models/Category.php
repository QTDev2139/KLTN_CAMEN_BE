<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'id',
        'name',
        'slug',
        'languages_id',
    ];

    //n Categories -> 1 Languages
    public function language(): BelongsTo{
        return $this -> belongsTo(Language::class, 'languages_id');
    } 
    // 1 Category -> n Products
    public function products(): HasMany{
        return $this -> hasMany(Product::class, 'category_id');
    }
}
