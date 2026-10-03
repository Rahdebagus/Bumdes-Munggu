@php use App\Enums\StokOpnameStatus; $st = $o->status; @endphp
<div class="space-y-6">
    <x-ui.breadcrumb :items="[['label' => 'Stok Opname', 'url' => route('stocktakes.index')], ['label' => $o->nomor_opname]]" />

    @error('opname') <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div> @enderror

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4 text-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="text-lg font-semibold text-slate-900">{{ $o->nomor_opname }}</span>
                    <x-ui.badge :color="match($st) { StokOpnameStatus::Posted => 'emerald', StokOpnameStatus::Disetujui => 'blue', default => 'slate' }">{{ $st->value }}</x-ui.badge>
                    <span class="text-xs text-slate-400">revisi {{ $o->revision }}</span>
                </div>
                <p class="text-slate-500">{{ $o->tanggal->format('d M Y') }} · {{ $o->unitUsaha?->nama_unit }} · Nilai selisih {{ number_format((float) $o->nilai_selisih, 2, ',', '.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('perform-admin-mutation')
                    @if ($st === StokOpnameStatus::Draft)<x-ui.button wire:click="complete">Tandai Selesai</x-ui.button>@endif
                    @if ($st === StokOpnameStatus::Selesai)<x-ui.button wire:click="submit">Ajukan Persetujuan</x-ui.button>@endif
                    @if ($st === StokOpnameStatus::Disetujui)<x-ui.button wire:click="post">Posting</x-ui.button>@endif
                @endcan
                @can('approve-stocktake')
                    @if ($st === StokOpnameStatus::MenungguPersetujuan)
                        <x-ui.button wire:click="approve">Setujui</x-ui.button>
                        <x-ui.button variant="secondary" wire:click="requestRevision">Minta Revisi</x-ui.button>
                    @elseif ($st === StokOpnameStatus::Disetujui)
                        <x-ui.button variant="secondary" wire:click="requestRevision">Minta Revisi</x-ui.button>
                    @endif
                @endcan
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Rincian">
        <x-ui.table :headers="['Produk', 'Sistem', 'Fisik', 'Selisih', 'HPP', 'Nilai Selisih']"
                    :is-empty="$o->details->isEmpty()" empty="Tidak ada rincian.">
            @foreach ($o->details as $d)
                <tr wire:key="opd-{{ $d->id }}">
                    <td class="px-4 py-2">{{ $d->produk?->nama_produk }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ rtrim(rtrim($d->stok_sistem, '0'), '.') }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ rtrim(rtrim($d->stok_fisik, '0'), '.') }}</td>
                    <td class="px-4 py-2 text-right tabular-nums {{ (float) $d->selisih < 0 ? 'text-red-600' : '' }}">{{ rtrim(rtrim($d->selisih, '0'), '.') }}</td>
                    <td class="px-4 py-2 text-right tabular-nums text-slate-500">{{ number_format((float) $d->harga_pokok, 2, ',', '.') }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $d->nilai_selisih, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
</div>
