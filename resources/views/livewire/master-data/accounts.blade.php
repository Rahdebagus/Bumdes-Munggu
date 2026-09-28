@php use App\Enums\AkunJenis; @endphp
<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            <div class="w-64">
                <x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari kode / nama / system_key…" />
            </div>
            <select wire:model.live="jenis" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua jenis</option>
                @foreach (AkunJenis::cases() as $j)
                    <option value="{{ $j->value }}">{{ ucfirst($j->value) }}</option>
                @endforeach
            </select>
        </div>
        @can('perform-admin-mutation')
            <x-ui.button wire:click="create">+ Tambah Akun</x-ui.button>
        @endcan
    </div>

    <x-ui.table :headers="['Kode', 'Nama', 'Jenis', 'Saldo Normal', 'System Key', 'Aktif', '']"
                :is-empty="$akun->isEmpty()" empty="Belum ada akun.">
        @foreach ($akun as $a)
            <tr wire:key="akun-{{ $a->id }}">
                <td class="px-4 py-2 font-medium text-slate-900">{{ $a->kode_akun }}</td>
                <td class="px-4 py-2">{{ $a->nama_akun }}</td>
                <td class="px-4 py-2 capitalize">{{ $a->jenis_akun->value }}</td>
                <td class="px-4 py-2 capitalize">{{ $a->saldo_normal->value }}</td>
                <td class="px-4 py-2 text-xs text-slate-500">{{ $a->system_key ?? '—' }}</td>
                <td class="px-4 py-2">
                    <x-ui.badge :color="$a->is_active ? 'emerald' : 'slate'">{{ $a->is_active ? 'aktif' : 'nonaktif' }}</x-ui.badge>
                </td>
                <td class="px-4 py-2 text-right whitespace-nowrap">
                    @can('perform-admin-mutation')
                        <button wire:click="edit({{ $a->id }})" class="text-sm font-medium text-emerald-700 hover:underline">Edit</button>
                        <button wire:click="toggleStatus({{ $a->id }})" class="ml-3 text-sm text-slate-500 hover:underline">
                            {{ $a->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <div class="mt-4">{{ $akun->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="$set('showModal', false)"></div>
            <div class="relative w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">{{ $form->akunId ? 'Edit' : 'Tambah' }} Akun</h2>
                </div>
                <form wire:submit="save" class="space-y-3 px-5 py-4">
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input label="Kode Akun" wire:model="form.kode_akun" :error="$errors->first('form.kode_akun')" />
                        <x-ui.input label="Nama Akun" wire:model="form.nama_akun" :error="$errors->first('form.nama_akun')" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-slate-700">Jenis Akun</label>
                            <select wire:model="form.jenis_akun" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                @foreach (AkunJenis::cases() as $j)
                                    <option value="{{ $j->value }}">{{ ucfirst($j->value) }}</option>
                                @endforeach
                            </select>
                            @error('form.jenis_akun') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-slate-700">Saldo Normal</label>
                            <select wire:model="form.saldo_normal" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="debit">Debit</option>
                                <option value="kredit">Kredit</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input label="System Key (opsional)" wire:model="form.system_key" :error="$errors->first('form.system_key')" />
                        <x-ui.input label="Report Group (opsional)" wire:model="form.report_group" />
                    </div>
                    <div class="flex gap-6 pt-1 text-sm">
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.is_cash" class="rounded border-slate-300"> Akun kas/bank</label>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.is_active" class="rounded border-slate-300"> Aktif</label>
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
