@props(['items' => []])

@if (! empty($items))
    <nav aria-label="Breadcrumb" class="text-xs text-slate-500">
        <ol class="flex items-center gap-1">
            @foreach ($items as $item)
                <li class="flex items-center gap-1">
                    @if (! $loop->first)
                        <span aria-hidden="true" class="text-slate-300">/</span>
                    @endif
                    @if (! empty($item['url']) && ! $loop->last)
                        <a href="{{ $item['url'] }}" wire:navigate class="hover:text-slate-700">{{ $item['label'] }}</a>
                    @else
                        <span @if($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
