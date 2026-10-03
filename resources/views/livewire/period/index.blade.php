<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Periode Akuntansi</h1>
        <p class="text-sm text-slate-500">Tutup/buka periode. Penutupan menolak bila rekonsiliasi gagal, penyusutan/opname belum selesai.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @error('periode')<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $message }}</div>@enderror

    @can('manage-period')
        <x-ui.card title="Tambah Periode" subtitle="Buat periode bulan baru agar transaksi tanggal tersebut dapat dicatat">
            <div class="flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Bulan</span>
                    <select wire:model="newBulan" class="mt-1 rounded-lg border-slate-300 text-sm">
                        @foreach (range(1, 12) as $m)
                            <option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Tahun</span>
                    <input type="number" wire:model="newTahun" class="mt-1 w-28 rounded-lg border-slate-300 text-sm tabular-nums">
                </label>
                <x-ui.button wire:click="createPeriod">Buat periode</x-ui.button>
            </div>
            @error('newBulan')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('newTahun')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </x-ui.card>
    @endcan

    <x-ui.card>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                    <th class="px-3 py-2">Periode</th>
                    <th class="px-3 py-2">Rentang</th>
                    <th class="px-3 py-2">Opening</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Rev</th>
                    <th class="px-3 py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->periods() as $p)
                    @php($info = $p->status->value === 'terbuka' && $p->opening_locked_at ? $this->closeInfo($p) : ['blockers' => [], 'warnings' => []])
                    <tr class="border-b border-slate-100 align-top">
                        <td class="px-3 py-2 font-medium text-slate-800">{{ str_pad($p->bulan, 2, '0', STR_PAD_LEFT) }}/{{ $p->tahun }}</td>
                        <td class="px-3 py-2 text-slate-500 tabular-nums">{{ $p->tanggal_mulai->format('d/m') }}–{{ $p->tanggal_selesai->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">
                            @if ($p->opening_locked_at)
                                <x-ui.badge color="emerald">terkunci</x-ui.badge>
                            @else
                                <x-ui.badge color="slate">belum</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($p->status->value === 'terbuka')
                                <x-ui.badge color="blue">terbuka</x-ui.badge>
                            @else
                                <x-ui.badge color="slate">ditutup</x-ui.badge>
                            @endif
                            @if ($p->reopened_at)
                                <span class="ml-1 text-[10px] text-amber-600" title="{{ $p->reopen_reason }}">pernah dibuka</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 tabular-nums text-slate-500">{{ $p->revision }}</td>
                        <td class="px-3 py-2 text-right">
                            @can('manage-period')
                                @if ($p->status->value === 'terbuka' && $p->opening_locked_at)
                                    @if ($info['blockers'] !== [])
                                        <div class="mb-1 text-left text-xs text-rose-600">
                                            @foreach ($info['blockers'] as $b)<div>• {{ $b }}</div>@endforeach
                                        </div>
                                        <x-ui.button variant="secondary" disabled>Tutup (terblokir)</x-ui.button>
                                    @else
                                        @if ($info['warnings'] !== [])
                                            <div class="mb-1 text-left text-xs text-amber-600">
                                                @foreach ($info['warnings'] as $w)<div>• {{ $w }}</div>@endforeach
                                            </div>
                                        @endif
                                        <x-ui.button wire:click="close({{ $p->id }})"
                                            wire:confirm="Tutup periode {{ $p->bulan }}/{{ $p->tahun }}?">Tutup</x-ui.button>
                                    @endif
                                @elseif ($p->status->value === 'ditutup')
                                    <x-ui.button variant="secondary" wire:click="startReopen({{ $p->id }})">Buka kembali</x-ui.button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>

    @if ($reopenId)
        <x-ui.card title="Buka kembali periode" subtitle="Alasan wajib dan tercatat di audit">
            @error('reopen')<p class="mb-2 text-sm text-rose-600">{{ $message }}</p>@enderror
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <label class="flex-1">
                    <span class="text-xs font-medium text-slate-500">Alasan</span>
                    <input type="text" wire:model="reopenReason" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Alasan koreksi…">
                </label>
                <div class="flex gap-2">
                    <x-ui.button wire:click="reopen">Buka kembali</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="$set('reopenId', null)">Batal</x-ui.button>
                </div>
            </div>
        </x-ui.card>
    @endif
</div>
