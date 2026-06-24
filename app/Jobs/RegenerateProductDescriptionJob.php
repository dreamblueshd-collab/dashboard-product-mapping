<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\AiRefinementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RegenerateProductDescriptionJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;
    public int $tries = 2;

    public function __construct(public int $productId) {}

    public function handle(AiRefinementService $ai): void
    {
        $product = Product::find($this->productId);
        if (! $product) {
            return;
        }

        $ai->regenerateDescription($product);
    }
}
