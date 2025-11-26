<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    /**
     * Các field có thể gán hàng loạt
     * (phù hợp với migration `posts`)
     */
    protected $fillable = [
        'user_id',
        'post_category_id',
        'thumbnail',
        'status',
    ];

    // n Post -> 1 User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // n Post -> 1 PostCategory (nếu posts có cột post_category_id)
    public function postCategory(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    // 1 Post -> n PostTranslation
    public function postTranslations(): HasMany
    {
        return $this->hasMany(PostTranslation::class, 'post_id');
    }
}
