<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Jadwal Angsuran</h1>
            <p class="text-sm text-slate-500">Seluruh jadwal angsuran dari semua pinjaman beserta status pembayaran.</p>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            <label class="block">
                <span class="text-xs font-medium text-slate-500">Status</span>
                <select wire:model.live="status" class="mt-1 rounded-lg border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option value="belum_dibayar">Belum dibayar</option>
                    <option value="sebagian">Sebagian</option>
                    <option value="lunas">Lunas</option>
                </select>
            </label>
            <x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari no. pinjaman / peminjam…" />
        </div>
    </div>

    <x-ui.card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-3 py-3">Pinjaman</th>
                        <th class="px-3 py-3">Peminjam</th>
                        <th class="px-3 py-3 text-center">Ke</th>
                        <th class="px-3 py-3">Jatuh Tempo</th>
                        <th class="px-3 py-3 text-right">Pokok</th>
                        <th class="px-3 py-3 text-right">Bunga</th>
                        <th class="px-3 py-3 text-right">Denda</th>
                        <th class="px-3 py-3 text-right">Dibayar (P/B/D)</th>
                        <th class="px-3 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($angsuran as $a)
                        @php($terlambat = $a->status->value !== 'lunas' && $a->tanggal_jatuh_tempo && $a->tanggal_jatuh_tempo->toDateString() < $today)
                        <tr class="{{ $terlambat ? 'bg-rose-50/40' : '' }}">
                            <td class="px-3 py-2.5 font-medium text-slate-900">
                                <a href="{{ route('loans.show', $a->pinjaman_id) }}" wire:navigate class="text-emerald-700 hover:underline">{{ $a->pinjaman->nomor_pinjaman }}</a>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600">{{ $a->pinjaman->peminjam?->nama ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-center tabular-nums text-slate-600">{{ $a->angsuran_ke }}</td>
                            <td class="px-3 py-2.5 tabular-nums {{ $terlambat ? 'font-medium text-rose-600' : 'text-slate-500' }}">{{ $a->tanggal_jatuh_tempo?->format('d/m/Y') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ number_format((float) $a->pokok_tagihan, 2, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ number_format((float) $a->bunga_tagihan, 2, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-500">{{ number_format((float) $a->denda_tagihan, 0, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-xs text-slate-500">
                                {{ number_format((float) $a->pokok_dibayar, 0, ',', '.') }} / {{ number_format((float) $a->bunga_dibayar, 0, ',', '.') }} / {{ number_format((float) $a->denda_dibayar, 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-2.5">
                                @php($s = $a->status->value)
                                <x-ui.badge :color="$s === 'lunas' ? 'emerald' : ($s === 'sebagian' ? 'amber' : ($terlambat ? 'red' : 'slate'))">{{ str_replace('_', ' ', $s) }}</x-ui.badge>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-10 text-center text-slate-500">Belum ada jadwal angsuran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $angsuran->links() }}</div>
    </x-ui.card>
</div>
