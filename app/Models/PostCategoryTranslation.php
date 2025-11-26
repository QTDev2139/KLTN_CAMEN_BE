<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostCategoryTranslation extends Model
{
    protected $fillable = [
        'id',
        'name',
        'slug',
        'language_id',
        'post_category_id'
    ];
    //n CategoryTranslation -> 1 Languages
    public function language(): BelongsTo{
        return $this -> belongsTo(Language::class, 'language_id');
    } 
    //n CategoryTranslation -> 1 Category
    public function postCategory(): BelongsTo{
        return $this -> belongsTo(PostCategory::class, 'post_category_id');
    } 
}
