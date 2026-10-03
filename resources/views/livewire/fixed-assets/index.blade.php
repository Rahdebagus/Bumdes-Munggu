@php use App\Enums\AsetStatus; @endphp
<div>
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-700">Daftar Aset Tetap</h2>
        @can('perform-admin-mutation')
            <a href="{{ route('assets.create') }}" wire:navigate><x-ui.button>+ Daftar Aset</x-ui.button></a>
        @endcan
    </div>

    <x-ui.table :headers="['Kode', 'Nama', 'Perolehan', 'Residu', 'Umur', 'Status', '']"
                :is-empty="$aset->isEmpty()" empty="Belum ada aset.">
        @foreach ($aset as $a)
            <tr wire:key="ast-{{ $a->id }}">
                <td class="px-4 py-2 font-medium text-slate-900">{{ $a->kode_aset }}</td>
                <td class="px-4 py-2">{{ $a->nama_aset }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $a->harga_perolehan, 2, ',', '.') }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $a->nilai_residu, 2, ',', '.') }}</td>
                <td class="px-4 py-2 text-right">{{ $a->umur_manfaat }}</td>
                <td class="px-4 py-2"><x-ui.badge :color="$a->status === AsetStatus::Aktif ? 'emerald' : 'slate'">{{ $a->status->value }}</x-ui.badge></td>
                <td class="px-4 py-2 text-right"><a href="{{ route('assets.show', $a) }}" wire:navigate class="text-sm font-medium text-emerald-700 hover:underline">Detail</a></td>
            </tr>
        @endforeach
    </x-ui.table>

    <div class="mt-4">{{ $aset->links() }}</div>
</div>
