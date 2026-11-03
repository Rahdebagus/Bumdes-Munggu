<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Laporan Omzet Desa</h1>
        <p class="text-sm text-slate-500">Snapshot per periode: buat draft → ajukan → Pimpinan menyetujui atau meminta revisi. Angka beku saat disetujui; koreksi = versi baru.</p>
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
                @php($live = $this->liveRevision())
                @forelse ($this->versions() as $l)
                    @php($stale = $l->status->value === 'diajukan' && $live !== null && $live !== (int) $l->data_revision)
                    @php($showNote = $l->catatan_pimpinan && in_array($l->status->value, ['revisi', 'diajukan']))
                    <tr @class(['border-b border-slate-100' => ! $stale && ! $showNote])>
                        <td class="px-3 py-2">
                            <div class="font-medium text-slate-800">{{ $l->nomor_laporan }}</div>
                            <div class="text-xs text-slate-500">
                                Dagang {{ number_format((float) $l->omzet_dagang, 0, ',', '.') }}
                                · Kredit {{ number_format((float) $l->pendapatan_kredit, 0, ',', '.') }}
                                · Lain {{ number_format((float) $l->pendapatan_lain, 0, ',', '.') }}
                            </div>
                        </td>
                        <td class="px-3 py-2 tabular-nums">{{ $l->version }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $l->total_omzet, 2, ',', '.') }}</td>
                        <td class="px-3 py-2">
                            @php($color = match ($l->status->value) {
                                'disetujui', 'diterima' => 'emerald', 'dikirim' => 'blue',
                                'diajukan' => 'amber', 'revisi' => 'red', default => 'slate',
                            })
                            <x-ui.badge :color="$color">{{ $l->status->value === 'revisi' ? 'perlu revisi' : $l->status->value }}</x-ui.badge>
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
                                        <x-ui.button variant="secondary" wire:click="startDelivery({{ $l->id }})">Kirim/Terima</x-ui.button>
                                    @endif
                                @endcan
                                @can('approve-laporan')
                                    @if ($l->status->value === 'diajukan')
                                        <x-ui.button wire:click="approve({{ $l->id }})" wire:confirm="Setujui laporan {{ $l->nomor_laporan }}?">Setujui</x-ui.button>
                                        <x-ui.button variant="danger" wire:click="startRevision({{ $l->id }})">Minta Revisi</x-ui.button>
                                    @endif
                                @endcan
                                <a href="{{ route('reports.laporan-desa.export', ['laporan' => $l->id, 'format' => 'pdf']) }}"
                                   class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">PDF</a>
                                <a href="{{ route('reports.laporan-desa.export', ['laporan' => $l->id, 'format' => 'xlsx']) }}"
                                   class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">XLSX</a>
                            </div>
                        </td>
                    </tr>
                    @if ($stale || $showNote)
                        <tr class="border-b border-slate-100">
                            <td colspan="6" class="space-y-1 px-3 pb-3">
                                @if ($stale)
                                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                        Angka periode berubah sejak laporan diajukan. Admin perlu mengajukan ulang sebelum laporan dapat disetujui.
                                    </div>
                                @endif
                                @if ($showNote)
                                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                                        <span class="font-semibold">Catatan Pimpinan:</span> {{ $l->catatan_pimpinan }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-sm text-slate-400">Belum ada laporan untuk periode ini. Buat draft untuk mulai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    @if ($revisionId)
        <x-ui.card title="Minta Revisi Laporan" subtitle="Laporan dikembalikan ke Admin; catatan wajib diisi agar jelas apa yang harus diperbaiki">
            <label class="block">
                <span class="text-xs font-medium text-slate-500">Catatan untuk Admin</span>
                <textarea wire:model="revisionNote" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm"
                          placeholder="mis. Penjualan pupuk tanggal 12 belum masuk; cek ulang nota Toko Sari."></textarea>
                @error('revisionNote')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </label>
            <div class="mt-3 flex gap-2">
                <x-ui.button variant="danger" wire:click="requestRevision">Kembalikan ke Admin</x-ui.button>
                <x-ui.button variant="secondary" wire:click="$set('revisionId', null)">Batal</x-ui.button>
            </div>
        </x-ui.card>
    @endif

    @if ($deliverId)
        <x-ui.card title="Catat Pengiriman ke Desa" subtitle="Pencatatan manual; tidak mengubah nominal laporan">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Tanggal dikirim</span>
                    <input type="date" wire:model="deliverKirim" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    @error('deliverKirim')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Tanggal diterima</span>
                    <input type="date" wire:model="deliverTerima" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    @error('deliverTerima')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">No./ket. bukti penerimaan</span>
                    <input type="text" wire:model="deliverBukti" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="mis. Tanda terima Sekdes">
                    @error('deliverBukti')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
            </div>
            <div class="mt-3 flex gap-2">
                <x-ui.button wire:click="recordDelivery">Simpan</x-ui.button>
                <x-ui.button variant="secondary" wire:click="$set('deliverId', null)">Batal</x-ui.button>
            </div>
            <p class="mt-2 text-xs text-slate-400">Isi tanggal kirim → status menjadi <span class="font-medium">dikirim</span>; isi tanggal diterima → status <span class="font-medium">diterima</span>.</p>
        </x-ui.card>
    @endif
</div>
