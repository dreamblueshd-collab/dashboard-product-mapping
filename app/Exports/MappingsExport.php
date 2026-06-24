<?php

namespace App\Exports;

use App\Models\ProductMapping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export Product Mapping mengikuti format "Konfirmasi Atribut untuk Simulasi.xlsx":
 * gabungan atribut produk + aplikasi kendaraan.
 */
class MappingsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private int $row = 0;

    /**
     * @param  array{q?: string}  $filters
     */
    public function __construct(private readonly array $filters = []) {}

    public function query(): Builder
    {
        $query = ProductMapping::query()->with(['product', 'vehicle'])->latest('id');

        if (! empty($this->filters['q'])) {
            $search = $this->filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('vehicle_brand', 'like', "%{$search}%")
                    ->orWhere('vehicle_model', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'No',
            'SAP Part Number',
            'Product Name',
            'Part Category (Tire, Lubricants, Battery, etc*)',
            'Brand (Aspira, Federal, Incoe, GS Astra, etc*)',
            'Type (Bearing, Electrical, Filter, Gasket, etc*)',
            'Dimension',
            'Product Description (Long Desc)',
            'Technical Specification',
            'Primary Image',
            'Additional Images (Multiple Angles)',
            'Product Price (HET)',
            'ID Vehicle',
            'Vehicle Type (Motorcycle, Passenger Car, Truck, etc*)',
            'Vehicle Brand (Honda, Suzuki, Kawasaki, Yamaha, Piaggio, etc*)',
            'Vehicle Model (Beat, Vario, RX King, etc*)',
            'Year',
            'Transmission (AT, MT for cars) (Carburator, Injection, dll for Motorcycle)',
        ];
    }

    /**
     * @param  ProductMapping  $m
     */
    public function map($m): array
    {
        $product = $m->product;
        $description = $product?->description ?: $this->plainText($product?->raw_description);

        return [
            ++$this->row,
            $product?->sku,
            $product?->name,
            $product?->part_category,
            $product?->brand,
            $product?->type,
            $product?->dimension,
            $description,
            $product?->technical_specification,
            $product?->primary_image,
            is_array($product?->additional_images) ? implode(' | ', $product->additional_images) : null,
            $product?->price,
            $m->vehicle_id,
            $m->vehicle_type,
            $m->vehicle_brand,
            $m->vehicle_model,
            $m->year,
            $m->transmission,
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
