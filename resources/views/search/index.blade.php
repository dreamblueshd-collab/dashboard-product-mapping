@extends('layouts.app')
@section('title', 'Pencarian Semantik')

@section('content')
    <div class="mb-4">
        <h1 class="text-2xl font-bold">Pencarian Semantik</h1>
        <p class="text-slate-500 text-sm">Cari isi katalog berdasarkan <em>makna</em> (bukan sekadar kata), memanfaatkan embedding vektor hasil Index RAG.</p>
    </div>

    <form method="GET" class="rounded-xl bg-white border border-slate-200 p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-2">
            <input type="text" name="q" value="{{ $query }}" placeholder="mis. aki untuk Honda BeAT, aki mobil Avanza, kapasitas 7 Ah..."
                   class="flex-1 text-sm border border-slate-300 rounded-lg px-3 py-2" autofocus>
            <select name="catalog" class="text-sm border border-slate-300 rounded-lg px-3 py-2">
                <option value="">Semua katalog</option>
                @foreach($catalogs as $c)
                    <option value="{{ $c->id }}" @selected($batchId === $c->id)>
                        {{ \Illuminate\Support\Str::limit($c->original_filename, 30) }} ({{ $c->chunks_count }} chunk)
                    </option>
                @endforeach
            </select>
            <select name="top" class="text-sm border border-slate-300 rounded-lg px-3 py-2" title="Jumlah hasil">
                @foreach([10, 15, 25, 50] as $n)
                    <option value="{{ $n }}" @selected($topK === $n)>{{ $n }} hasil</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700">Cari</button>
        </div>
        <p class="mt-2 text-xs text-slate-400">
            Query Anda di-embed jadi vektor, lalu dicocokkan dengan chunk katalog memakai cosine similarity.
            Skor lebih tinggi = lebih mirip secara makna.
        </p>
    </form>

    @if($error)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $error }}</div>
    @endif

    @if($ran && ! $error)
        <p class="text-sm text-slate-500 mb-2">{{ count($results) }} hasil teratas untuk: <span class="font-medium text-slate-700">"{{ $query }}"</span></p>

        <div class="space-y-3">
            @forelse($results as $r)
                @php($pct = round($r['score'] * 100))
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between mb-1">
                        <div class="text-xs text-slate-400">
                            {{ $r['batch_name'] ?? 'Katalog #'.$r['batch_id'] }} &middot; chunk #{{ $r['chunk_index'] }}
                        </div>
                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                            {{ number_format($r['score'], 4) }} ({{ $pct }}%)
                        </span>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-slate-100 mb-2">
                        <div class="h-1.5 rounded-full bg-indigo-500" style="width: {{ max(2, min(100, $pct)) }}%"></div>
                    </div>
                    <p class="text-sm text-slate-700 leading-relaxed">{{ \Illuminate\Support\Str::limit($r['content'], 600) }}</p>
                </div>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white p-8 text-center text-slate-400">Tidak ada hasil.</div>
            @endforelse
        </div>
    @elseif(! $ran)
        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-400">
            Ketik kueri di atas untuk mulai mencari di katalog yang sudah di-index.
        </div>
    @endif
@endsection
