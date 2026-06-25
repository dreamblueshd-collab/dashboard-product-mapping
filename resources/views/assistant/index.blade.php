@extends('layouts.app')
@section('title', 'Asisten AI')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold">Asisten AI</h1>
            <p class="text-slate-500 text-sm">Tanya apa saja tentang katalog, produk, kendaraan, atau mapping. Dijawab AI Gemini dengan konteks data Anda (RAG + rerank).</p>
        </div>
        @if(!empty($messages))
            <form action="{{ route('assistant.reset') }}" method="POST">
                @csrf
                <button class="rounded-lg bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Reset Percakapan</button>
            </form>
        @endif
    </div>

    @unless($configured)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>VERTEX_API_KEY belum diisi.</strong> Asisten AI memerlukannya untuk menghasilkan jawaban.
        </div>
    @endunless

    <div class="rounded-xl border border-slate-200 bg-white flex flex-col" style="min-height: 60vh;">
        {{-- Area percakapan --}}
        <div class="flex-1 p-4 space-y-4 overflow-y-auto" id="chat-scroll" style="max-height: 65vh;">
            @forelse($messages as $m)
                @if($m['role'] === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-[80%] rounded-2xl rounded-br-sm bg-indigo-600 text-white px-4 py-2 text-sm whitespace-pre-line">{{ $m['content'] }}</div>
                    </div>
                @else
                    <div class="flex justify-start">
                        <div class="max-w-[85%] space-y-2">
                            <div class="rounded-2xl rounded-bl-sm bg-slate-100 text-slate-800 px-4 py-2 text-sm whitespace-pre-line">{{ $m['content'] }}</div>
                            @if(!empty($m['sources']))
                                @php($src = $m['sources'])
                                <details class="text-xs text-slate-500 px-1">
                                    <summary class="cursor-pointer select-none hover:text-slate-700">
                                        Sumber: {{ count($src['catalog'] ?? []) }} potongan katalog ·
                                        {{ $src['products_count'] ?? 0 }} produk ·
                                        {{ $src['vehicles_count'] ?? 0 }} kendaraan ·
                                        {{ $src['mappings_count'] ?? 0 }} mapping
                                    </summary>
                                    <div class="mt-2 space-y-1">
                                        @forelse(($src['catalog'] ?? []) as $c)
                                            <div class="rounded-md bg-slate-50 border border-slate-100 px-2 py-1">
                                                <span class="font-medium text-slate-600">{{ $c['batch_name'] ?? 'katalog' }} · chunk #{{ $c['chunk_index'] }}</span>
                                                @if(!is_null($c['score']))<span class="text-indigo-600"> ({{ $c['score'] }})</span>@endif
                                                <div class="text-slate-500">{{ $c['excerpt'] }}</div>
                                            </div>
                                        @empty
                                            <div class="text-slate-400">Tidak ada potongan katalog yang dipakai.</div>
                                        @endforelse
                                        @if(!empty($src['note']))<div class="text-amber-600">{{ $src['note'] }}</div>@endif
                                    </div>
                                </details>
                            @endif
                        </div>
                    </div>
                @endif
            @empty
                <div class="h-full flex flex-col items-center justify-center text-center text-slate-400 py-10">
                    <p class="font-medium text-slate-500">Mulai percakapan</p>
                    <p class="text-sm mt-1">Contoh: "Aki apa untuk Honda BeAT?", "Produk GTZ-5S cocok di kendaraan apa saja?", "Sebutkan aki untuk mobil Avanza".</p>
                </div>
            @endforelse
        </div>

        {{-- Form input --}}
        <form action="{{ route('assistant.ask') }}" method="POST" class="border-t border-slate-200 p-3"
              onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='Memproses...';">
            @csrf
            <div class="flex gap-2">
                <input type="text" name="question" required autofocus autocomplete="off"
                       placeholder="Tulis pertanyaan Anda..."
                       class="flex-1 text-sm border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">Kirim</button>
            </div>
        </form>
    </div>

    <p class="mt-2 text-xs text-slate-400">Asisten menjawab berdasarkan data yang sudah diupload. Untuk hasil katalog terbaik, pastikan katalog sudah di-<em>Index RAG</em>.</p>

    <script>
        // Auto-scroll ke pesan terbaru.
        (function () {
            var el = document.getElementById('chat-scroll');
            if (el) el.scrollTop = el.scrollHeight;
        })();
    </script>
@endsection
