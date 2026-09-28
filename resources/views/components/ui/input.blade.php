@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
])

<div class="space-y-1">
    @if ($label)
        <label @if($name) for="{{ $name }}" @endif class="block text-sm font-medium text-slate-700">
            {{ $label }}
        </label>
    @endif

    <input
        type="{{ $type }}"
        @if($name) id="{{ $name }}" name="{{ $name }}" @endif
        {{ $attributes->merge([
            'class' => 'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 '
                .($error ? 'border-red-400' : 'border-slate-300'),
        ]) }}
        @if($error) aria-invalid="true" @endif
    >

    @if ($error)
        <p class="text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
