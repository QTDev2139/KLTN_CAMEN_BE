<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTranslation extends Model
{
  
    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_description',
        'language_id', 
        'post_id',
    ];

    // n PostTranslation -> 1 Post
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    // n PostTranslation -> 1 Language
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'language_id');
    }
}
