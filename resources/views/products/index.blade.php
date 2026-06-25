@extends('layouts.app')
@section('title', 'Produk')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold">Produk</h1>
            <p class="text-slate-500 text-sm">Upload Data Product (Excel), lalu lengkapi atribut dengan AI.</p>
        </div>
        <a href="{{ route('products.export', request()->only('q', 'status')) }}"
           class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
            Download Excel
        </a>
    </div>

    {{-- Upload + aksi batch --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <form action="{{ route('products.import') }}" method="POST" enctype="multipart/form-data"
              class="lg:col-span-2 rounded-xl bg-white border border-slate-200 p-4">
            @csrf
            <label class="block text-sm font-medium mb-2">Upload Data Product (.xlsx / .xls / .csv)</label>
            <div class="flex flex-col sm:flex-row gap-2">
                <input type="file" name="file" required accept=".xlsx,.xls,.csv"
                       class="flex-1 text-sm border border-slate-300 rounded-lg px-3 py-2 bg-slate-50">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Upload
                </button>
            </div>
            <p class="mt-2 text-xs text-slate-400">Kolom: Nama, Deskripsi, SKU, Harga.</p>
        </form>

        <div class="rounded-xl bg-white border border-slate-200 p-4 space-y-2">
            <div class="text-sm font-medium">Aksi AI (massal)</div>
            <form action="{{ route('products.refineAll') }}" method="POST">
                @csrf
                <button class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Refine semua (status raw)
                </button>
            </form>
            <form action="{{ route('products.regenerateAll') }}" method="POST">
                @csrf
                <button class="w-full rounded-lg bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-700">
                    Regenerate semua deskripsi
                </button>
            </form>
            <p class="text-xs text-slate-400">Diproses via antrian. Jalankan <code>php artisan queue:work</code>.</p>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" class="flex flex-wrap gap-2 mb-3">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Cari nama / SKU..."
               class="flex-1 min-w-[200px] text-sm border border-slate-300 rounded-lg px-3 py-2">
        <select name="status" class="text-sm border border-slate-300 rounded-lg px-3 py-2">
            <option value="">Semua status</option>
            @foreach(['raw' => 'Raw', 'refined' => 'Refined', 'failed' => 'Failed'] as $val => $lbl)
                <option value="{{ $val }}" @selected($filters['status'] === $val)>{{ $lbl }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white">Filter</button>
    </form>

    {{-- Tabel produk --}}
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-2 font-medium">Produk</th>
                    <th class="px-4 py-2 font-medium">SKU</th>
                    <th class="px-4 py-2 font-medium">Kategori / Brand</th>
                    <th class="px-4 py-2 font-medium">Harga</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($products as $product)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 max-w-[280px]">
                            <a href="{{ route('products.show', $product) }}" class="font-medium text-indigo-600 hover:underline line-clamp-2">
                                {{ $product->name }}
                            </a>
                        </td>
                        <td class="px-4 py-2 text-slate-500">{{ $product->sku ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-500">
                            {{ $product->part_category ?? '—' }}
                            <div class="text-xs text-slate-400">{{ $product->brand }}</div>
                        </td>
                        <td class="px-4 py-2">{{ $product->price ? 'Rp '.number_format($product->price, 0, ',', '.') : '—' }}</td>
                        <td class="px-4 py-2 space-y-1">
                            @include('partials.status', ['status' => $product->refine_status])
                            <div class="text-xs text-slate-400">desc: {{ $product->description_status }}</div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex justify-end gap-1">
                                <form action="{{ route('products.refine', $product) }}" method="POST"
                                      onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='...';">
                                    @csrf
                                    <button class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100" title="Lengkapi atribut via AI">
                                        Refine
                                    </button>
                                </form>
                                <form action="{{ route('products.regenerateDescription', $product) }}" method="POST"
                                      onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='...';">
                                    @csrf
                                    <button class="rounded-md bg-sky-50 px-2 py-1 text-xs font-medium text-sky-700 hover:bg-sky-100" title="Regenerate deskripsi via AI">
                                        Deskripsi
                                    </button>
                                </form>
                                <a href="{{ route('products.show', $product) }}" class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">Detail</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Belum ada produk. Upload Excel di atas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection
