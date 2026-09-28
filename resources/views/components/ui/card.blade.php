@props([
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white shadow-sm']) }}>
    @if ($title || $subtitle || isset($actions))
        <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                @if ($title)
                    <h3 class="text-sm font-semibold text-slate-900">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div>{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="px-5 py-4">
        {{ $slot }}
    </div>
</div>
