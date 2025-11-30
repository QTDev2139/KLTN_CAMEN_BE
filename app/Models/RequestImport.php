<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestImport extends Model
{
    protected $fillable = [
        'date',
        'status',
        'note',
        'has_shortage',
        'user_id',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function quantityImports(): HasMany
    {
        return $this->hasMany(QuantityImport::class, 'request_import_id');
    }
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'request_import_id');
    }
}
