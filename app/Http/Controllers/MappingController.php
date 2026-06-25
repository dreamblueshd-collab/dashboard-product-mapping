<?php

namespace App\Http\Controllers;

use App\Exports\MappingsExport;
use App\Models\ProductMapping;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MappingController extends Controller
{
    public function index(Request $request): View
    {
        $query = ProductMapping::query()
            ->with(['product', 'vehicle'])
            ->latest('id');

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('vehicle_brand', 'like', "%{$search}%")
                    ->orWhere('vehicle_model', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        return view('mappings.index', [
            'mappings' => $query->paginate(25)->withQueryString(),
            'filters' => ['q' => $search ?? ''],
        ]);
    }

    /**
     * Download Product Mapping (Excel) - format "Konfirmasi Atribut untuk Simulasi".
     */
    public function export(Request $request): BinaryFileResponse
    {
        $filters = ['q' => $request->string('q')->toString()];

        return Excel::download(new MappingsExport($filters), 'product-mapping-'.now()->format('Ymd-His').'.xlsx');
    }
}
