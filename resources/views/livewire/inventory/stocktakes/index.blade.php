@php use App\Enums\StokOpnameStatus; @endphp
<div>
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-700">Daftar Stok Opname</h2>
        @can('perform-admin-mutation')
            <a href="{{ route('stocktakes.create') }}" wire:navigate><x-ui.button>+ Opname Baru</x-ui.button></a>
        @endcan
    </div>

    <x-ui.table :headers="['Nomor', 'Tanggal', 'Unit', 'Nilai Selisih', 'Status', '']"
                :is-empty="$opname->isEmpty()" empty="Belum ada opname.">
        @foreach ($opname as $o)
            <tr wire:key="opn-{{ $o->id }}">
                <td class="px-4 py-2 font-medium text-slate-900">{{ $o->nomor_opname }}</td>
                <td class="px-4 py-2">{{ $o->tanggal->format('d M Y') }}</td>
                <td class="px-4 py-2">{{ $o->unitUsaha?->nama_unit }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $o->nilai_selisih, 2, ',', '.') }}</td>
                <td class="px-4 py-2">
                    <x-ui.badge :color="match($o->status) { StokOpnameStatus::Posted => 'emerald', StokOpnameStatus::Disetujui => 'blue', StokOpnameStatus::Dibatalkan => 'red', default => 'slate' }">{{ $o->status->value }}</x-ui.badge>
                </td>
                <td class="px-4 py-2 text-right">
                    <a href="{{ route('stocktakes.show', $o) }}" wire:navigate class="text-sm font-medium text-emerald-700 hover:underline">Detail</a>
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <div class="mt-4">{{ $opname->links() }}</div>
</div>
