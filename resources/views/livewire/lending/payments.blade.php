<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Pembayaran Angsuran</h1>
            <p class="text-sm text-slate-500">Angsuran jatuh tempo dari pinjaman aktif. Pilih "Bayar" untuk mencatat pembayaran pokok/bunga/denda.</p>
        </div>
        <x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari no. pinjaman / peminjam…" />
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @error('pay')<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $message }}</div>@enderror

    <x-ui.card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-3 py-3">Pinjaman</th>
                        <th class="px-3 py-3">Peminjam</th>
                        <th class="px-3 py-3 text-center">Angsuran</th>
                        <th class="px-3 py-3">Jatuh Tempo</th>
                        <th class="px-3 py-3 text-right">Sisa Pokok</th>
                        <th class="px-3 py-3 text-right">Sisa Bunga</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($angsuran as $a)
                        @php($terlambat = $a->tanggal_jatuh_tempo && $a->tanggal_jatuh_tempo->toDateString() < $today)
                        @php($sisaPokok = (float) $a->pokok_tagihan - (float) $a->pokok_dibayar)
                        @php($sisaBunga = (float) $a->bunga_tagihan - (float) $a->bunga_dibayar)
                        <tr class="{{ $terlambat ? 'bg-rose-50/40' : '' }}">
                            <td class="px-3 py-2.5 font-medium text-slate-900">
                                <a href="{{ route('loans.show', $a->pinjaman_id) }}" wire:navigate class="text-emerald-700 hover:underline">{{ $a->pinjaman->nomor_pinjaman }}</a>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600">{{ $a->pinjaman->peminjam?->nama ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-center tabular-nums text-slate-600">ke-{{ $a->angsuran_ke }}</td>
                            <td class="px-3 py-2.5 tabular-nums {{ $terlambat ? 'font-medium text-rose-600' : 'text-slate-500' }}">{{ $a->tanggal_jatuh_tempo?->format('d/m/Y') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ number_format($sisaPokok, 2, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ number_format($sisaBunga, 2, ',', '.') }}</td>
                            <td class="px-3 py-2.5">
                                @php($s = $a->status->value)
                                <x-ui.badge :color="$s === 'sebagian' ? 'amber' : 'slate'">{{ str_replace('_', ' ', $s) }}</x-ui.badge>
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                @can('perform-admin-mutation')
                                    <x-ui.button variant="secondary" wire:click="startPay({{ $a->id }})">Bayar</x-ui.button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-10 text-center text-slate-500">Tidak ada angsuran jatuh tempo. 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $angsuran->links() }}</div>
    </x-ui.card>

    @if ($payAngsuranId)
        <x-ui.card title="Catat Pembayaran Angsuran" subtitle="Nilai diisi otomatis dari sisa tagihan; boleh diubah untuk pembayaran sebagian">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Tanggal</span>
                    <input type="date" wire:model="payTanggal" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Akun Kas/Bank</span>
                    <select wire:model="akunKasId" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        @foreach ($akunKas as $k)
                            <option value="{{ $k->id }}">{{ $k->kode_akun }} — {{ $k->nama_akun }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Pokok</span>
                    <input type="number" step="0.01" min="0" wire:model="payPokok" class="mt-1 w-full rounded-lg border-slate-300 text-right text-sm tabular-nums">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Bunga</span>
                    <input type="number" step="0.01" min="0" wire:model="payBunga" class="mt-1 w-full rounded-lg border-slate-300 text-right text-sm tabular-nums">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Denda</span>
                    <input type="number" step="0.01" min="0" wire:model="payDenda" class="mt-1 w-full rounded-lg border-slate-300 text-right text-sm tabular-nums">
                </label>
            </div>
            <div class="mt-3 flex gap-2">
                <x-ui.button wire:click="pay" wire:loading.attr="disabled">Bayar & Posting</x-ui.button>
                <x-ui.button variant="secondary" wire:click="$set('payAngsuranId', null)">Batal</x-ui.button>
            </div>
        </x-ui.card>
    @endif
</div>
