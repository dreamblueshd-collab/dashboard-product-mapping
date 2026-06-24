@extends('layouts.app')
@section('title', 'Detail Produk')

@section('content')
    <a href="{{ route('products.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Kembali ke daftar produk</a>

    <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">{{ $product->name }}</h1>
            <p class="text-slate-500 text-sm">SKU: {{ $product->sku ?? '—' }} &middot;
                Harga: {{ $product->price ? 'Rp '.number_format($product->price, 0, ',', '.') : '—' }}</p>
        </div>
        <div class="flex gap-2">
            <form action="{{ route('products.refine', $product) }}" method="POST">
                @csrf
                <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Refine atribut (AI)</button>
            </form>
            <form action="{{ route('products.regenerateDescription', $product) }}" method="POST">
                @csrf
                <button class="rounded-lg bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-700">Regenerate deskripsi (AI)</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <div class="rounded-xl border border-slate-200 bg-white p-5 space-y-3">
            <h2 class="font-semibold">Atribut (hasil refine AI)</h2>
            @php($rows = [
                'Status refine' => $product->refine_status,
                'Part Category' => $product->part_category,
                'Brand' => $product->brand,
                'Type' => $product->type,
                'Dimension' => $product->dimension,
                'Technical Spec' => $product->technical_specification,
                'Primary Image' => $product->primary_image,
            ])
            <dl class="divide-y divide-slate-100 text-sm">
                @foreach($rows as $label => $value)
                    <div class="py-2 grid grid-cols-3 gap-2">
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd class="col-span-2 break-words">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
            @if($product->ai_notes)
                <p class="text-xs text-slate-400 border-t border-slate-100 pt-2">Catatan AI: {{ $product->ai_notes }}</p>
            @endif
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-semibold mb-2">Deskripsi bersih
                    <span class="ml-1">@include('partials.status', ['status' => $product->description_status])</span>
                </h2>
                <div class="text-sm whitespace-pre-line text-slate-700">{{ $product->description ?: 'Belum ada. Klik "Regenerate deskripsi (AI)".' }}</div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-semibold mb-2">Deskripsi mentah (asli Excel)</h2>
                <div class="text-xs text-slate-500 max-h-48 overflow-auto border border-slate-100 rounded-lg p-3 bg-slate-50">
                    {{ \Illuminate\Support\Str::limit(strip_tags($product->raw_description ?? ''), 1500) ?: '—' }}
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 mt-6">
        <h2 class="font-semibold mb-3">Mapping kendaraan ({{ $product->mappings->count() }})</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">Tipe</th>
                        <th class="px-3 py-2 font-medium">Brand</th>
                        <th class="px-3 py-2 font-medium">Model</th>
                        <th class="px-3 py-2 font-medium">Tahun</th>
                        <th class="px-3 py-2 font-medium">Transmisi</th>
                        <th class="px-3 py-2 font-medium">Match Master</th>
                        <th class="px-3 py-2 font-medium">Confidence</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($product->mappings as $m)
                        <tr>
                            <td class="px-3 py-2">{{ $m->vehicle_type ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $m->vehicle_brand ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $m->vehicle_model ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $m->year ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $m->transmission ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $m->vehicle?->label() ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $m->confidence !== null ? $m->confidence.'%' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-6 text-center text-slate-400">Belum ada mapping. Jalankan auto-mapping dari menu Katalog PDF.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
