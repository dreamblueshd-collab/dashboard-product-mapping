<?php

namespace App\Imports;

use App\Models\ImportBatch;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Importer Data Vehicle.xlsx.
 *
 * File referensi memiliki banyak sheet (All Data + sheet-sheet lookup seperti
 * Group Code, Brand Code, Variant Code, dll). Hanya SHEET PERTAMA (index 0,
 * yaitu "All Data") yang diimpor sebagai data kendaraan; sheet lookup diabaikan.
 */
class VehiclesImport implements WithMultipleSheets
{
    public VehicleSheetImport $sheet;

    public function __construct(ImportBatch $batch)
    {
        $this->sheet = new VehicleSheetImport($batch);
    }

    /**
     * Hanya proses sheet pertama.
     */
    public function sheets(): array
    {
        return [
            0 => $this->sheet,
        ];
    }

    /**
     * Jumlah baris kendaraan yang berhasil diimpor.
     */
    public function importedCount(): int
    {
        return $this->sheet->imported;
    }
}
