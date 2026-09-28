@php use App\Enums\StatusUmum; @endphp
<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="w-72">
            <x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari kode / nama unit…" />
        </div>
        @can('perform-admin-mutation')
            <x-ui.button wire:click="create">+ Tambah Unit</x-ui.button>
        @endcan
    </div>

    <x-ui.table :headers="['Kode', 'Nama', 'Jenis', 'Penanggung Jawab', 'Status', '']"
                :is-empty="$units->isEmpty()" empty="Belum ada unit usaha.">
        @foreach ($units as $u)
            <tr wire:key="unit-{{ $u->id }}">
                <td class="px-4 py-2 font-medium text-slate-900">{{ $u->kode_unit }}</td>
                <td class="px-4 py-2">{{ $u->nama_unit }}</td>
                <td class="px-4 py-2 capitalize">{{ $u->jenis_usaha->value }}</td>
                <td class="px-4 py-2">{{ $u->penanggung_jawab }}</td>
                <td class="px-4 py-2">
                    <x-ui.badge :color="$u->status === StatusUmum::Aktif ? 'emerald' : 'slate'">{{ $u->status->value }}</x-ui.badge>
                </td>
                <td class="px-4 py-2 text-right whitespace-nowrap">
                    @can('perform-admin-mutation')
                        <button wire:click="edit({{ $u->id }})" class="text-sm font-medium text-emerald-700 hover:underline">Edit</button>
                        <button wire:click="toggleStatus({{ $u->id }})" class="ml-3 text-sm text-slate-500 hover:underline">
                            {{ $u->status === StatusUmum::Aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <div class="mt-4">{{ $units->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="$set('showModal', false)"></div>
            <div class="relative w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">{{ $form->unitId ? 'Edit' : 'Tambah' }} Unit Usaha</h2>
                </div>
                <form wire:submit="save" class="space-y-3 px-5 py-4">
                    <x-ui.input label="Kode Unit" wire:model="form.kode_unit" :error="$errors->first('form.kode_unit')" />
                    <x-ui.input label="Nama Unit" wire:model="form.nama_unit" :error="$errors->first('form.nama_unit')" />
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700">Jenis Usaha</label>
                        <select wire:model="form.jenis_usaha" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="dagang">Dagang</option>
                            <option value="kredit">Kredit</option>
                        </select>
                        @error('form.jenis_usaha') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-ui.input label="Penanggung Jawab" wire:model="form.penanggung_jawab" :error="$errors->first('form.penanggung_jawab')" />
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700">Status</label>
                        <select wire:model="form.status" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit">Simpan</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
