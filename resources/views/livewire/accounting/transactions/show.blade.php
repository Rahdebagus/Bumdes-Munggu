@php use App\Enums\TransaksiStatus; @endphp
<div class="space-y-6">
    <x-ui.breadcrumb :items="[
        ['label' => 'Daftar Transaksi', 'url' => route('transactions.index')],
        ['label' => $trx->nomor_transaksi],
    ]" />

    @error('post') <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div> @enderror
    @error('reverse') <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div> @enderror

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1 text-sm">
                <div class="flex items-center gap-2">
                    <span class="text-lg font-semibold text-slate-900">{{ $trx->nomor_transaksi }}</span>
                    <x-ui.badge :color="match($trx->status) { TransaksiStatus::Posted => 'emerald', TransaksiStatus::Reversed => 'amber', default => 'slate' }">{{ $trx->status->value }}</x-ui.badge>
                </div>
                <p class="capitalize text-slate-500">{{ $trx->jenis_transaksi->value }} · {{ $trx->tanggal->format('d M Y') }} · {{ str_pad($trx->periode->bulan, 2, '0', STR_PAD_LEFT) }}/{{ $trx->periode->tahun }}</p>
                <p class="text-slate-500">Unit: {{ $trx->unitUsaha?->nama_unit ?? '—' }} · Pihak: {{ $trx->pihak?->nama ?? '—' }} · Metode: {{ $trx->metode_pembayaran ?? '—' }}</p>
                @if ($trx->keterangan)<p class="text-slate-500">{{ $trx->keterangan }}</p>@endif
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-400">Total</p>
                <p class="text-2xl font-semibold tabular-nums text-slate-900">{{ number_format((float) $trx->total, 2, ',', '.') }}</p>
                <div class="mt-2 flex justify-end gap-2">
                    @can('perform-admin-mutation')
                        @if ($trx->status === TransaksiStatus::Draft)
                            <x-ui.button wire:click="post" wire:loading.attr="disabled">Posting</x-ui.button>
                        @elseif ($trx->status === TransaksiStatus::Posted && $canReverse)
                            <x-ui.button variant="danger" wire:click="$set('confirmingReverse', true)">Reverse</x-ui.button>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- Jurnal (drilldown) --}}
    <x-ui.card title="Jurnal" subtitle="{{ $trx->status === TransaksiStatus::Draft ? 'Draft — belum masuk buku besar' : 'Terposting' }}">
        <x-ui.table :headers="['Akun', 'Debit', 'Kredit']" :is-empty="$trx->details->isEmpty()" empty="Belum ada baris jurnal (draft dihitung saat posting).">
            @foreach ($trx->details as $d)
                <tr>
                    <td class="px-4 py-2">{{ $d->akun?->kode_akun }} — {{ $d->akun?->nama_akun }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ (float) $d->debit ? number_format((float) $d->debit, 2, ',', '.') : '' }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ (float) $d->kredit ? number_format((float) $d->kredit, 2, ',', '.') : '' }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    {{-- Barang --}}
    @if ($trx->barang->isNotEmpty())
        <x-ui.card title="Barang">
            <x-ui.table :headers="['Produk', 'Qty', 'Harga', 'HPP/unit', 'Subtotal']">
                @foreach ($trx->barang as $b)
                    <tr>
                        <td class="px-4 py-2">{{ $b->produk?->nama_produk }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ rtrim(rtrim($b->kuantitas, '0'), '.') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $b->harga, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-slate-500">{{ number_format((float) $b->harga_pokok, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $b->subtotal, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    {{-- Kartu stok terkait (drilldown) --}}
    @if ($mutasi->isNotEmpty())
        <x-ui.card title="Mutasi Stok Terkait">
            <x-ui.table :headers="['Produk', 'Masuk', 'Keluar', 'HPP', 'Saldo Qty', 'Saldo Nilai']">
                @foreach ($mutasi as $m)
                    <tr>
                        <td class="px-4 py-2">{{ $m->produk?->nama_produk }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ (float) $m->kuantitas_masuk ? rtrim(rtrim($m->kuantitas_masuk, '0'), '.') : '' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ (float) $m->kuantitas_keluar ? rtrim(rtrim($m->kuantitas_keluar, '0'), '.') : '' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-slate-500">{{ number_format((float) ($m->nilai_masuk ?: $m->nilai_keluar), 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ rtrim(rtrim($m->saldo_kuantitas, '0'), '.') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $m->saldo_nilai, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    @if ($confirmingReverse)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="$set('confirmingReverse', false)"></div>
            <div class="relative w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                <h2 class="text-sm font-semibold text-slate-900">Alasan pembalikan</h2>
                <p class="mt-1 text-xs text-slate-500">Pembalik dibuat pada tanggal {{ $trx->tanggal->format('d M Y') }}.</p>
                <textarea wire:model="reverseReason" rows="3" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                @error('reverseReason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="mt-3 flex justify-end gap-2">
                    <x-ui.button variant="secondary" wire:click="$set('confirmingReverse', false)">Batal</x-ui.button>
                    <x-ui.button variant="danger" wire:click="reverse">Balik transaksi</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
