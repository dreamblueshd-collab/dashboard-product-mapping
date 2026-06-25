<?php

namespace App\Services;

use App\Models\CatalogChunk;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\Vehicle;
use Illuminate\Support\Str;
use Throwable;

/**
 * Asisten AI (RAG chat) untuk menjawab pertanyaan pengguna tentang katalog,
 * produk, kendaraan, dan mapping.
 *
 * Bersifat ADITIF: hanya memanggil method publik yang sudah ada
 * (VertexAiService, CatalogRagService) tanpa mengubah perilakunya, sehingga
 * tidak berdampak ke fitur lain (refine, auto-mapping, pencarian semantik).
 */
class AssistantService
{
    public function __construct(
        private readonly VertexAiService $vertex,
        private readonly CatalogRagService $rag,
    ) {}

    /**
     * Asisten siap dipakai bila generateContent (Gemini) terkonfigurasi.
     */
    public function isConfigured(): bool
    {
        return $this->vertex->isConfigured();
    }

    /**
     * Jawab pertanyaan pengguna dengan konteks gabungan (katalog RAG + data terstruktur).
     *
     * @param  array<int, array{role: string, content: string}>  $history  Riwayat percakapan (untuk multi-turn).
     * @return array{answer: string, sources: array<string, mixed>}
     */
    public function ask(string $question, array $history = []): array
    {
        $keywords = $this->keywords($question);

        // --- Konteks data terstruktur (LIKE) ---
        $products = $this->searchProducts($keywords);
        $vehicles = $this->searchVehicles($keywords);
        $mappings = $this->searchMappings($keywords);

        // --- Konteks katalog via RAG (opsional, tidak fatal bila gagal) ---
        $catalog = [];
        $catalogNote = null;
        if ($this->rag->isConfigured() && CatalogChunk::query()->exists()) {
            try {
                $topK = (int) config('vertex.rag.top_k', 12);
                $topN = (int) config('vertex.rag.top_n', 5);
                $candidates = $this->rag->search($question, null, $topK);

                if ($candidates !== [] && config('vertex.rag.rerank_enabled')) {
                    $candidates = $this->rag->rerank($question, $candidates, $topN);
                } else {
                    $candidates = array_slice($candidates, 0, $topN);
                }
                $catalog = $candidates;
            } catch (Throwable $e) {
                $catalogNote = 'Konteks katalog dilewati: '.$e->getMessage();
            }
        }

        $prompt = $this->buildPrompt($question, $history, $products, $vehicles, $mappings, $catalog);

        $answer = $this->vertex->generateText($prompt);

        return [
            'answer' => $answer,
            'sources' => [
                'catalog' => collect($catalog)->map(fn ($c) => [
                    'batch_name' => $c['batch_name'] ?? null,
                    'chunk_index' => $c['chunk_index'] ?? null,
                    'score' => isset($c['score']) ? round((float) $c['score'], 4) : null,
                    'excerpt' => Str::limit($c['content'] ?? '', 200),
                ])->all(),
                'products_count' => count($products),
                'vehicles_count' => count($vehicles),
                'mappings_count' => count($mappings),
                'note' => $catalogNote,
            ],
        ];
    }

    /**
     * Ekstrak kata kunci sederhana dari pertanyaan.
     *
     * @return array<int, string>
     */
    private function keywords(string $question): array
    {
        $clean = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $question));
        $words = preg_split('/\s+/u', trim($clean)) ?: [];

        $stop = ['yang', 'untuk', 'apa', 'saja', 'dari', 'dan', 'atau', 'bisa', 'dengan',
            'adalah', 'itu', 'ini', 'pada', 'kah', 'apakah', 'tolong', 'mohon', 'berapa',
            'gimana', 'bagaimana', 'dimana', 'kapan', 'siapa', 'kenapa', 'mengapa', 'the', 'and'];

        $words = array_filter($words, fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, $stop, true));

        return array_slice(array_values(array_unique($words)), 0, 12);
    }

    /**
     * @param  array<int, string>  $kw
     * @return array<int, string>
     */
    private function searchProducts(array $kw): array
    {
        if ($kw === []) {
            return [];
        }

        $rows = Product::query()
            ->where(function ($q) use ($kw) {
                foreach ($kw as $k) {
                    $q->orWhere('name', 'like', "%{$k}%")
                        ->orWhere('sku', 'like', "%{$k}%")
                        ->orWhere('brand', 'like', "%{$k}%")
                        ->orWhere('part_category', 'like', "%{$k}%")
                        ->orWhere('type', 'like', "%{$k}%");
                }
            })
            ->limit(10)
            ->get();

        return $rows->map(function (Product $p) {
            $price = $p->price ? 'Rp '.number_format((float) $p->price, 0, ',', '.') : '-';

            return "Produk: {$p->name} | SKU: ".($p->sku ?? '-')
                .' | brand: '.($p->brand ?? '-')
                .' | kategori: '.($p->part_category ?? '-')
                .' | dimensi: '.($p->dimension ?? '-')
                .' | spesifikasi: '.Str::limit((string) $p->technical_specification, 160)
                ." | harga: {$price}";
        })->all();
    }

    /**
     * @param  array<int, string>  $kw
     * @return array<int, string>
     */
    private function searchVehicles(array $kw): array
    {
        if ($kw === []) {
            return [];
        }

        $rows = Vehicle::query()
            ->where(function ($q) use ($kw) {
                foreach ($kw as $k) {
                    $q->orWhere('brand_description', 'like', "%{$k}%")
                        ->orWhere('model_description', 'like', "%{$k}%")
                        ->orWhere('common_name', 'like', "%{$k}%")
                        ->orWhere('type_description', 'like', "%{$k}%");
                }
            })
            ->limit(10)
            ->get();

        return $rows->map(function (Vehicle $v) {
            return 'Kendaraan: '.trim(($v->brand_description ?? '').' '.($v->model_description ?? ''))
                .' | nama umum: '.($v->common_name ?? '-')
                .' | tipe: '.($v->type_description ?? '-')
                .' | transmisi: '.($v->transmission_description ?? '-')
                .' | tahun: '.($v->release_year ?? '-');
        })->all();
    }

    /**
     * @param  array<int, string>  $kw
     * @return array<int, string>
     */
    private function searchMappings(array $kw): array
    {
        if ($kw === []) {
            return [];
        }

        $rows = ProductMapping::query()
            ->with('product:id,name,sku')
            ->where(function ($q) use ($kw) {
                foreach ($kw as $k) {
                    $q->orWhere('vehicle_brand', 'like', "%{$k}%")
                        ->orWhere('vehicle_model', 'like', "%{$k}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$k}%")->orWhere('sku', 'like', "%{$k}%"));
                }
            })
            ->limit(15)
            ->get();

        return $rows->map(function (ProductMapping $m) {
            $veh = trim(($m->vehicle_brand ?? '').' '.($m->vehicle_model ?? ''));

            return 'Mapping: '.($m->product?->name ?? 'Produk #'.$m->product_id)
                ." cocok untuk {$veh}"
                .' ('.($m->year ?? '-').', '.($m->transmission ?? '-').')';
        })->all();
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<int, string>  $products
     * @param  array<int, string>  $vehicles
     * @param  array<int, string>  $mappings
     * @param  array<int, array<string, mixed>>  $catalog
     */
    private function buildPrompt(string $question, array $history, array $products, array $vehicles, array $mappings, array $catalog): string
    {
        $catalogText = collect($catalog)
            ->map(fn ($c) => '- '.trim((string) ($c['content'] ?? '')))
            ->implode("\n");

        $productText = $products === [] ? '(tidak ada)' : implode("\n", array_map(fn ($s) => '- '.$s, $products));
        $vehicleText = $vehicles === [] ? '(tidak ada)' : implode("\n", array_map(fn ($s) => '- '.$s, $vehicles));
        $mappingText = $mappings === [] ? '(tidak ada)' : implode("\n", array_map(fn ($s) => '- '.$s, $mappings));
        $catalogText = $catalogText === '' ? '(tidak ada)' : $catalogText;

        $historyText = '';
        if ($history !== []) {
            $historyText = "RIWAYAT PERCAKAPAN (untuk konteks lanjutan):\n";
            foreach ($history as $h) {
                $role = ($h['role'] ?? '') === 'assistant' ? 'Asisten' : 'Pengguna';
                $historyText .= $role.': '.Str::limit((string) ($h['content'] ?? ''), 500)."\n";
            }
            $historyText .= "\n";
        }

        return <<<PROMPT
        Anda adalah asisten AI untuk dashboard data spare part otomotif. Jawab pertanyaan pengguna
        dalam Bahasa Indonesia secara ringkas, jelas, dan akurat. Utamakan informasi dari KONTEKS
        di bawah (katalog, produk, kendaraan, mapping). Bila informasi tidak ada pada konteks,
        katakan terus terang bahwa data tidak tersedia dan jangan mengarang. Bila relevan,
        sebutkan produk/kendaraan/mapping spesifik.

        {$historyText}KONTEKS - POTONGAN KATALOG (RAG):
        {$catalogText}

        KONTEKS - DATA PRODUK:
        {$productText}

        KONTEKS - DATA KENDARAAN:
        {$vehicleText}

        KONTEKS - DATA PRODUCT MAPPING (produk -> kendaraan kompatibel):
        {$mappingText}

        PERTANYAAN PENGGUNA:
        {$question}

        Jawaban:
        PROMPT;
    }
}
