@php
    use App\Domain\Accounting\DocumentActions;
    use App\Enums\TransaksiStatus;
    $statusLabel = ['draft' => 'Draft', 'posted' => 'Diposting', 'reversed' => 'Dibatalkan'];
@endphp
<div>
    <p class="mb-3 text-sm text-slate-500">Daftar dokumen/nota. Hasil jurnal (debet–kredit) ada di menu
        <a href="{{ route('journal.index') }}" wire:navigate class="font-medium text-emerald-700 underline">Jurnal Umum</a>.</p>

    <div class="mb-4 flex flex-wrap items-end gap-2">
        <div class="w-60"><x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari nomor / pihak / keterangan…" /></div>
        <select wire:model.live="jenis" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua jenis</option>
            @foreach ($jenisList as $j)
                <option value="{{ $j->value }}">{{ ucfirst($j->value) }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua status</option>
            @foreach ($statusList as $s)
                <option value="{{ $s->value }}">{{ $statusLabel[$s->value] ?? ucfirst($s->value) }}</option>
            @endforeach
        </select>
        <select wire:model.live="unitId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua unit</option>
            @foreach ($units as $u)
                <option value="{{ $u->id }}">{{ $u->nama_unit }}</option>
            @endforeach
        </select>
        <label class="text-xs text-slate-500">Dari
            <input type="date" wire:model.live="dari" class="block rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
        </label>
        <label class="text-xs text-slate-500">Sampai
            <input type="date" wire:model.live="sampai" class="block rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
        </label>
        <button type="button" wire:click="resetFilter" class="px-2 py-2 text-sm text-slate-500 hover:underline">Reset</button>
    </div>

    <x-ui.table :headers="['No. Nota', 'Tanggal', 'Jenis', 'Unit', 'Pihak', 'Total', 'Status', '']"
                :is-empty="$transaksi->isEmpty()" empty="Belum ada transaksi.">
        @foreach ($transaksi as $t)
            <tr wire:key="trx-{{ $t->id }}">
                <td class="px-4 py-2 font-medium whitespace-nowrap text-slate-900">
                    <a href="{{ route('transactions.show', $t) }}" wire:navigate class="hover:text-emerald-700 hover:underline">{{ $t->nomor_transaksi }}</a>
                </td>
                <td class="px-4 py-2 whitespace-nowrap">{{ $t->tanggal->format('d/m/Y') }}</td>
                <td class="px-4 py-2 capitalize">{{ $t->jenis_transaksi->value }}{{ $t->sumber_tipe === 'stok_awal' ? ' (stok awal)' : '' }}</td>
                <td class="px-4 py-2">{{ $t->unitUsaha?->nama_unit ?? '—' }}</td>
                <td class="px-4 py-2">{{ $t->pihak?->nama ?? '—' }}</td>
                <td class="px-4 py-2 text-right tabular-nums whitespace-nowrap">Rp {{ number_format((float) $t->total, 0, ',', '.') }}</td>
                <td class="px-4 py-2">
                    <x-ui.badge :color="match($t->status) { TransaksiStatus::Posted => 'emerald', TransaksiStatus::Reversed => 'amber', default => 'slate' }">{{ $statusLabel[$t->status->value] ?? $t->status->value }}</x-ui.badge>
                </td>
                <td class="px-4 py-2 text-right text-sm whitespace-nowrap">
                    <a href="{{ route('transactions.show', $t) }}" wire:navigate class="font-medium text-emerald-700 hover:underline">Buka</a>
                    @can('perform-admin-mutation')
                        @if ($editUrl = DocumentActions::editUrl($t))
                            <a href="{{ $editUrl }}" wire:navigate class="ml-3 text-slate-600 hover:underline">Edit</a>
                            <button type="button" wire:click="deleteDraft({{ $t->id }})" wire:confirm="Hapus draft {{ $t->nomor_transaksi }}? Tindakan ini tidak dapat dibatalkan."
                                    class="ml-3 text-red-600 hover:underline">Hapus</button>
                        @endif
                    @endcan
                    <a href="{{ route('transactions.nota', $t) }}" target="_blank" class="ml-3 text-slate-600 hover:underline">Cetak</a>
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <div class="mt-4">{{ $transaksi->links() }}</div>
</div>
