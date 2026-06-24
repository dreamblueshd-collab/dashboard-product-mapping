@php
    $map = [
        'raw' => ['Raw', 'bg-slate-100 text-slate-600'],
        'refined' => ['Refined', 'bg-green-100 text-green-700'],
        'failed' => ['Failed', 'bg-red-100 text-red-700'],
        'pending' => ['Pending', 'bg-amber-100 text-amber-700'],
        'processing' => ['Processing', 'bg-sky-100 text-sky-700'],
        'completed' => ['Completed', 'bg-green-100 text-green-700'],
    ];
    [$label, $classes] = $map[$status] ?? [ucfirst($status), 'bg-slate-100 text-slate-600'];
@endphp
<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $classes }}">{{ $label }}</span>
