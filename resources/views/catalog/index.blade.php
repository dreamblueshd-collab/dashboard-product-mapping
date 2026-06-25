@extends('layouts.app')
@section('title', 'Katalog PDF')

@section('content')
    <div class="mb-4">
        <h1 class="text-2xl font-bold">Katalog Produk (PDF)</h1>
        <p class="text-slate-500 text-sm">Upload katalog PDF, lalu jalankan auto-mapping: AI menentukan tiap produk cocok untuk kendaraan apa saja.</p>
    </div>

    <form action="{{ route('catalog.import') }}" method="POST" enctype="multipart/form-data"
          class="rounded-xl bg-white border border-slate-200 p-4 mb-6">
        @csrf
        <label class="block text-sm font-medium mb-2">Upload Katalog (.pdf)</label>
        <div class="flex flex-col sm:flex-row gap-2">
            <input type="file" name="file" required accept="application/pdf,.pdf"
                   class="flex-1 text-sm border border-slate-300 rounded-lg px-3 py-2 bg-slate-50">
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Upload &amp; Ekstrak</button>
        </div>
        <p class="mt-2 text-xs text-slate-400">Teks PDF akan diekstrak otomatis. Saat ini ada <strong>{{ number_format($productCount) }}</strong> produk di database.</p>
    </form>

    <h2 class="font-semibold text-lg mb-3">Katalog Terunggah</h2>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-2 font-medium">File</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium">Keterangan</th>
                    <th class="px-4 py-2 font-medium">RAG Chunks</th>
                    <th class="px-4 py-2 font-medium">Waktu</th>
                    <th class="px-4 py-2 font-medium text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($batches as $batch)
                    <tr>
                        <td class="px-4 py-2 max-w-[220px] truncate" title="{{ $batch->original_filename }}">{{ $batch->original_filename }}</td>
                        <td class="px-4 py-2">@include('partials.status', ['status' => $batch->status])</td>
                        <td class="px-4 py-2 text-slate-500 max-w-[300px]">{{ $batch->message }}</td>
                        <td class="px-4 py-2">
                            @if($batch->chunks_count > 0)
                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ number_format($batch->chunks_count) }} chunk</span>
                            @else
                                <span class="text-xs text-slate-400">belum di-index</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-400">{{ $batch->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-2 text-right">
                            @if($batch->status === 'completed')
                                <div class="flex justify-end gap-1">
                                    <form action="{{ route('catalog.indexRag', $batch) }}" method="POST">
                                        @csrf
                                        <button class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700" title="Chunk + embedding untuk RAG">
                                            {{ $batch->chunks_count > 0 ? 'Re-index RAG' : 'Index RAG' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('catalog.generateMappings', $batch) }}" method="POST">
                                        @csrf
                                        <button class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-rose-700">
                                            Auto-Mapping
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-xs text-slate-400">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Belum ada katalog. Upload PDF di atas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-slate-400">
        <strong>Alur RAG:</strong> setelah upload PDF, klik <strong>Index RAG</strong> (memecah teks jadi chunk + membuat embedding Gemini) lalu <strong>Auto-Mapping</strong>.
        Saat mapping, sistem mengambil potongan katalog paling relevan per produk (retrieval cosine + rerank LLM) sebagai konteks.
        Jika katalog belum di-index, mapping tetap jalan memakai potongan teks katalog penuh sebagai fallback.
        Semua diproses via antrian &mdash; jalankan <code>php artisan queue:work</code>.
        Hasil dilihat di <a href="{{ route('mappings.index') }}" class="text-indigo-600 underline">Product Mapping</a>.
    </p>
@endsection
