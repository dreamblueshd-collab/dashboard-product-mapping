@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    @unless($aiConfigured)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>VERTEX_API_KEY belum diisi.</strong> Fitur refine AI &amp; auto-mapping akan nonaktif sampai
            Anda mengisi <code>VERTEX_API_KEY</code> di file <code>.env</code>.
        </div>
    @endunless

    <h1 class="text-2xl font-bold mb-1">Dashboard</h1>
    <p class="text-slate-500 mb-6">Ringkasan data produk, kendaraan, dan mapping.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @php($cards = [
            ['Produk', $stats['products'], $stats['products_refined'].' refined', 'indigo', route('products.index')],
            ['Kendaraan', $stats['vehicles'], $stats['vehicles_refined'].' refined', 'sky', route('vehicles.index')],
            ['Product Mapping', $stats['mappings'], 'relasi produk-kendaraan', 'emerald', route('mappings.index')],
            ['Katalog PDF', null, 'unggah & auto-mapping', 'rose', route('catalog.index')],
        ])
        @foreach($cards as [$label, $value, $sub, $color, $url])
            <a href="{{ $url }}" class="block rounded-xl bg-white p-5 shadow-sm border border-slate-200 hover:shadow-md transition">
                <div class="text-sm text-slate-500">{{ $label }}</div>
                <div class="mt-2 text-3xl font-bold text-{{ $color }}-600">
                    {{ $value !== null ? number_format($value) : '—' }}
                </div>
                <div class="mt-1 text-xs text-slate-400">{{ $sub }}</div>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-3">
            <h2 class="font-semibold text-lg">Alur Kerja</h2>
            <ol class="space-y-2 text-sm">
                <li class="rounded-lg bg-white border border-slate-200 p-3"><span class="font-semibold text-indigo-600">1.</span> Upload Excel <a href="{{ route('products.index') }}" class="text-indigo-600 underline">Produk</a> &amp; <a href="{{ route('vehicles.index') }}" class="text-indigo-600 underline">Kendaraan</a>.</li>
                <li class="rounded-lg bg-white border border-slate-200 p-3"><span class="font-semibold text-indigo-600">2.</span> Refine kedua data dengan AI (lengkapi atribut + regenerate deskripsi + nama umum/tahun kendaraan).</li>
                <li class="rounded-lg bg-white border border-slate-200 p-3"><span class="font-semibold text-indigo-600">3.</span> Upload <a href="{{ route('catalog.index') }}" class="text-indigo-600 underline">Katalog PDF</a> lalu jalankan auto-mapping produk &rarr; kendaraan.</li>
                <li class="rounded-lg bg-white border border-slate-200 p-3"><span class="font-semibold text-indigo-600">4.</span> Lihat hasil di <a href="{{ route('mappings.index') }}" class="text-indigo-600 underline">Product Mapping</a>.</li>
            </ol>
        </div>

        <div class="lg:col-span-2">
            <h2 class="font-semibold text-lg mb-3">Riwayat Import Terakhir</h2>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-4 py-2 font-medium">Tipe</th>
                            <th class="px-4 py-2 font-medium">File</th>
                            <th class="px-4 py-2 font-medium">Baris</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($batches as $batch)
                            <tr>
                                <td class="px-4 py-2 capitalize">{{ $batch->type }}</td>
                                <td class="px-4 py-2 max-w-[200px] truncate" title="{{ $batch->original_filename }}">{{ $batch->original_filename }}</td>
                                <td class="px-4 py-2">{{ number_format($batch->imported_rows) }}</td>
                                <td class="px-4 py-2">
                                    @include('partials.status', ['status' => $batch->status])
                                </td>
                                <td class="px-4 py-2 text-slate-400">{{ $batch->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Belum ada import.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
