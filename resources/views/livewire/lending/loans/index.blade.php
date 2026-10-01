@php use App\Enums\PinjamanStatus; @endphp
<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div class="flex gap-2">
            <div class="w-56"><x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari nomor pinjaman…" /></div>
            <select wire:model.live="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua status</option>
                @foreach (PinjamanStatus::cases() as $s)
                    <option value="{{ $s->value }}">{{ ucfirst($s->value) }}</option>
                @endforeach
            </select>
        </div>
        @can('perform-admin-mutation')
            <a href="{{ route('loans.create') }}" wire:navigate>
                <x-ui.button>+ Buat Pinjaman</x-ui.button>
            </a>
        @endcan
    </div>

    <x-ui.table :headers="['Nomor', 'Peminjam', 'Pokok', 'Sisa Pokok', 'Tenor', 'Status', '']"
                :is-empty="$pinjaman->isEmpty()" empty="Belum ada pinjaman.">
        @foreach ($pinjaman as $p)
            <tr wire:key="loan-{{ $p->id }}">
                <td class="px-4 py-2 font-medium text-slate-900">{{ $p->nomor_pinjaman }}</td>
                <td class="px-4 py-2">{{ $p->peminjam?->nama }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $p->pokok_pinjaman, 2, ',', '.') }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $p->sisa_pokok, 2, ',', '.') }}</td>
                <td class="px-4 py-2 text-right">{{ $p->tenor }}</td>
                <td class="px-4 py-2">
                    <x-ui.badge :color="match($p->status) { PinjamanStatus::Aktif => 'blue', PinjamanStatus::Lunas => 'emerald', PinjamanStatus::Macet => 'red', default => 'slate' }">{{ $p->status->value }}</x-ui.badge>
                </td>
                <td class="px-4 py-2 text-right">
                    <a href="{{ route('loans.show', $p) }}" wire:navigate class="text-sm font-medium text-emerald-700 hover:underline">Detail</a>
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <div class="mt-4">{{ $pinjaman->links() }}</div>
</div>
