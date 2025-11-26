<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostCategory extends Model
{
    protected $fillable = [
        'id',   
    ];

    // 1 PostCategory -> n Posts
    public function posts(): HasMany{
        return $this -> hasMany(Post::class, 'post_category_id');
    }
    // 1 PostCategory -> n PostCategoryTranslations
    public function postCategoryTranslations(): HasMany{
        return $this -> hasMany(PostCategoryTranslation::class, 'post_category_id');
    }
}
