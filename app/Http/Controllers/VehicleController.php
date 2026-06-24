<?php

namespace App\Http\Controllers;

use App\Exports\VehiclesExport;
use App\Imports\VehiclesImport;
use App\Jobs\RefineVehicleJob;
use App\Models\ImportBatch;
use App\Models\Vehicle;
use App\Services\AiRefinementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        $query = Vehicle::query()->latest('id');

        if ($status = $request->string('status')->toString()) {
            $query->where('refine_status', $status);
        }
        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('brand_description', 'like', "%{$search}%")
                    ->orWhere('model_description', 'like', "%{$search}%")
                    ->orWhere('common_name', 'like', "%{$search}%");
            });
        }

        return view('vehicles.index', [
            'vehicles' => $query->paginate(20)->withQueryString(),
            'filters' => ['status' => $status ?? '', 'q' => $search ?? ''],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = [
            'status' => $request->string('status')->toString(),
            'q' => $request->string('q')->toString(),
        ];

        return Excel::download(new VehiclesExport($filters), 'kendaraan-'.now()->format('Ymd-His').'.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:51200'],
        ]);

        $file = $request->file('file');
        $batch = ImportBatch::create([
            'type' => ImportBatch::TYPE_VEHICLE,
            'original_filename' => $file->getClientOriginalName(),
            'status' => ImportBatch::STATUS_PROCESSING,
        ]);

        try {
            // Hanya impor sheet pertama (mis. "All Data"); abaikan sheet lookup.
            $import = new VehiclesImport($batch);
            Excel::import($import, $file);
            $imported = $import->importedCount();

            $batch->update([
                'status' => ImportBatch::STATUS_COMPLETED,
                'imported_rows' => $imported,
                'total_rows' => $imported,
                'message' => "Berhasil mengimpor {$imported} kendaraan.",
            ]);

            return redirect()->route('vehicles.index')
                ->with('success', "Berhasil mengimpor {$imported} kendaraan.");
        } catch (Throwable $e) {
            $batch->update([
                'status' => ImportBatch::STATUS_FAILED,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('vehicles.index')
                ->with('error', 'Gagal mengimpor: '.$e->getMessage());
        }
    }

    public function refine(Vehicle $vehicle, AiRefinementService $ai): RedirectResponse
    {
        if (! $ai->isConfigured()) {
            return back()->with('error', 'VERTEX_API_KEY belum diisi di .env.');
        }

        try {
            $ai->refineVehicle($vehicle);

            return back()->with('success', "Kendaraan \"{$vehicle->label()}\" berhasil di-refine AI.");
        } catch (Throwable $e) {
            return back()->with('error', 'Refine gagal: '.$e->getMessage());
        }
    }

    public function refineAll(Request $request, AiRefinementService $ai): RedirectResponse
    {
        if (! $ai->isConfigured()) {
            return back()->with('error', 'VERTEX_API_KEY belum diisi di .env.');
        }

        // Batasi default agar tidak mengantre ribuan baris sekaligus tanpa sengaja.
        $limit = (int) $request->integer('limit', 200);
        $ids = Vehicle::whereIn('refine_status', [Vehicle::STATUS_RAW, Vehicle::STATUS_FAILED])
            ->limit($limit)
            ->pluck('id');

        $delay = (int) config('vertex.bulk_delay_seconds', 5);
        foreach ($ids as $i => $id) {
            RefineVehicleJob::dispatch($id)->delay(now()->addSeconds($i * $delay));
        }

        return back()->with('success', "Mengantrekan {$ids->count()} kendaraan untuk di-refine AI (jeda {$delay} detik antar proses). Pastikan worker antrian berjalan & sudah di-restart setelah mengisi .env (php artisan queue:restart, lalu php artisan queue:work).");
    }
}
