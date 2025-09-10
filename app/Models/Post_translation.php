<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post_translation extends Model
{
    protected $fillable = [
        'post_id',
        'languages_id',
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'thumbnail',
    ];

    //n Post_translation -> 1 Post
    public function post(): BelongsTo{
        return $this -> belongsTo(Post::class, 'post_id');
    }

    //n Post_translation -> 1 Languages
    public function language(): BelongsTo{
        return $this -> belongsTo(Language::class, 'languages_id');
    } 
}
