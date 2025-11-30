<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'delivery_number',
        'date',
        'note',
        'status',
        'user_id',
        'request_import_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function quantityDeliveries(): HasMany
    {
        return $this->hasMany(QuantityDelivery::class, 'delivery_id');
    }
    
    public function requestImport(): BelongsTo
    {
        return $this->belongsTo(RequestImport::class, 'request_import_id');
    }

}
