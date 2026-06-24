<?php

namespace App\Http\Controllers;

use App\Models\ProductMapping;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
}
