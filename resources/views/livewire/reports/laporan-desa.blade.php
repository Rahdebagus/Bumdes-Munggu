<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Laporan Omzet Desa</h1>
        <p class="text-sm text-slate-500">Snapshot per periode: buat draft → ajukan → Pimpinan menyetujui. Angka beku saat disetujui; koreksi = versi baru.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @error('laporan')<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $message }}</div>@enderror

    <x-ui.card>
        <div class="flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="text-xs font-medium text-slate-500">Periode (opening terkunci)</span>
                <select wire:model.live="periodeId" class="mt-1 rounded-lg border-slate-300 text-sm">
                    @foreach ($this->periods() as $p)
                        <option value="{{ $p->id }}">{{ str_pad($p->bulan, 2, '0', STR_PAD_LEFT) }}/{{ $p->tahun }}</option>
                    @endforeach
                </select>
            </label>
            @can('perform-admin-mutation')
                <x-ui.button wire:click="generate">Buat / segarkan draft</x-ui.button>
            @endcan
        </div>
    </x-ui.card>

    <x-ui.card title="Versi laporan periode ini" subtitle="Versi lama tetap tersimpan dan dapat diunduh">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                    <th class="px-3 py-2">Nomor</th>
                    <th class="px-3 py-2">Ver</th>
                    <th class="px-3 py-2 text-right">Total omzet</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Disetujui</th>
                    <th class="px-3 py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->versions() as $l)
                    <tr class="border-b border-slate-100">
                        <td class="px-3 py-2 font-medium text-slate-800">{{ $l->nomor_laporan }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $l->version }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $l->total_omzet, 2, ',', '.') }}</td>
                        <td class="px-3 py-2">
                            @php($color = match ($l->status->value) {
                                'disetujui', 'diterima' => 'emerald', 'dikirim' => 'blue',
                                'diajukan' => 'amber', default => 'slate',
                            })
                            <x-ui.badge :color="$color">{{ $l->status->value }}</x-ui.badge>
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-500">{{ $l->approvedBy?->nama ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @can('perform-admin-mutation')
                                    @if (in_array($l->status->value, ['draft', 'revisi', 'diajukan']))
                                        <x-ui.button variant="secondary" wire:click="submit({{ $l->id }})">Ajukan</x-ui.button>
                                    @endif
                                    @if (in_array($l->status->value, ['disetujui', 'dikirim', 'diterima']))
                                        <x-ui.button variant="secondary" wire:click="revise({{ $l->id }})">Revisi</x-ui.button>
                                    @endif
                                @endcan
                                @can('approve-laporan')
                                    @if ($l->status->value === 'diajukan')
                                        <x-ui.button wire:click="approve({{ $l->id }})" wire:confirm="Setujui laporan {{ $l->nomor_laporan }}?">Setujui</x-ui.button>
                                    @endif
                                @endcan
                                <a href="{{ route('reports.laporan-desa.export', ['laporan' => $l->id, 'format' => 'pdf']) }}"
                                   class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">PDF</a>
                                <a href="{{ route('reports.laporan-desa.export', ['laporan' => $l->id, 'format' => 'xlsx']) }}"
                                   class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">XLSX</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-sm text-slate-400">Belum ada laporan untuk periode ini. Buat draft untuk mulai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
