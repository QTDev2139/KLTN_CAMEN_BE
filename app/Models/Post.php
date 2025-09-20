<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'languages_id',
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'thumbnail',
        'translation_key',
        'status',
    ];

    //n Post -> 1 User
    public function user(): BelongsTo {
        return $this -> belongsTo(User::class, 'user_id');
    }

    //n Post -> 1 Languages
    public function language(): BelongsTo{
        return $this -> belongsTo(Language::class, 'languages_id');
    } 
}
