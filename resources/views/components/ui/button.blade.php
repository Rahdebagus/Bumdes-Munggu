@props([
    'variant' => 'primary',
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60';
    $variants = [
        'primary' => 'bg-emerald-600 text-white hover:bg-emerald-700',
        'secondary' => 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
        'ghost' => 'text-slate-600 hover:bg-slate-100',
    ];
    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</button>
