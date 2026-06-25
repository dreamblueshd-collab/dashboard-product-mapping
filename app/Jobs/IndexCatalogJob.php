<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\CatalogRagService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Index katalog untuk RAG: chunk + embedding seluruh teks katalog.
 * Berjalan di antrian karena bisa melibatkan banyak panggilan embedding.
 */
class IndexCatalogJob implements ShouldQueue
{
    use Queueable;

    // Embedding banyak chunk + jeda antar panggilan -> beri timeout besar.
    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public int $catalogBatchId) {}

    public function handle(CatalogRagService $rag): void
    {
        $batch = ImportBatch::find($this->catalogBatchId);
        if (! $batch) {
            return;
        }

        try {
            $count = $rag->indexCatalog($batch);
            $batch->update([
                'message' => "Index RAG selesai: {$count} chunk + embedding tersimpan.",
            ]);
        } catch (Throwable $e) {
            $batch->update([
                'message' => 'Index RAG gagal: '.$e->getMessage(),
            ]);
            throw $e;
        }
    }
}
