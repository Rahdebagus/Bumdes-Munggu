@props([
    'headers' => [],
    'empty' => 'Belum ada data.',
    'isEmpty' => false,
])

<div class="overflow-x-auto rounded-xl border border-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        @if (! empty($headers))
            <thead class="bg-slate-50">
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-slate-100 bg-white">
            @if ($isEmpty)
                <tr>
                    <td colspan="{{ max(count($headers), 1) }}" class="px-4 py-10 text-center text-slate-500">
                        {{ $empty }}
                    </td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>
