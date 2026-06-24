<?php

namespace App\Services;

use App\Models\CatalogChunk;
use App\Models\ImportBatch;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * RAG untuk katalog produk:
 *  - indexCatalog(): pecah teks katalog menjadi chunk, buat embedding (Gemini), simpan ke DB.
 *  - buildContextForProduct(): retrieval (cosine) + rerank (LLM) untuk menyusun konteks
 *    paling relevan bagi sebuah produk, dipakai pada auto-mapping.
 *
 * Catatan: MySQL/MariaDB 10.x belum punya tipe VECTOR, sehingga cosine similarity
 * dihitung di PHP atas embedding yang disimpan sebagai JSON. Untuk katalog skala
 * puluhan halaman (ratusan chunk) ini cepat dan memadai.
 */
class CatalogRagService
{
    public function __construct(private readonly VertexAiService $vertex) {}

    public function isConfigured(): bool
    {
        return $this->vertex->isConfigured();
    }

    /**
     * Index ulang sebuah katalog: hapus chunk lama, chunk + embed teks, simpan.
     *
     * @return int Jumlah chunk yang dibuat.
     */
    public function indexCatalog(ImportBatch $batch): int
    {
        $text = '';
        if ($batch->stored_path && Storage::exists($batch->stored_path)) {
            $text = (string) Storage::get($batch->stored_path);
        }
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        // Bersihkan chunk lama agar idempotent.
        CatalogChunk::where('import_batch_id', $batch->id)->delete();

        $chunks = $this->chunkText($text);
        $delayMs = (int) config('vertex.embedding.delay_ms', 250);
        $created = 0;

        foreach ($chunks as $i => $content) {
            $embedding = $this->vertex->embed($content, 'RETRIEVAL_DOCUMENT');

            CatalogChunk::create([
                'import_batch_id' => $batch->id,
                'chunk_index' => $i,
                'content' => $content,
                'embedding' => $embedding,
                'dimensions' => count($embedding),
                'word_count' => str_word_count($content),
            ]);
            $created++;

            // Rate limiting antar panggilan embedding.
            if ($delayMs > 0 && $i < count($chunks) - 1) {
                usleep($delayMs * 1000);
            }
        }

        return $created;
    }

    /**
     * Susun konteks relevan untuk sebuah produk.
     * Jika katalog sudah di-index (punya chunk), gunakan RAG (retrieval + rerank).
     * Jika belum, fallback ke potongan teks katalog penuh.
     */
    public function buildContextForProduct(ImportBatch $batch, Product $product): string
    {
        $hasChunks = CatalogChunk::where('import_batch_id', $batch->id)->exists();

        if (! $hasChunks) {
            return $this->fallbackContext($batch);
        }

        $query = $this->productQuery($product);
        $topK = (int) config('vertex.rag.top_k', 12);
        $topN = (int) config('vertex.rag.top_n', 5);

        $candidates = $this->retrieve($batch, $query, $topK);
        if ($candidates === []) {
            return $this->fallbackContext($batch);
        }

        if (config('vertex.rag.rerank_enabled')) {
            $candidates = $this->rerank($query, $candidates, $topN);
        } else {
            $candidates = array_slice($candidates, 0, $topN);
        }

        return collect($candidates)
            ->map(fn (array $c) => '- '.trim($c['content']))
            ->implode("\n\n");
    }

    /**
     * Retrieval: embed query, hitung cosine ke semua chunk katalog, ambil top-K.
     *
     * @return array<int, array{content: string, score: float, index: int}>
     */
    public function retrieve(ImportBatch $batch, string $query, int $topK): array
    {
        $queryVec = $this->vertex->embed($query, 'RETRIEVAL_QUERY');

        $scored = [];
        CatalogChunk::where('import_batch_id', $batch->id)
            ->orderBy('chunk_index')
            ->chunk(200, function ($rows) use (&$scored, $queryVec) {
                foreach ($rows as $row) {
                    $vec = $row->embedding;
                    if (! is_array($vec) || $vec === []) {
                        continue;
                    }
                    $scored[] = [
                        'content' => $row->content,
                        'index' => $row->chunk_index,
                        'score' => $this->cosineSimilarity($queryVec, $vec),
                    ];
                }
            });

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $topK));
    }

    /**
     * Rerank kandidat memakai LLM Gemini (mengembalikan urutan indeks paling relevan).
     *
     * @param  array<int, array{content: string, score: float, index: int}>  $candidates
     * @return array<int, array{content: string, score: float, index: int}>
     */
    public function rerank(string $query, array $candidates, int $topN): array
    {
        $list = collect($candidates)
            ->values()
            ->map(fn (array $c, int $i) => "[{$i}] ".Str::limit(str_replace("\n", ' ', $c['content']), 500, ''))
            ->implode("\n");

        $prompt = <<<PROMPT
        Anda adalah sistem reranker. Diberikan QUERY dan daftar potongan dokumen (masing-masing
        diawali indeks dalam kurung siku). Urutkan potongan dari yang PALING relevan terhadap query.

        QUERY:
        {$query}

        POTONGAN:
        {$list}

        Kembalikan HANYA JSON valid: {"order": [indeks_terurut_dari_paling_relevan]}.
        Sertakan maksimal {$topN} indeks teratas.
        PROMPT;

        try {
            $data = $this->vertex->generateJson($prompt);
            $order = $data['order'] ?? [];
            if (! is_array($order) || $order === []) {
                return array_slice($candidates, 0, $topN);
            }

            $result = [];
            foreach ($order as $idx) {
                $idx = (int) $idx;
                if (isset($candidates[$idx])) {
                    $result[] = $candidates[$idx];
                }
                if (count($result) >= $topN) {
                    break;
                }
            }

            return $result !== [] ? $result : array_slice($candidates, 0, $topN);
        } catch (\Throwable) {
            // Bila rerank gagal, pakai urutan cosine.
            return array_slice($candidates, 0, $topN);
        }
    }

    /**
     * Cosine similarity dua vektor.
     *
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $len = min(count($a), count($b));
        if ($len === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * Pecah teks menjadi chunk berbasis jumlah kata, dengan overlap.
     *
     * @return array<int, string>
     */
    public function chunkText(string $text): array
    {
        $size = max(50, (int) config('vertex.rag.chunk_words', 220));
        $overlap = max(0, min($size - 1, (int) config('vertex.rag.chunk_overlap_words', 40)));

        // Normalisasi whitespace, pertahankan baris baru sebagai spasi.
        $normalized = preg_replace('/[ \t]+/u', ' ', $text);
        $words = preg_split('/\s+/u', trim($normalized)) ?: [];
        $words = array_values(array_filter($words, fn ($w) => $w !== ''));

        if ($words === []) {
            return [];
        }

        $chunks = [];
        $step = max(1, $size - $overlap);
        for ($start = 0; $start < count($words); $start += $step) {
            $slice = array_slice($words, $start, $size);
            if ($slice === []) {
                break;
            }
            $chunks[] = implode(' ', $slice);
            if ($start + $size >= count($words)) {
                break;
            }
        }

        return $chunks;
    }

    /**
     * Bangun query dari atribut produk untuk retrieval.
     */
    private function productQuery(Product $product): string
    {
        $parts = [
            $product->name,
            $product->sku,
            $product->dimension,
            $product->technical_specification,
            $this->plainText($product->raw_description),
        ];

        return Str::limit(trim(implode('. ', array_filter($parts))), 1500, '');
    }

    private function fallbackContext(ImportBatch $batch): string
    {
        if ($batch->stored_path && Storage::exists($batch->stored_path)) {
            return Str::limit((string) Storage::get($batch->stored_path), 12000, '');
        }

        return '';
    }

    private function plainText(?string $html): string
    {
        if (! $html) {
            return '';
        }
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
