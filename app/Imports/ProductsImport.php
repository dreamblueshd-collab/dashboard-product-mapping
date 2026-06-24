<?php

namespace App\Imports;

use App\Models\ImportBatch;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/**
 * Importer Data Product.xlsx.
 * Kolom (berdasarkan posisi): 0=Nama, 1=Deskripsi(HTML), 2=SKU, 3=Harga.
 */
class ProductsImport implements ToCollection, WithChunkReading
{
    public int $imported = 0;

    public function __construct(private readonly ImportBatch $batch) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            // Lewati baris header.
            $first = trim((string) ($row[0] ?? ''));
            if ($first === '' && trim((string) ($row[2] ?? '')) === '') {
                continue;
            }
            if ($this->looksLikeHeader($first, (string) ($row[2] ?? ''))) {
                continue;
            }

            $name = trim((string) ($row[0] ?? ''));
            if ($name === '') {
                continue;
            }

            Product::create([
                'name' => $name,
                'raw_description' => $row[1] ?? null,
                'sku' => trim((string) ($row[2] ?? '')) ?: null,
                'price' => $this->parsePrice($row[3] ?? null),
                'refine_status' => Product::STATUS_RAW,
                'description_status' => Product::STATUS_RAW,
                'import_batch_id' => $this->batch->id,
            ]);
            $this->imported++;
        }
    }

    public function chunkSize(): int
    {
        return 200;
    }

    private function looksLikeHeader(string $col0, string $col2): bool
    {
        return strcasecmp($col0, 'Nama') === 0
            || strcasecmp($col2, 'SKU') === 0;
    }

    private function parsePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $clean = preg_replace('/[^0-9]/', '', (string) $value);

        return $clean === '' ? null : (float) $clean;
    }
}
