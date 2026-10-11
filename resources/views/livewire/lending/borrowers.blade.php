<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Data Peminjam</h1>
            <p class="text-sm text-slate-500">Daftar peminjam unit usaha kredit beserta ringkasan pinjamannya.</p>
        </div>
        <div class="flex items-end gap-2">
            <x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari nama / kode peminjam…" />
            <a href="{{ route('master.parties') }}" wire:navigate>
                <x-ui.button variant="secondary">Kelola di Data Pihak</x-ui.button>
            </a>
        </div>
    </div>

    <x-ui.card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-3 py-3">Kode</th>
                        <th class="px-3 py-3">Nama</th>
                        <th class="px-3 py-3 text-center">Pinjaman Aktif</th>
                        <th class="px-3 py-3 text-center">Total Pinjaman</th>
                        <th class="px-3 py-3 text-right">Sisa Pokok</th>
                        <th class="px-3 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($peminjam as $p)
                        <tr>
                            <td class="px-3 py-2.5 font-medium text-slate-900">{{ $p->kode_pihak }}</td>
                            <td class="px-3 py-2.5 text-slate-700">{{ $p->nama }}</td>
                            <td class="px-3 py-2.5 text-center tabular-nums text-slate-600">{{ $p->pinjaman_aktif }}</td>
                            <td class="px-3 py-2.5 text-center tabular-nums text-slate-500">{{ $p->pinjaman_total }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ number_format((float) ($p->sisa_pokok_total ?? 0), 2, ',', '.') }}</td>
                            <td class="px-3 py-2.5">
                                <x-ui.badge :color="$p->status->value === 'aktif' ? 'emerald' : 'slate'">{{ $p->status->value }}</x-ui.badge>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-10 text-center text-slate-500">Belum ada peminjam. Tambahkan di menu Data Pihak.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $peminjam->links() }}</div>
    </x-ui.card>
</div>
