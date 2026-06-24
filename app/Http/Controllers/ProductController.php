<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Imports\ProductsImport;
use App\Jobs\RefineProductJob;
use App\Jobs\RegenerateProductDescriptionJob;
use App\Models\ImportBatch;
use App\Models\Product;
use App\Services\AiRefinementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->latest('id');

        if ($status = $request->string('status')->toString()) {
            $query->where('refine_status', $status);
        }
        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return view('products.index', [
            'products' => $query->paginate(20)->withQueryString(),
            'filters' => ['status' => $status ?? '', 'q' => $search ?? ''],
        ]);
    }

    public function show(Product $product): View
    {
        $product->load('mappings.vehicle');

        return view('products.show', ['product' => $product]);
    }

    /**
     * Download data produk (Excel) - lengkap, mengikuti filter aktif.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $filters = [
            'status' => $request->string('status')->toString(),
            'q' => $request->string('q')->toString(),
        ];

        return Excel::download(new ProductsExport($filters), 'produk-'.now()->format('Ymd-His').'.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $file = $request->file('file');
        $batch = ImportBatch::create([
            'type' => ImportBatch::TYPE_PRODUCT,
            'original_filename' => $file->getClientOriginalName(),
            'status' => ImportBatch::STATUS_PROCESSING,
        ]);

        try {
            $import = new ProductsImport($batch);
            Excel::import($import, $file);

            $batch->update([
                'status' => ImportBatch::STATUS_COMPLETED,
                'imported_rows' => $import->imported,
                'total_rows' => $import->imported,
                'message' => "Berhasil mengimpor {$import->imported} produk.",
            ]);

            return redirect()->route('products.index')
                ->with('success', "Berhasil mengimpor {$import->imported} produk.");
        } catch (Throwable $e) {
            $batch->update([
                'status' => ImportBatch::STATUS_FAILED,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('products.index')
                ->with('error', 'Gagal mengimpor: '.$e->getMessage());
        }
    }

    /**
     * Refine satu produk secara sinkron (langsung tampil hasilnya).
     */
    public function refine(Product $product, AiRefinementService $ai): RedirectResponse
    {
        if (! $ai->isConfigured()) {
            return back()->with('error', 'VERTEX_API_KEY belum diisi di .env.');
        }

        try {
            $ai->refineProduct($product);

            return back()->with('success', "Produk \"{$product->name}\" berhasil di-refine AI.");
        } catch (Throwable $e) {
            return back()->with('error', 'Refine gagal: '.$e->getMessage());
        }
    }

    /**
     * Regenerate deskripsi satu produk secara sinkron.
     */
    public function regenerateDescription(Product $product, AiRefinementService $ai): RedirectResponse
    {
        if (! $ai->isConfigured()) {
            return back()->with('error', 'VERTEX_API_KEY belum diisi di .env.');
        }

        try {
            $ai->regenerateDescription($product);

            return back()->with('success', "Deskripsi produk \"{$product->name}\" berhasil dibuat ulang.");
        } catch (Throwable $e) {
            return back()->with('error', 'Regenerate deskripsi gagal: '.$e->getMessage());
        }
    }

    /**
     * Refine semua produk yang belum berhasil (status raw atau failed) via queue.
     */
    public function refineAll(AiRefinementService $ai): RedirectResponse
    {
        if (! $ai->isConfigured()) {
            return back()->with('error', 'VERTEX_API_KEY belum diisi di .env.');
        }

        $ids = Product::whereIn('refine_status', [Product::STATUS_RAW, Product::STATUS_FAILED])->pluck('id');
        $delay = (int) config('vertex.bulk_delay_seconds', 5);
        foreach ($ids as $i => $id) {
            RefineProductJob::dispatch($id)->delay(now()->addSeconds($i * $delay));
        }

        return back()->with('success', "Mengantrekan {$ids->count()} produk untuk di-refine AI (jeda {$delay} detik antar proses). Pastikan worker antrian berjalan & sudah di-restart setelah mengisi .env (php artisan queue:restart, lalu php artisan queue:work).");
    }

    /**
     * Regenerate deskripsi semua produk via queue.
     */
    public function regenerateAllDescriptions(AiRefinementService $ai): RedirectResponse
    {
        if (! $ai->isConfigured()) {
            return back()->with('error', 'VERTEX_API_KEY belum diisi di .env.');
        }

        $ids = Product::pluck('id');
        $delay = (int) config('vertex.bulk_delay_seconds', 5);
        foreach ($ids as $i => $id) {
            RegenerateProductDescriptionJob::dispatch($id)->delay(now()->addSeconds($i * $delay));
        }

        return back()->with('success', "Mengantrekan {$ids->count()} deskripsi produk (jeda {$delay} detik antar proses). Pastikan worker antrian berjalan & sudah di-restart setelah mengisi .env (php artisan queue:restart, lalu php artisan queue:work).");
    }
}
