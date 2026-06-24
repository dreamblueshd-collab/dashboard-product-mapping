<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMapping extends Model
{
    public const SOURCE_EXCEL = 'excel';
    public const SOURCE_AI_CATALOG = 'ai_catalog';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'product_id',
        'vehicle_id',
        'vehicle_type',
        'vehicle_brand',
        'vehicle_model',
        'year',
        'transmission',
        'source',
        'confidence',
        'notes',
        'import_batch_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
