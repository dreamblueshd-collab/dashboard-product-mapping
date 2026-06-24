@extends('layouts.app')
@section('title', 'Kendaraan')

@section('content')
    <div class="mb-4">
        <h1 class="text-2xl font-bold">Kendaraan</h1>
        <p class="text-slate-500 text-sm">Upload Data Vehicle (Excel), lalu lengkapi nama umum &amp; tahun keluaran dengan AI.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <form action="{{ route('vehicles.import') }}" method="POST" enctype="multipart/form-data"
              class="lg:col-span-2 rounded-xl bg-white border border-slate-200 p-4">
            @csrf
            <label class="block text-sm font-medium mb-2">Upload Data Vehicle (.xlsx / .xls / .csv)</label>
            <div class="flex flex-col sm:flex-row gap-2">
                <input type="file" name="file" required accept=".xlsx,.xls,.csv"
                       class="flex-1 text-sm border border-slate-300 rounded-lg px-3 py-2 bg-slate-50">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Upload</button>
            </div>
            <p class="mt-2 text-xs text-slate-400">Sheet pertama (mis. "All Data") dengan 16 kolom berkode.</p>
        </form>

        <div class="rounded-xl bg-white border border-slate-200 p-4 space-y-2">
            <div class="text-sm font-medium">Aksi AI (massal)</div>
            <form action="{{ route('vehicles.refineAll') }}" method="POST" class="flex gap-2">
                @csrf
                <input type="number" name="limit" value="200" min="1" max="5000"
                       class="w-24 text-sm border border-slate-300 rounded-lg px-2 py-2" title="Batas jumlah">
                <button class="flex-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Refine (raw)
                </button>
            </form>
            <p class="text-xs text-slate-400">Diproses via antrian. Jalankan <code>php artisan queue:work</code>.</p>
        </div>
    </div>

    <form method="GET" class="flex flex-wrap gap-2 mb-3">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Cari brand / model / nama umum..."
               class="flex-1 min-w-[200px] text-sm border border-slate-300 rounded-lg px-3 py-2">
        <select name="status" class="text-sm border border-slate-300 rounded-lg px-3 py-2">
            <option value="">Semua status</option>
            @foreach(['raw' => 'Raw', 'refined' => 'Refined', 'failed' => 'Failed'] as $val => $lbl)
                <option value="{{ $val }}" @selected($filters['status'] === $val)>{{ $lbl }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-2 font-medium">Brand / Model</th>
                    <th class="px-4 py-2 font-medium">Tipe</th>
                    <th class="px-4 py-2 font-medium">Transmisi / Mesin</th>
                    <th class="px-4 py-2 font-medium">Nama Umum (AI)</th>
                    <th class="px-4 py-2 font-medium">Tahun (AI)</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($vehicles as $vehicle)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2">
                            <div class="font-medium">{{ $vehicle->brand_description ?? '—' }}</div>
                            <div class="text-xs text-slate-400">{{ $vehicle->model_description }}</div>
                        </td>
                        <td class="px-4 py-2 text-slate-500">{{ $vehicle->type_description ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-500">
                            {{ $vehicle->transmission_description ?? '—' }}
                            <div class="text-xs text-slate-400">{{ $vehicle->machine_volume_description }}</div>
                        </td>
                        <td class="px-4 py-2">{{ $vehicle->common_name ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $vehicle->release_year ?: '—' }}</td>
                        <td class="px-4 py-2">@include('partials.status', ['status' => $vehicle->refine_status])</td>
                        <td class="px-4 py-2 text-right">
                            <form action="{{ route('vehicles.refine', $vehicle) }}" method="POST"
                                  onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='...';">
                                @csrf
                                <button class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100">Refine</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Belum ada kendaraan. Upload Excel di atas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $vehicles->links() }}</div>
@endsection
