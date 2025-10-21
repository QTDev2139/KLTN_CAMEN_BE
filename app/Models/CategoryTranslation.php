<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryTranslation extends Model
{
    protected $fillable = [
        'id',
        'name',
        'slug',
        'language_id',
        'category_id'
    ];
    //n CategoryTranslation -> 1 Languages
    public function language(): BelongsTo{
        return $this -> belongsTo(Language::class, 'language_id');
    } 
    //n CategoryTranslation -> 1 Category
    public function category(): BelongsTo{
        return $this -> belongsTo(Category::class, 'category_id');
    } 
    
}
