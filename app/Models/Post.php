<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'status',
    ];

    //n Post -> 1 User
    public function user(): BelongsTo {
        return $this -> belongsTo(User::class, 'user_id');
    }

    //1 Post -> n Post_translation
    public function post_translations(): HasMany {
        return $this -> hasMany(Post_translation::class, 'post_id');
    }
}
