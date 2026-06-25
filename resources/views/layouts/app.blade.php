<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --tw-font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        html { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        body { -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }

        /* ===== Markdown elegan (jawaban AI) ===== */
        .md { font-size: 0.875rem; line-height: 1.7; color: #1e293b; word-break: break-word; }
        .md > :first-child { margin-top: 0; }
        .md > :last-child { margin-bottom: 0; }
        .md p { margin: 0.5rem 0; }
        .md h1, .md h2, .md h3 { font-weight: 600; line-height: 1.3; margin: 0.9rem 0 0.4rem; color: #0f172a; }
        .md h1 { font-size: 1.15rem; }
        .md h2 { font-size: 1.05rem; }
        .md h3 { font-size: 0.95rem; }
        .md ul, .md ol { margin: 0.5rem 0; padding-left: 1.25rem; }
        .md ul { list-style: disc; }
        .md ol { list-style: decimal; }
        .md li { margin: 0.2rem 0; padding-left: 0.15rem; }
        .md li > ul, .md li > ol { margin: 0.25rem 0; }
        .md strong { font-weight: 600; color: #0f172a; }
        .md em { color: #475569; }
        .md a { color: #4f46e5; text-decoration: underline; text-underline-offset: 2px; }
        .md code { background: #f1f5f9; color: #be185d; padding: 0.08rem 0.35rem; border-radius: 0.3rem; font-size: 0.8em; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .md pre { background: #0f172a; color: #e2e8f0; padding: 0.85rem 1rem; border-radius: 0.6rem; overflow-x: auto; margin: 0.6rem 0; }
        .md pre code { background: transparent; color: inherit; padding: 0; }
        .md blockquote { border-left: 3px solid #c7d2fe; padding-left: 0.75rem; color: #475569; margin: 0.6rem 0; }
        .md table { border-collapse: collapse; margin: 0.6rem 0; font-size: 0.8rem; width: 100%; }
        .md th, .md td { border: 1px solid #e2e8f0; padding: 0.35rem 0.6rem; text-align: left; }
        .md th { background: #f8fafc; font-weight: 600; }
        .md hr { border: 0; border-top: 1px solid #e2e8f0; margin: 0.9rem 0; }

        /* Scrollbar halus */
        .nice-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
        .nice-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        .nice-scroll::-webkit-scrollbar-track { background: transparent; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
<div class="min-h-screen flex flex-col">
    <header class="bg-slate-900 text-white">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold text-lg">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-500 text-sm">PM</span>
                {{ config('app.name') }}
            </a>
            <nav class="flex items-center gap-1 text-sm">
                @php($nav = [
                    'dashboard' => 'Dashboard',
                    'products.index' => 'Produk',
                    'vehicles.index' => 'Kendaraan',
                    'mappings.index' => 'Mapping',
                    'catalog.index' => 'Katalog PDF',
                    'search.index' => 'Pencarian',
                    'assistant.index' => 'Asisten AI',
                ])
                @foreach($nav as $route => $label)
                    <a href="{{ route($route) }}"
                       class="px-3 py-2 rounded-md transition {{ request()->routeIs(Str::before($route, '.').'*') || request()->routeIs($route) ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-6">
        @if(session('success'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-slate-200 py-4 text-center text-xs text-slate-500">
        {{ config('app.name') }} &middot; Laravel {{ app()->version() }} &middot; AI: Vertex AI (Gemini)
    </footer>
</div>
</body>
</html>
