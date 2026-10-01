@php use App\Enums\AngsuranStatus; use App\Enums\PinjamanStatus; @endphp
<div class="space-y-6">
    <x-ui.breadcrumb :items="[['label' => 'Pinjaman', 'url' => route('loans.index')], ['label' => $loan->nomor_pinjaman]]" />

    @error('disburse') <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div> @enderror
    @error('pay') <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div> @enderror

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4 text-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="text-lg font-semibold text-slate-900">{{ $loan->nomor_pinjaman }}</span>
                    <x-ui.badge :color="match($loan->status) { PinjamanStatus::Aktif => 'blue', PinjamanStatus::Lunas => 'emerald', default => 'slate' }">{{ $loan->status->value }}</x-ui.badge>
                </div>
                <p class="text-slate-500">Peminjam: {{ $loan->peminjam?->nama }} · Unit: {{ $loan->unitUsaha?->nama_unit }}</p>
                <p class="text-slate-500">Basis: {{ $loan->basis_bunga?->value }} · Bunga {{ (float) $loan->persentase_bunga }}% · Tenor {{ $loan->tenor }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-400">Pokok / Sisa Pokok</p>
                <p class="text-lg font-semibold tabular-nums text-slate-900">{{ number_format((float) $loan->pokok_pinjaman, 2, ',', '.') }} / {{ number_format((float) $loan->sisa_pokok, 2, ',', '.') }}</p>
                <p class="text-xs text-slate-400">Total bunga {{ number_format((float) $loan->total_bunga, 2, ',', '.') }}</p>
            </div>
        </div>
    </x-ui.card>

    {{-- Pencairan --}}
    @can('perform-admin-mutation')
        @if ($isDraft)
            <x-ui.card title="Pencairan" subtitle="Dr Piutang pinjaman; Cr Kas/bank">
                <div class="flex flex-wrap items-end gap-3">
                    <x-ui.input label="Tanggal" type="date" wire:model="disburseTanggal" />
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700">Akun Kas/Bank</label>
                        <select wire:model="akunKasId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">— pilih —</option>
                            @foreach ($akunKas as $a)<option value="{{ $a->id }}">{{ $a->kode_akun }} — {{ $a->nama_akun }}</option>@endforeach
                        </select>
                        @error('akunKasId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-ui.button wire:click="disburse" wire:loading.attr="disabled">Cairkan</x-ui.button>
                </div>
            </x-ui.card>
        @endif
    @endcan

    {{-- Jadwal angsuran --}}
    <x-ui.card title="Jadwal Angsuran">
        <x-ui.table :headers="['Ke', 'Jatuh Tempo', 'Pokok', 'Bunga', 'Denda', 'Dibayar (P/B/D)', 'Status']"
                    :is-empty="$loan->angsuran->isEmpty()" empty="Belum ada jadwal.">
            @foreach ($loan->angsuran->sortBy('angsuran_ke') as $a)
                <tr wire:key="ang-{{ $a->id }}">
                    <td class="px-4 py-2">{{ $a->angsuran_ke }}</td>
                    <td class="px-4 py-2">{{ $a->tanggal_jatuh_tempo->format('d M Y') }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $a->pokok_tagihan, 2, ',', '.') }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $a->bunga_tagihan, 2, ',', '.') }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $a->denda_tagihan, 2, ',', '.') }}</td>
                    <td class="px-4 py-2 text-right tabular-nums text-slate-500">{{ number_format((float) $a->pokok_dibayar, 0, ',', '.') }} / {{ number_format((float) $a->bunga_dibayar, 0, ',', '.') }} / {{ number_format((float) $a->denda_dibayar, 0, ',', '.') }}</td>
                    <td class="px-4 py-2"><x-ui.badge :color="$a->status === AngsuranStatus::Lunas ? 'emerald' : ($a->status === AngsuranStatus::Sebagian ? 'amber' : 'slate')">{{ $a->status->value }}</x-ui.badge></td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    {{-- Pembayaran --}}
    @can('perform-admin-mutation')
        @if ($isAktif)
            <x-ui.card title="Bayar Angsuran" subtitle="Boleh sebagian; alokasi pokok/bunga/denda">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700">Angsuran</label>
                        <select wire:model="payAngsuranId" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">— pilih jadwal —</option>
                            @foreach ($loan->angsuran->sortBy('angsuran_ke') as $a)
                                <option value="{{ $a->id }}">Ke-{{ $a->angsuran_ke }} ({{ $a->tanggal_jatuh_tempo->format('d/m/Y') }})</option>
                            @endforeach
                        </select>
                        @error('payAngsuranId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-ui.input label="Tanggal Bayar" type="date" wire:model="payTanggal" />
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700">Akun Kas/Bank</label>
                        <select wire:model="akunKasId" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">— pilih —</option>
                            @foreach ($akunKas as $a)<option value="{{ $a->id }}">{{ $a->kode_akun }} — {{ $a->nama_akun }}</option>@endforeach
                        </select>
                    </div>
                    <x-ui.input label="Pokok" type="number" step="0.01" wire:model="payPokok" />
                    <x-ui.input label="Bunga" type="number" step="0.01" wire:model="payBunga" />
                    <x-ui.input label="Denda" type="number" step="0.01" wire:model="payDenda" />
                </div>
                <div class="mt-3 flex justify-end">
                    <x-ui.button wire:click="pay" wire:loading.attr="disabled">Bayar &amp; Posting</x-ui.button>
                </div>
            </x-ui.card>
        @endif
    @endcan
</div>
