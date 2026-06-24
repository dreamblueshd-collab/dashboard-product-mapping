<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public const STATUS_RAW = 'raw';
    public const STATUS_REFINED = 'refined';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'sku',
        'name',
        'raw_description',
        'price',
        'part_category',
        'brand',
        'type',
        'dimension',
        'description',
        'technical_specification',
        'primary_image',
        'additional_images',
        'refine_status',
        'description_status',
        'ai_notes',
        'refined_at',
        'import_batch_id',
    ];

    protected $casts = [
        'additional_images' => 'array',
        'price' => 'decimal:2',
        'refined_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ProductMapping::class);
    }
}
