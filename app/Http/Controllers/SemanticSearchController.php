<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Services\CatalogRagService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SemanticSearchController extends Controller
{
    public function index(Request $request, CatalogRagService $rag): View
    {
        $query = trim((string) $request->string('q'));
        $batchId = $request->integer('catalog') ?: null;
        $topK = (int) ($request->integer('top') ?: 15);

        $results = [];
        $error = null;
        $ran = false;

        // Daftar katalog yang sudah di-index (punya chunk).
        $catalogs = ImportBatch::where('type', ImportBatch::TYPE_CATALOG)
            ->withCount('chunks')
            ->having('chunks_count', '>', 0)
            ->latest()
            ->get();

        if ($query !== '') {
            $ran = true;
            if (! $rag->isConfigured()) {
                $error = 'Embedding belum dikonfigurasi (lihat .env: VERTEX_EMBED_*).';
            } elseif ($catalogs->isEmpty()) {
                $error = 'Belum ada katalog yang di-index. Buka menu Katalog PDF lalu klik "Index RAG".';
            } else {
                try {
                    $results = $rag->search($query, $batchId, $topK);
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return view('search.index', [
            'query' => $query,
            'batchId' => $batchId,
            'topK' => $topK,
            'catalogs' => $catalogs,
            'results' => $results,
            'error' => $error,
            'ran' => $ran,
        ]);
    }
}
