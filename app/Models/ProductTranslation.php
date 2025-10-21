<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTranslation extends Model
{
    protected $fillable = [
        'id',
        'name',
        'slug',
        'description',
        'nutrition_info',
        'usage_instruction',
        'reason_to_choose',
        'product_id',
        'language_id'
    ];

    //n Product_translation -> 1 Languages
    public function language(): BelongsTo{
        return $this -> belongsTo(Language::class, 'language_id');
    } 
    //n Product_translation -> 1 Product
    public function product(): BelongsTo{
        return $this -> belongsTo(Product::class, 'product_id');
    } 
    
}
