<?php

namespace App\Exports;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export Kendaraan: SEMUA kolom database kecuali
 * refined_at, import_batch_id, created_at, updated_at.
 */
class VehiclesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * @param  array{status?: string, q?: string}  $filters
     */
    public function __construct(private readonly array $filters = []) {}

    public function query(): Builder
    {
        $query = Vehicle::query()->latest('id');

        if (! empty($this->filters['status'])) {
            $query->where('refine_status', $this->filters['status']);
        }
        if (! empty($this->filters['q'])) {
            $search = $this->filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('brand_description', 'like', "%{$search}%")
                    ->orWhere('model_description', 'like', "%{$search}%")
                    ->orWhere('common_name', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Group Code',
            'Group Description',
            'Type Code',
            'Type Description',
            'Brand Code',
            'Brand Description',
            'Sub Brand Code',
            'Sub Brand Description',
            'Variant Code',
            'Model Description',
            'Transmission Code',
            'Transmission Description',
            'Machine Type Code',
            'Machine Type Description',
            'Machine Volume Code',
            'Machine Volume Description',
            'Nama Umum Kendaraan',
            'Tahun Keluaran',
            'Status Refine',
            'Catatan AI',
        ];
    }

    /**
     * @param  Vehicle  $vehicle
     */
    public function map($vehicle): array
    {
        return [
            $vehicle->id,
            $vehicle->group_code,
            $vehicle->group_description,
            $vehicle->type_code,
            $vehicle->type_description,
            $vehicle->brand_code,
            $vehicle->brand_description,
            $vehicle->sub_brand_code,
            $vehicle->sub_brand_description,
            $vehicle->variant_code,
            $vehicle->model_description,
            $vehicle->transmission_code,
            $vehicle->transmission_description,
            $vehicle->machine_type_code,
            $vehicle->machine_type_description,
            $vehicle->machine_volume_code,
            $vehicle->machine_volume_description,
            $vehicle->common_name,
            $vehicle->release_year,
            $vehicle->refine_status,
            $vehicle->ai_notes,
        ];
    }
}
