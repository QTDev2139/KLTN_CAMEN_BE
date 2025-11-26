<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTranslation extends Model
{
    /**
     * Các field có thể gán hàng loạt
     * (phù hợp với migration `post_translations`)
     */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'language_id', // theo migration tên cột là 
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
