<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    public const TYPE_PRODUCT = 'product';
    public const TYPE_VEHICLE = 'vehicle';
    public const TYPE_CATALOG = 'catalog';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type',
        'original_filename',
        'stored_path',
        'status',
        'total_rows',
        'imported_rows',
        'message',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(CatalogChunk::class, 'import_batch_id');
    }
}
