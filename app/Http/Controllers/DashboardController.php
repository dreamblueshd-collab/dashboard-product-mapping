<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\Vehicle;
use App\Services\VertexAiService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(VertexAiService $vertex): View
    {
        $stats = [
            'products' => Product::count(),
            'products_refined' => Product::where('refine_status', Product::STATUS_REFINED)->count(),
            'vehicles' => Vehicle::count(),
            'vehicles_refined' => Vehicle::where('refine_status', Vehicle::STATUS_REFINED)->count(),
            'mappings' => ProductMapping::count(),
        ];

        $batches = ImportBatch::latest()->limit(10)->get();

        return view('dashboard', [
            'stats' => $stats,
            'batches' => $batches,
            'aiConfigured' => $vertex->isConfigured(),
        ]);
    }
}
