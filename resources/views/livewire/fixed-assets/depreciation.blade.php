<div class="space-y-5">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Penyusutan Aset</h1>
        <p class="text-sm text-slate-500">Posting penyusutan garis lurus per periode. Nominal dihitung otomatis; satu aset hanya sekali per periode.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @error('dep')<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $message }}</div>@enderror

    <x-ui.card>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <label class="block">
                <span class="text-xs font-medium text-slate-500">Periode</span>
                <select wire:model.live="periodeId" class="mt-1 rounded-lg border-slate-300 text-sm">
                    @foreach ($this->periods() as $p)
                        <option value="{{ $p->id }}">{{ str_pad($p->bulan, 2, '0', STR_PAD_LEFT) }}/{{ $p->tahun }}{{ $p->status->value === 'ditutup' ? ' (ditutup)' : '' }}</option>
                    @endforeach
                </select>
            </label>
            @can('perform-admin-mutation')
                @unless ($periodeTutup)
                    <x-ui.button wire:click="postAllEligible" wire:confirm="Posting penyusutan semua aset yang siap untuk {{ $periodeLabel }}?">
                        Posting Semua yang Siap
                    </x-ui.button>
                @endunless
            @endcan
        </div>
        @if ($periodeTutup)
            <p class="mt-2 text-xs text-amber-600">Periode {{ $periodeLabel }} sudah ditutup — posting penyusutan dinonaktifkan.</p>
        @endif
    </x-ui.card>

    <x-ui.card :title="'Aset Tetap — '.$periodeLabel">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-3 py-3">Kode</th>
                        <th class="px-3 py-3">Nama</th>
                        <th class="px-3 py-3">Unit</th>
                        <th class="px-3 py-3 text-right">Harga Perolehan</th>
                        <th class="px-3 py-3 text-right">Akumulasi</th>
                        <th class="px-3 py-3 text-right">Nilai Buku</th>
                        <th class="px-3 py-3">Keterangan</th>
                        <th class="px-3 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($rows as $r)
                        <tr>
                            <td class="px-3 py-2.5 font-medium text-slate-900"><a href="{{ route('assets.show', $r['id']) }}" wire:navigate class="text-emerald-700 hover:underline">{{ $r['kode'] }}</a></td>
                            <td class="px-3 py-2.5 text-slate-700">{{ $r['nama'] }}</td>
                            <td class="px-3 py-2.5 text-slate-500">{{ $r['unit'] }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ number_format((float) $r['perolehan'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-600">{{ number_format((float) $r['akumulasi'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-medium text-slate-900">{{ number_format((float) $r['nilai_buku'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2.5">
                                <x-ui.badge :color="$r['sudah'] ? 'emerald' : ($r['eligible'] ? 'blue' : 'slate')">{{ $r['keterangan'] }}</x-ui.badge>
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                @can('perform-admin-mutation')
                                    @if ($r['eligible'] && ! $periodeTutup)
                                        <x-ui.button variant="secondary" wire:click="post({{ $r['id'] }})">Posting</x-ui.button>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-10 text-center text-slate-500">Belum ada aset tetap. Daftarkan di menu Data Aset Tetap.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
