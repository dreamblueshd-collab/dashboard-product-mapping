<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
