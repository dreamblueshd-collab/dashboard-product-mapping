@extends('layouts.app')
@section('title', 'Product Mapping')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold">Product Mapping</h1>
            <p class="text-slate-500 text-sm">Relasi produk &rarr; kendaraan yang kompatibel (hasil auto-mapping AI dari katalog).</p>
        </div>
        <a href="{{ route('mappings.export', request()->only('q')) }}"
           class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
            Download Excel
        </a>
    </div>

    <form method="GET" class="flex flex-wrap gap-2 mb-3">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Cari produk / brand / model kendaraan..."
               class="flex-1 min-w-[200px] text-sm border border-slate-300 rounded-lg px-3 py-2">
        <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white">Cari</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-2 font-medium">Produk</th>
                    <th class="px-4 py-2 font-medium">Tipe</th>
                    <th class="px-4 py-2 font-medium">Brand</th>
                    <th class="px-4 py-2 font-medium">Model</th>
                    <th class="px-4 py-2 font-medium">Tahun</th>
                    <th class="px-4 py-2 font-medium">Transmisi</th>
                    <th class="px-4 py-2 font-medium">Sumber</th>
                    <th class="px-4 py-2 font-medium">Conf.</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($mappings as $m)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 max-w-[240px]">
                            <a href="{{ $m->product ? route('products.show', $m->product) : '#' }}" class="text-indigo-600 hover:underline line-clamp-2">
                                {{ $m->product?->name ?? '—' }}
                            </a>
                        </td>
                        <td class="px-4 py-2">{{ $m->vehicle_type ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $m->vehicle_brand ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $m->vehicle_model ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $m->year ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $m->transmission ?: '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $m->source }}</span>
                        </td>
                        <td class="px-4 py-2">{{ $m->confidence !== null ? $m->confidence.'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">Belum ada mapping. Upload katalog PDF lalu jalankan auto-mapping.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $mappings->links() }}</div>
@endsection
