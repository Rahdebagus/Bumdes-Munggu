@php use App\Enums\PihakJenis; use App\Enums\StatusUmum; @endphp
<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            <div class="w-64">
                <x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari kode / nama pihak…" />
            </div>
            <select wire:model.live="jenis" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua jenis</option>
                @foreach (PihakJenis::cases() as $j)
                    <option value="{{ $j->value }}">{{ ucfirst($j->value) }}</option>
                @endforeach
            </select>
        </div>
        @can('perform-admin-mutation')
            <x-ui.button wire:click="create">+ Tambah Pihak</x-ui.button>
        @endcan
    </div>

    <x-ui.table :headers="['Kode', 'Jenis', 'Nama', 'Telepon', 'Status', '']"
                :is-empty="$pihak->isEmpty()" empty="Belum ada pihak.">
        @foreach ($pihak as $p)
            <tr wire:key="pihak-{{ $p->id }}">
                <td class="px-4 py-2 font-medium text-slate-900">{{ $p->kode_pihak }}</td>
                <td class="px-4 py-2 capitalize">{{ $p->jenis_pihak->value }}</td>
                <td class="px-4 py-2">{{ $p->nama }}</td>
                <td class="px-4 py-2">{{ $p->telepon ?? '—' }}</td>
                <td class="px-4 py-2">
                    <x-ui.badge :color="$p->status === StatusUmum::Aktif ? 'emerald' : 'slate'">{{ $p->status->value }}</x-ui.badge>
                </td>
                <td class="px-4 py-2 text-right whitespace-nowrap">
                    @can('perform-admin-mutation')
                        <button wire:click="edit({{ $p->id }})" class="text-sm font-medium text-emerald-700 hover:underline">Edit</button>
                        <button wire:click="toggleStatus({{ $p->id }})" class="ml-3 text-sm text-slate-500 hover:underline">
                            {{ $p->status === StatusUmum::Aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <div class="mt-4">{{ $pihak->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="$set('showModal', false)"></div>
            <div class="relative w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">{{ $form->pihakId ? 'Edit' : 'Tambah' }} Pihak</h2>
                </div>
                <form wire:submit="save" class="space-y-3 px-5 py-4">
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input label="Kode Pihak" wire:model="form.kode_pihak" :error="$errors->first('form.kode_pihak')" />
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-slate-700">Jenis Pihak</label>
                            <select wire:model="form.jenis_pihak" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                @foreach (PihakJenis::cases() as $j)
                                    <option value="{{ $j->value }}">{{ ucfirst($j->value) }}</option>
                                @endforeach
                            </select>
                            @error('form.jenis_pihak') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <x-ui.input label="Nama" wire:model="form.nama" :error="$errors->first('form.nama')" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input label="Nomor Identitas (opsional)" wire:model="form.nomor_identitas" />
                        <x-ui.input label="Telepon (opsional)" wire:model="form.telepon" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700">Alamat (opsional)</label>
                        <textarea wire:model="form.alamat" rows="2" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    </div>
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
