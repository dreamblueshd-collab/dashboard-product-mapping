<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export Produk: data penting, detail, dan lengkap (mentah + hasil refine AI).
 */
class ProductsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * @param  array{status?: string, q?: string}  $filters
     */
    public function __construct(private readonly array $filters = []) {}

    public function query(): Builder
    {
        $query = Product::query()->withCount('mappings')->latest('id');

        if (! empty($this->filters['status'])) {
            $query->where('refine_status', $this->filters['status']);
        }
        if (! empty($this->filters['q'])) {
            $search = $this->filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'SKU (SAP Part Number)',
            'Nama Produk',
            'Brand',
            'Part Category',
            'Type',
            'Dimension',
            'Harga (HET)',
            'Technical Specification',
            'Deskripsi (Bersih)',
            'Deskripsi Mentah (Teks)',
            'Primary Image',
            'Additional Images',
            'Status Refine',
            'Status Deskripsi',
            'Jumlah Mapping Kendaraan',
            'Catatan AI',
            'Terakhir Refine',
        ];
    }

    /**
     * @param  Product  $product
     */
    public function map($product): array
    {
        return [
            $product->id,
            $product->sku,
            $product->name,
            $product->brand,
            $product->part_category,
            $product->type,
            $product->dimension,
            $product->price,
            $product->technical_specification,
            $product->description,
            $this->plainText($product->raw_description),
            $product->primary_image,
            is_array($product->additional_images) ? implode(' | ', $product->additional_images) : null,
            $product->refine_status,
            $product->description_status,
            $product->mappings_count,
            $product->ai_notes,
            $product->refined_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function plainText(?string $html): string
    {
        if (! $html) {
            return '';
        }
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text)), 4000, '');
    }
}
