@extends('layouts.app')
@section('title', 'Asisten AI')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Asisten AI</h1>
            <p class="text-slate-500 text-sm">Tanya tentang katalog, produk, kendaraan, atau mapping. Dijawab Gemini dengan konteks data Anda.</p>
        </div>
        <a href="#"
           onclick="event.preventDefault(); document.getElementById('new-chat-form').submit();"
           class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Chat Baru
        </a>
        <form id="new-chat-form" action="{{ route('assistant.reset') }}" method="POST" class="hidden">@csrf</form>
    </div>

    @unless($configured)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>VERTEX_API_KEY belum diisi.</strong> Asisten AI memerlukannya untuk menghasilkan jawaban.
        </div>
    @endunless

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        {{-- Sidebar riwayat percakapan --}}
        <aside class="lg:col-span-1 rounded-2xl border border-slate-200 bg-white p-3 h-max">
            <div class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Riwayat</div>
            <div class="mt-1 space-y-1 max-h-[60vh] overflow-y-auto nice-scroll">
                @forelse($recent as $c)
                    @php($active = $conversation && $conversation->id === $c->id)
                    <a href="{{ route('assistant.index', ['conversation' => $c->uuid]) }}"
                       class="block rounded-lg px-3 py-2 text-sm transition {{ $active ? 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200' : 'text-slate-600 hover:bg-slate-50' }}">
                        <div class="truncate font-medium">{{ $c->title ?? 'Percakapan baru' }}</div>
                        <div class="text-[11px] text-slate-400">{{ $c->messages_count }} pesan · {{ $c->updated_at?->diffForHumans() }}</div>
                    </a>
                @empty
                    <p class="px-3 py-2 text-sm text-slate-400">Belum ada riwayat.</p>
                @endforelse
            </div>
        </aside>

        {{-- Area chat --}}
        <section class="lg:col-span-3 rounded-2xl border border-slate-200 bg-white flex flex-col overflow-hidden" style="min-height: 64vh;">
            <div class="flex-1 p-5 space-y-5 overflow-y-auto nice-scroll" id="chat-scroll" style="max-height: 68vh;">
                @forelse($messages as $m)
                    @if($m->role === 'user')
                        <div class="flex justify-end">
                            <div class="max-w-[80%] rounded-2xl rounded-br-md bg-indigo-600 text-white px-4 py-2.5 text-sm shadow-sm whitespace-pre-line">{{ $m->content }}</div>
                        </div>
                    @else
                        <div class="flex justify-start">
                            <div class="flex gap-3 max-w-[88%]">
                                <div class="mt-0.5 h-8 w-8 shrink-0 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 text-white grid place-items-center text-xs font-bold">AI</div>
                                <div class="space-y-2 min-w-0">
                                    <div class="md rounded-2xl rounded-bl-md bg-slate-50 border border-slate-100 px-4 py-3">
                                        {!! \App\Support\Markdown::toHtml($m->content) !!}
                                    </div>
                                    @if(!empty($m->sources))
                                        @php($src = $m->sources)
                                        <details class="group text-xs text-slate-500 px-1">
                                            <summary class="cursor-pointer select-none inline-flex items-center gap-1 hover:text-slate-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                                Sumber: {{ count($src['catalog'] ?? []) }} katalog · {{ $src['products_count'] ?? 0 }} produk · {{ $src['vehicles_count'] ?? 0 }} kendaraan · {{ $src['mappings_count'] ?? 0 }} mapping
                                            </summary>
                                            <div class="mt-2 space-y-1">
                                                @forelse(($src['catalog'] ?? []) as $c)
                                                    <div class="rounded-md bg-white border border-slate-100 px-2 py-1">
                                                        <span class="font-medium text-slate-600">{{ $c['batch_name'] ?? 'katalog' }} · chunk #{{ $c['chunk_index'] ?? '-' }}</span>
                                                        @if(!empty($c['score']))<span class="text-indigo-600"> ({{ $c['score'] }})</span>@endif
                                                        <div class="text-slate-500">{{ $c['excerpt'] ?? '' }}</div>
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
                        </div>
                    @endif
                @empty
                    <div class="h-full flex flex-col items-center justify-center text-center text-slate-400 py-12">
                        <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white grid place-items-center text-base font-bold mb-3">AI</div>
                        <p class="font-medium text-slate-600">Mulai percakapan</p>
                        <p class="text-sm mt-1 max-w-md">Contoh: "Aki apa untuk Honda BeAT?", "Produk GTZ-5S cocok di kendaraan apa saja?", "Sebutkan aki untuk mobil Avanza".</p>
                    </div>
                @endforelse
            </div>

            <form action="{{ route('assistant.ask') }}" method="POST" class="border-t border-slate-200 bg-white p-3"
                  onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='...';">
                @csrf
                <div class="flex items-center gap-2">
                    <input type="text" name="question" required autofocus autocomplete="off"
                           placeholder="Tulis pertanyaan Anda..."
                           class="flex-1 text-sm border border-slate-300 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">Kirim</button>
                </div>
            </form>
        </section>
    </div>

    <p class="mt-2 text-xs text-slate-400">Riwayat percakapan tersimpan otomatis. Untuk hasil katalog terbaik, pastikan katalog sudah di-<em>Index RAG</em>.</p>

    <script>
        (function () { var el = document.getElementById('chat-scroll'); if (el) el.scrollTop = el.scrollHeight; })();
    </script>
@endsection
