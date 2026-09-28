@php use App\Enums\TransaksiStatus; @endphp
<div class="space-y-6">
    @can('perform-admin-mutation')
        <x-ui.card title="Jurnal Penyesuaian Baru" subtitle="Debit harus sama dengan kredit saat diposting">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-slate-700">Tanggal</label>
                    <x-ui.input type="date" wire:model="tanggal" :error="$errors->first('tanggal')" />
                </div>
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-slate-700">Periode</label>
                    <select wire:model="periodeId" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— pilih —</option>
                        @foreach ($periods as $p)
                            <option value="{{ $p->id }}">{{ str_pad($p->bulan, 2, '0', STR_PAD_LEFT) }}/{{ $p->tahun }}{{ $p->opening_locked_at ? '' : ' (opening blm dikunci)' }}</option>
                        @endforeach
                    </select>
                    @error('periodeId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-slate-700">Keterangan</label>
                    <x-ui.input wire:model="keterangan" />
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Akun</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Debit</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Kredit</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($lines as $i => $line)
                            <tr wire:key="line-{{ $i }}">
                                <td class="px-3 py-1.5">
                                    <select wire:model="lines.{{ $i }}.akun_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <option value="">— akun —</option>
                                        @foreach ($akun as $a)
                                            <option value="{{ $a->id }}">{{ $a->kode_akun }} — {{ $a->nama_akun }}</option>
                                        @endforeach
                                    </select>
                                    @error("lines.$i.akun_id") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-3 py-1.5"><input type="number" step="0.01" wire:model="lines.{{ $i }}.debit" class="w-32 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                                <td class="px-3 py-1.5"><input type="number" step="0.01" wire:model="lines.{{ $i }}.kredit" class="w-32 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                                <td class="px-3 py-1.5 text-right">
                                    @if (count($lines) > 2)
                                        <button wire:click="removeLine({{ $i }})" class="text-sm text-red-600 hover:underline">Hapus</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('lines') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="mt-3 flex gap-2">
                <x-ui.button variant="secondary" wire:click="addLine">+ Baris</x-ui.button>
                <x-ui.button wire:click="saveDraft">Simpan Draft</x-ui.button>
            </div>
        </x-ui.card>
    @endcan

    <x-ui.card title="Daftar Jurnal Penyesuaian">
        @error('post') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('reverse') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror

        <x-ui.table :headers="['Nomor', 'Tanggal', 'Total', 'Status', 'Baris', '']"
                    :is-empty="$journals->isEmpty()" empty="Belum ada jurnal penyesuaian.">
            @foreach ($journals as $j)
                <tr wire:key="jrnl-{{ $j->id }}">
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $j->nomor_transaksi }}</td>
                    <td class="px-4 py-2">{{ $j->tanggal->format('d M Y') }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $j->total }}</td>
                    <td class="px-4 py-2">
                        <x-ui.badge :color="match($j->status) { TransaksiStatus::Posted => 'emerald', TransaksiStatus::Reversed => 'amber', default => 'slate' }">
                            {{ $j->status->value }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-2 text-right">{{ $j->details->count() }}</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        @can('perform-admin-mutation')
                            @if ($j->status === TransaksiStatus::Draft)
                                <button wire:click="post({{ $j->id }})" class="text-sm font-medium text-emerald-700 hover:underline">Post</button>
                            @elseif ($j->status === TransaksiStatus::Posted)
                                <button wire:click="$set('reversingId', {{ $j->id }})" class="text-sm text-amber-700 hover:underline">Reverse</button>
                            @endif
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $journals->links() }}</div>
    </x-ui.card>

    @if ($reversingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="$set('reversingId', null)"></div>
            <div class="relative w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                <h2 class="text-sm font-semibold text-slate-900">Alasan pembalikan</h2>
                <textarea wire:model="reverseReason" rows="3" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                @error('reverseReason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="mt-3 flex justify-end gap-2">
                    <x-ui.button variant="secondary" wire:click="$set('reversingId', null)">Batal</x-ui.button>
                    <x-ui.button variant="danger" wire:click="reverse">Balik transaksi</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
