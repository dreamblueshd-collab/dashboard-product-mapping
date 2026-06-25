<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogChunk extends Model
{
    protected $fillable = [
        'import_batch_id',
        'chunk_index',
        'content',
        'embedding',
        'dimensions',
        'word_count',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
