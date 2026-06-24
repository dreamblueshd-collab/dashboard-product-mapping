<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    public const STATUS_RAW = 'raw';
    public const STATUS_REFINED = 'refined';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'group_code',
        'group_description',
        'type_code',
        'type_description',
        'brand_code',
        'brand_description',
        'sub_brand_code',
        'sub_brand_description',
        'variant_code',
        'model_description',
        'transmission_code',
        'transmission_description',
        'machine_type_code',
        'machine_type_description',
        'machine_volume_code',
        'machine_volume_description',
        'common_name',
        'release_year',
        'refine_status',
        'ai_notes',
        'refined_at',
        'import_batch_id',
    ];

    protected $casts = [
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

    /**
     * Label ringkas kendaraan untuk ditampilkan / dipakai prompt AI.
     */
    public function label(): string
    {
        return collect([
            $this->brand_description,
            $this->model_description,
            $this->variant_code,
            $this->transmission_description,
        ])->filter()->implode(' ');
    }
}
