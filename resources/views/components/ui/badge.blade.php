@props(['color' => 'slate'])

@php
    $colors = [
        'slate' => 'bg-slate-100 text-slate-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'red' => 'bg-red-100 text-red-700',
        'amber' => 'bg-amber-100 text-amber-800',
        'blue' => 'bg-blue-100 text-blue-700',
    ];
    $classes = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '
        .($colors[$color] ?? $colors['slate']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
