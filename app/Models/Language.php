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

    // 1 Language -> n Post_translations
    public function post_translations(): HasMany{
        return $this -> hasMany(Post_translation::class, 'languages_id');
    }
}
