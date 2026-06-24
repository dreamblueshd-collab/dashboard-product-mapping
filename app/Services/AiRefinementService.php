<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Logika domain refinement berbasis AI: membangun prompt, memanggil Vertex AI,
 * lalu menyimpan hasil ke database.
 */
class AiRefinementService
{
    public function __construct(private readonly VertexAiService $vertex) {}

    public function isConfigured(): bool
    {
        return $this->vertex->isConfigured();
    }

    /**
     * Lengkapi atribut produk (part category, brand, type, dimension,
     * technical specification, gambar) berdasarkan data mentah + Google Search.
     */
    public function refineProduct(Product $product): Product
    {
        $rawDescription = $this->plainText($product->raw_description);

        $prompt = <<<PROMPT
        Anda adalah asisten data katalog spare part otomotif. Lengkapi dan rapikan atribut produk berikut.
        Gunakan Google Search untuk mencari informasi resmi bila atribut tidak tersedia pada data mentah.

        DATA MENTAH PRODUK:
        - Nama: {$product->name}
        - SKU / Part Number: {$product->sku}
        - Harga: {$product->price}
        - Deskripsi (teks): {$rawDescription}

        TUGAS:
        Kembalikan HANYA objek JSON valid (tanpa penjelasan, tanpa markdown) dengan struktur:
        {
          "part_category": "kategori part, contoh: Battery, Tire, Lubricants, Filter",
          "brand": "merek produk, contoh: GS Astra, Aspira, Federal",
          "type": "tipe/jenis part, contoh: Battery, Electrical, Bearing",
          "dimension": "dimensi fisik bila ada, contoh: 'P 197 mm, L 129 mm, T 203 mm', jika tidak ada kosongkan",
          "technical_specification": "spesifikasi teknis ringkas, contoh: 'Tegangan: 12 V; Kapasitas: 7 Ah'",
          "primary_image": "URL gambar utama bila ditemukan, jika tidak ada kosongkan",
          "additional_images": ["URL gambar tambahan", "..."],
          "notes": "catatan singkat sumber/asumsi"
        }
        Jika sebuah field tidak diketahui, isi dengan string kosong atau array kosong. Jangan mengarang URL gambar.
        PROMPT;

        try {
            $data = $this->vertex->generateJson($prompt);

            $product->fill([
                'part_category' => $this->str($data['part_category'] ?? null) ?: $product->part_category,
                'brand' => $this->str($data['brand'] ?? null) ?: $product->brand,
                'type' => $this->str($data['type'] ?? null) ?: $product->type,
                'dimension' => $this->str($data['dimension'] ?? null) ?: $product->dimension,
                'technical_specification' => $this->str($data['technical_specification'] ?? null) ?: $product->technical_specification,
                'primary_image' => $this->str($data['primary_image'] ?? null) ?: $product->primary_image,
                'additional_images' => $this->arr($data['additional_images'] ?? null) ?: $product->additional_images,
                'ai_notes' => $this->str($data['notes'] ?? null),
                'refine_status' => Product::STATUS_REFINED,
                'refined_at' => Carbon::now(),
            ]);
            $product->save();
        } catch (Throwable $e) {
            $product->update([
                'refine_status' => Product::STATUS_FAILED,
                'ai_notes' => 'Refine gagal: '.$e->getMessage(),
            ]);
            throw $e;
        }

        return $product;
    }

    /**
     * Regenerate deskripsi produk menjadi teks bersih & rapi (tanpa HTML berantakan),
     * memanfaatkan Google Search untuk akurasi.
     */
    public function regenerateDescription(Product $product): Product
    {
        $rawDescription = $this->plainText($product->raw_description);

        $prompt = <<<PROMPT
        Anda adalah copywriter katalog spare part otomotif. Tulis ulang deskripsi produk berikut menjadi
        deskripsi yang bersih, rapi, informatif, dan profesional dalam Bahasa Indonesia. Gunakan Google Search
        untuk memverifikasi spesifikasi bila perlu. Hilangkan tag HTML, promo, dan kalimat tidak relevan.

        DATA PRODUK:
        - Nama: {$product->name}
        - SKU: {$product->sku}
        - Deskripsi mentah: {$rawDescription}

        TUGAS:
        Kembalikan HANYA objek JSON valid:
        {
          "description": "deskripsi bersih 1-3 paragraf, boleh memakai poin '- ' antar baris",
          "notes": "catatan singkat"
        }
        PROMPT;

        try {
            $data = $this->vertex->generateJson($prompt);
            $description = $this->str($data['description'] ?? null);

            $product->fill([
                'description' => $description ?: $product->description,
                'description_status' => $description ? Product::STATUS_REFINED : Product::STATUS_FAILED,
                'ai_notes' => $this->str($data['notes'] ?? null) ?: $product->ai_notes,
            ]);
            $product->save();
        } catch (Throwable $e) {
            $product->update([
                'description_status' => Product::STATUS_FAILED,
                'ai_notes' => 'Regenerate deskripsi gagal: '.$e->getMessage(),
            ]);
            throw $e;
        }

        return $product;
    }

    /**
     * Lengkapi atribut kendaraan: nama umum kendaraan & tahun keluaran (via Google Search).
     */
    public function refineVehicle(Vehicle $vehicle): Vehicle
    {
        $label = $vehicle->label();

        $prompt = <<<PROMPT
        Anda adalah pakar data kendaraan bermotor di Indonesia. Tentukan "nama umum kendaraan" dan
        "tahun keluaran" untuk kendaraan berikut. Gunakan Google Search untuk akurasi.

        DATA KENDARAAN:
        - Group: {$vehicle->group_description}
        - Type: {$vehicle->type_description}
        - Brand: {$vehicle->brand_description}
        - Sub Brand: {$vehicle->sub_brand_description}
        - Model: {$vehicle->model_description}
        - Variant Code: {$vehicle->variant_code}
        - Transmisi: {$vehicle->transmission_description}
        - Mesin: {$vehicle->machine_type_description} {$vehicle->machine_volume_description}
        - Ringkasan: {$label}

        TUGAS:
        Kembalikan HANYA objek JSON valid:
        {
          "common_name": "nama umum yang dikenal publik, contoh: 'Toyota Avanza 1.3 G', 'Honda BeAT eSP'",
          "release_year": "rentang/tahun keluaran, contoh: '2016-sekarang' atau '2012'",
          "notes": "catatan singkat sumber/asumsi"
        }
        PROMPT;

        try {
            $data = $this->vertex->generateJson($prompt);

            $vehicle->fill([
                'common_name' => $this->str($data['common_name'] ?? null) ?: $vehicle->common_name,
                'release_year' => $this->str($data['release_year'] ?? null) ?: $vehicle->release_year,
                'ai_notes' => $this->str($data['notes'] ?? null),
                'refine_status' => Vehicle::STATUS_REFINED,
                'refined_at' => Carbon::now(),
            ]);
            $vehicle->save();
        } catch (Throwable $e) {
            $vehicle->update([
                'refine_status' => Vehicle::STATUS_FAILED,
                'ai_notes' => 'Refine gagal: '.$e->getMessage(),
            ]);
            throw $e;
        }

        return $vehicle;
    }

    /**
     * Tentukan kendaraan yang kompatibel dengan sebuah produk berdasarkan konteks katalog
     * dan simpan ke product_mappings.
     *
     * @return int  Jumlah mapping yang dibuat.
     */
    public function mapProductToVehicles(Product $product, string $catalogContext, ?int $importBatchId = null): int
    {
        $rawDescription = $this->plainText($product->raw_description);
        $catalogContext = Str::limit($catalogContext, 12000, '');

        $prompt = <<<PROMPT
        Anda adalah teknisi otomotif. Berdasarkan informasi produk dan kutipan katalog produk berikut,
        tentukan kendaraan apa saja (mobil/motor/truk) yang kompatibel dengan produk ini. Gunakan Google
        Search bila perlu untuk melengkapi aplikasi kendaraan resmi.

        PRODUK:
        - Nama: {$product->name}
        - SKU: {$product->sku}
        - Deskripsi: {$rawDescription}

        KUTIPAN KATALOG (untuk konteks):
        {$catalogContext}

        TUGAS:
        Kembalikan HANYA objek JSON valid dengan struktur:
        {
          "vehicles": [
            {
              "vehicle_type": "Motorcycle | Passenger Car | Truck | dll",
              "vehicle_brand": "Honda | Yamaha | Toyota | dll",
              "vehicle_model": "model spesifik, contoh: 'BeAT FI', 'Avanza 1.3'",
              "year": "tahun/rentang bila tahu, boleh kosong",
              "transmission": "AT/MT/Injection/Carburator bila tahu, boleh kosong",
              "confidence": 0-100
            }
          ]
        }
        Jika tidak ada kendaraan yang relevan, kembalikan {"vehicles": []}.
        PROMPT;

        $data = $this->vertex->generateJson($prompt);
        $vehicles = $data['vehicles'] ?? [];
        if (! is_array($vehicles)) {
            return 0;
        }

        $count = 0;
        foreach ($vehicles as $v) {
            if (! is_array($v)) {
                continue;
            }
            $brand = $this->str($v['vehicle_brand'] ?? null);
            $model = $this->str($v['vehicle_model'] ?? null);
            if ($brand === '' && $model === '') {
                continue;
            }

            $matchedVehicleId = $this->matchVehicleId($brand, $model);

            ProductMapping::create([
                'product_id' => $product->id,
                'vehicle_id' => $matchedVehicleId,
                'vehicle_type' => $this->str($v['vehicle_type'] ?? null),
                'vehicle_brand' => $brand,
                'vehicle_model' => $model,
                'year' => $this->str($v['year'] ?? null),
                'transmission' => $this->str($v['transmission'] ?? null),
                'source' => ProductMapping::SOURCE_AI_CATALOG,
                'confidence' => $this->intOrNull($v['confidence'] ?? null),
                'import_batch_id' => $importBatchId,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Coba mencocokkan teks brand+model ke master vehicle yang ada.
     */
    private function matchVehicleId(string $brand, string $model): ?int
    {
        if ($model === '') {
            return null;
        }

        $query = Vehicle::query();
        if ($brand !== '') {
            $query->where('brand_description', 'like', '%'.$brand.'%');
        }

        // Cocokkan beberapa kata pertama model untuk meningkatkan peluang match.
        $modelKey = Str::of($model)->upper()->trim();
        $vehicle = (clone $query)
            ->where('model_description', 'like', '%'.$modelKey.'%')
            ->first();

        if (! $vehicle) {
            $firstWord = Str::of($model)->trim()->explode(' ')->first();
            if ($firstWord) {
                $vehicle = (clone $query)
                    ->where('model_description', 'like', '%'.$firstWord.'%')
                    ->first();
            }
        }

        return $vehicle?->id;
    }

    private function str(mixed $value): string
    {
        if (is_array($value)) {
            return trim(implode(', ', array_filter($value, 'is_scalar')));
        }

        return trim((string) ($value ?? ''));
    }

    /**
     * @return array<int, string>
     */
    private function arr(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $value)));
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(100, (int) $value));
    }

    /**
     * Bersihkan HTML menjadi teks polos.
     */
    private function plainText(?string $html): string
    {
        if (! $html) {
            return '';
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return Str::limit(trim($text), 4000, '');
    }
}
