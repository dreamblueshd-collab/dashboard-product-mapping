<?php

namespace App\Http\Controllers;

use App\Jobs\MapProductToVehiclesJob;
use App\Models\ImportBatch;
use App\Models\Product;
use App\Services\AiRefinementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Smalot\PdfParser\Parser;
use Throwable;

class CatalogController extends Controller
{
    public function index(): View
    {
        $batches = ImportBatch::where('type', ImportBatch::TYPE_CATALOG)
            ->latest()
            ->get();

        return view('catalog.index', [
            'batches' => $batches,
            'productCount' => Product::count(),
        ]);
    }

    /**
     * Upload katalog PDF: ekstrak teks lalu simpan sebagai import batch.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $file = $request->file('file');
        $batch = ImportBatch::create([
            'type' => ImportBatch::TYPE_CATALOG,
            'original_filename' => $file->getClientOriginalName(),
            'status' => ImportBatch::STATUS_PROCESSING,
        ]);

        try {
            // Simpan PDF asli.
            $pdfPath = $file->store('catalogs');

            // Ekstrak teks dari PDF.
            $parser = new Parser;
            $pdf = $parser->parseFile(Storage::path($pdfPath));
            $text = trim($pdf->getText());

            // Simpan teks hasil ekstraksi.
            $textPath = 'catalog_texts/'.$batch->id.'.txt';
            Storage::put($textPath, $text);

            $batch->update([
                'status' => ImportBatch::STATUS_COMPLETED,
                'stored_path' => $textPath,
                'total_rows' => str_word_count($text),
                'imported_rows' => 1,
                'message' => 'Teks katalog diekstrak ('.number_format(strlen($text)).' karakter). Siap untuk auto-mapping.',
            ]);

            return redirect()->route('catalog.index')
                ->with('success', 'Katalog PDF berhasil diunggah & teks diekstrak. Sekarang jalankan auto-mapping.');
        } catch (Throwable $e) {
            $batch->update([
                'status' => ImportBatch::STATUS_FAILED,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('catalog.index')
                ->with('error', 'Gagal memproses PDF: '.$e->getMessage());
        }
    }

    /**
     * Jalankan auto-mapping: untuk setiap produk, minta AI menentukan kendaraan
     * yang kompatibel berdasarkan konteks katalog ini.
     */
    public function generateMappings(ImportBatch $batch, AiRefinementService $ai): RedirectResponse
    {
        if (! $ai->isConfigured()) {
            return back()->with('error', 'VERTEX_API_KEY belum diisi di .env.');
        }
        if ($batch->type !== ImportBatch::TYPE_CATALOG) {
            return back()->with('error', 'Batch ini bukan katalog.');
        }

        $ids = Product::pluck('id');
        foreach ($ids as $id) {
            MapProductToVehiclesJob::dispatch($id, $batch->id);
        }

        return back()->with('success', "Mengantrekan auto-mapping untuk {$ids->count()} produk. Jalankan 'php artisan queue:work'.");
    }
}
