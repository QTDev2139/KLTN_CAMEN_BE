<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $fillable = [
        'code',
        'name'
    ];

    // 1 Language -> n Post
    public function posts(): HasMany{
        return $this -> hasMany(Post::class, 'language_id');
    }
    // 1 Language -> n CategoryTranslations
    public function categories(): HasMany{
        return $this -> hasMany(CategoryTranslation::class, 'language_id');
    }
    // 1 Language -> n ProductTranslation
    public function product_translations(): HasMany{
        return $this -> hasMany(ProductTranslation::class, 'language_id');
    }
}
