@php use App\Enums\TransaksiStatus; @endphp
<div>
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="w-56"><x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari nomor transaksi…" /></div>
        <select wire:model.live="jenis" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua jenis</option>
            @foreach ($jenisList as $j)
                <option value="{{ $j->value }}">{{ ucfirst($j->value) }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua status</option>
            @foreach ($statusList as $s)
                <option value="{{ $s->value }}">{{ ucfirst($s->value) }}</option>
            @endforeach
        </select>
    </div>

    <x-ui.table :headers="['Nomor', 'Tanggal', 'Jenis', 'Unit', 'Pihak', 'Total', 'Status', '']"
                :is-empty="$transaksi->isEmpty()" empty="Belum ada transaksi.">
        @foreach ($transaksi as $t)
            <tr wire:key="trx-{{ $t->id }}">
                <td class="px-4 py-2 font-medium text-slate-900">{{ $t->nomor_transaksi }}</td>
                <td class="px-4 py-2">{{ $t->tanggal->format('d M Y') }}</td>
                <td class="px-4 py-2 capitalize">{{ $t->jenis_transaksi->value }}</td>
                <td class="px-4 py-2">{{ $t->unitUsaha?->nama_unit ?? '—' }}</td>
                <td class="px-4 py-2">{{ $t->pihak?->nama ?? '—' }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $t->total, 2, ',', '.') }}</td>
                <td class="px-4 py-2">
                    <x-ui.badge :color="match($t->status) { TransaksiStatus::Posted => 'emerald', TransaksiStatus::Reversed => 'amber', default => 'slate' }">{{ $t->status->value }}</x-ui.badge>
                </td>
                <td class="px-4 py-2 text-right">
                    <a href="{{ route('transactions.show', $t) }}" wire:navigate class="text-sm font-medium text-emerald-700 hover:underline">Detail</a>
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <div class="mt-4">{{ $transaksi->links() }}</div>
</div>
