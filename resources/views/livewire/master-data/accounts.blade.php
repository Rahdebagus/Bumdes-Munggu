@php
    use App\Enums\AkunJenis;
    $judul = [
        'aset' => ['1', 'Aset (Harta)', 'Kas, bank, piutang, persediaan, inventaris'],
        'kewajiban' => ['2', 'Kewajiban (Utang)', 'Utang usaha, utang gaji, utang pajak'],
        'modal' => ['3', 'Modal & Dana', 'Modal desa, dana-dana, laba ditahan'],
        'pendapatan' => ['4', 'Pendapatan', 'Penjualan per unit & pendapatan lain'],
        'hpp' => ['5', 'Harga Pokok Penjualan', 'Diisi otomatis saat barang terjual'],
        'beban' => ['6', 'Biaya', 'Biaya operasional, non operasional & pajak'],
    ];
@endphp
<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="w-64">
                <x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari kode / nama akun…" />
            </div>
            <select wire:model.live="jenis" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua jenis</option>
                @foreach (AkunJenis::cases() as $j)
                    <option value="{{ $j->value }}">{{ $judul[$j->value][1] ?? ucfirst($j->value) }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" wire:model.live="tampilNonaktif" class="rounded border-slate-300 text-emerald-600">
                Tampilkan akun nonaktif ({{ $jumlahNonaktif }})
            </label>
        </div>
        @can('perform-admin-mutation')
            <x-ui.button wire:click="create">+ Tambah Akun</x-ui.button>
        @endcan
    </div>

    <p class="mb-4 text-sm text-slate-500">Akun bertanda <x-ui.badge color="blue">otomatis</x-ui.badge> dipakai sistem saat posting (penjualan, pembelian, kas) — cukup ganti namanya bila perlu, jangan dinonaktifkan.</p>

    @forelse ($groups as $jenisKey => $list)
        <div class="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" wire:key="grp-{{ $jenisKey }}">
            <div class="flex items-baseline justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-2.5">
                <h3 class="text-sm font-semibold text-slate-800">{{ $judul[$jenisKey][0] ?? '' }}. {{ $judul[$jenisKey][1] ?? ucfirst($jenisKey) }}</h3>
                <span class="text-xs text-slate-500">{{ $judul[$jenisKey][2] ?? '' }} · {{ $list->count() }} akun</span>
            </div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($list as $a)
                        <tr wire:key="akun-{{ $a->id }}" class="{{ $a->is_active ? '' : 'bg-slate-50 text-slate-400' }}">
                            <td class="w-32 px-4 py-2 font-medium tabular-nums text-slate-900">{{ $a->kode_akun }}</td>
                            <td class="px-4 py-2">
                                {{ $a->nama_akun }}
                                @if ($a->system_key && in_array($a->system_key, config('accounting.system_keys', []), true))
                                    <x-ui.badge color="blue" class="ml-1" title="system_key: {{ $a->system_key }}">otomatis</x-ui.badge>
                                @endif
                                @if ($a->is_cash)
                                    <x-ui.badge color="emerald" class="ml-1">kas/bank</x-ui.badge>
                                @endif
                            </td>
                            <td class="w-28 px-4 py-2 text-xs capitalize text-slate-500">saldo {{ $a->saldo_normal->value }}</td>
                            <td class="w-24 px-4 py-2">
                                @unless ($a->is_active)
                                    <x-ui.badge color="slate">nonaktif</x-ui.badge>
                                @endunless
                            </td>
                            <td class="w-44 px-4 py-2 text-right whitespace-nowrap">
                                @can('perform-admin-mutation')
                                    <button type="button" wire:click="edit({{ $a->id }})" class="text-sm font-medium text-emerald-700 hover:underline">Edit</button>
                                    <button type="button" wire:click="toggleStatus({{ $a->id }})" class="ml-3 text-sm text-slate-500 hover:underline">
                                        {{ $a->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p class="rounded-xl border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500">Tidak ada akun yang cocok.</p>
    @endforelse

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
