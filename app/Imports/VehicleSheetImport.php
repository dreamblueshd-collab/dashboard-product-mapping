<?php

namespace App\Imports;

use App\Models\ImportBatch;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/**
 * Importer untuk SATU sheet data kendaraan (sheet "All Data").
 *
 * Pemetaan berdasarkan POSISI kolom (header sumber punya label ganda):
 *   0  group_code                 8  variant_code
 *   1  group_description          9  model_description
 *   2  type_code                 10  transmission_code
 *   3  type_description          11  transmission_description
 *   4  brand_code                12  machine_type_code
 *   5  brand_description         13  machine_type_description
 *   6  sub_brand_code            14  machine_volume_code
 *   7  sub_brand_description     15  machine_volume_description
 */
class VehicleSheetImport implements ToCollection, WithChunkReading
{
    public int $imported = 0;

    public function __construct(private readonly ImportBatch $batch) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $groupCode = trim((string) ($row[0] ?? ''));

            if ($groupCode === '' || strcasecmp($groupCode, 'Group Code') === 0) {
                continue;
            }

            Vehicle::create([
                'group_code' => $this->s($row[0] ?? null),
                'group_description' => $this->s($row[1] ?? null),
                'type_code' => $this->s($row[2] ?? null),
                'type_description' => $this->s($row[3] ?? null),
                'brand_code' => $this->s($row[4] ?? null),
                'brand_description' => $this->s($row[5] ?? null),
                'sub_brand_code' => $this->s($row[6] ?? null),
                'sub_brand_description' => $this->s($row[7] ?? null),
                'variant_code' => $this->s($row[8] ?? null),
                'model_description' => $this->s($row[9] ?? null),
                'transmission_code' => $this->s($row[10] ?? null),
                'transmission_description' => $this->s($row[11] ?? null),
                'machine_type_code' => $this->s($row[12] ?? null),
                'machine_type_description' => $this->s($row[13] ?? null),
                'machine_volume_code' => $this->s($row[14] ?? null),
                'machine_volume_description' => $this->s($row[15] ?? null),
                'refine_status' => Vehicle::STATUS_RAW,
                'import_batch_id' => $this->batch->id,
            ]);
            $this->imported++;
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }

    private function s(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));

        return $v === '' ? null : $v;
    }
}
