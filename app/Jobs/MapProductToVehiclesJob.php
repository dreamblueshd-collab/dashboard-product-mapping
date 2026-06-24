<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\Product;
use App\Services\AiRefinementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class MapProductToVehiclesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(public int $productId, public int $catalogBatchId) {}

    public function handle(AiRefinementService $ai): void
    {
        $product = Product::find($this->productId);
        $batch = ImportBatch::find($this->catalogBatchId);
        if (! $product || ! $batch) {
            return;
        }

        $context = '';
        if ($batch->stored_path && Storage::exists($batch->stored_path)) {
            $context = Storage::get($batch->stored_path);
        }

        $ai->mapProductToVehicles($product, $context, $batch->id);
    }
}
