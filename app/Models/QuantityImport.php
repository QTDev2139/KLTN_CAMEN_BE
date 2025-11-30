<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuantityImport extends Model
{
    protected $fillable = [
        'quantity',
        'product_id',
        'request_import_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function requestImport(): BelongsTo
    {
        return $this->belongsTo(RequestImport::class, 'request_import_id');
    }
}