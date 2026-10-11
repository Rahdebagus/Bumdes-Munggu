@php
    use Illuminate\Support\Carbon;

    [$from, $to] = $this->range();
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 2, ',', '.');
    $bulanList = \App\Livewire\Reports\Index::BULAN;
    $org = config('bumdes');
    $toC = Carbon::parse($to);
    $toText = $toC->day.' '.($bulanList[$toC->month] ?? $toC->month).' '.$toC->year;

    $tabs = [
        'laba-rugi' => 'Laba Rugi',
        'neraca' => 'Neraca',
        'perubahan-modal' => 'Perubahan Modal',
        'arus-kas' => 'Arus Kas',
        'kredit' => 'Laporan Kredit',
        'omzet-desa' => 'Laporan Omzet Desa',
        'buku-besar' => 'Buku Besar',
    ];
    $exportParams = ['type' => $type, 'from' => $from, 'to' => $to, 'unitId' => $unitId];
@endphp

<div class="space-y-5">
    <style>
        @media print {
            body { background: #fff !important; }
            aside, header, .no-print { display: none !important; }
            .lg\:pl-64 { padding-left: 0 !important; }
            main { padding: 0 !important; }
            #report-sheet { border: none !important; box-shadow: none !important; }
        }
    </style>

    {{-- Filter --}}
    <x-ui.card class="no-print">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Bulan</span>
                    <select wire:model="bulan" class="mt-1 w-36 rounded-lg border-slate-300 text-sm">
                        @foreach ($bulanList as $num => $nama)
                            <option value="{{ $num }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Tahun</span>
                    <select wire:model="tahun" class="mt-1 w-28 rounded-lg border-slate-300 text-sm">
                        @foreach ($this->tahunOptions() as $th)
                            <option value="{{ $th }}">{{ $th }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Unit</span>
                    <select wire:model="unitId" class="mt-1 w-44 rounded-lg border-slate-300 text-sm">
                        <option value="">Semua Unit</option>
                        @foreach ($this->units() as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->nama_unit }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="button" wire:click="$refresh"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-800">
                    Tampilkan
                </button>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Z" /></svg>
                    Cetak
                </button>
                <a href="{{ route('reports.export', array_merge($exportParams, ['format' => 'pdf'])) }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                    Unduh PDF
                </a>
                <a href="{{ route('reports.export', array_merge($exportParams, ['format' => 'xlsx'])) }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                    Unduh Excel
                </a>
            </div>
        </div>
    </x-ui.card>

    {{-- Tabs --}}
    <div class="no-print flex flex-wrap gap-1 border-b border-slate-200">
        @foreach ($tabs as $value => $label)
            <button type="button" wire:click="$set('type', '{{ $value }}')"
                    @class([
                        '-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition',
                        'border-emerald-600 text-emerald-700' => $type === $value,
                        'border-transparent text-slate-500 hover:text-slate-800' => $type !== $value,
                    ])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Lembar laporan --}}
    <div id="report-sheet" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        {{-- Kop --}}
        <div class="flex items-start justify-between gap-4 border-b-2 border-slate-800 pb-4">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-emerald-400 to-emerald-600">
                    <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M5 20a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5Zm6 0a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1h-2Zm6 0a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1h-2Z" /></svg>
                </span>
                <div>
                    <p class="text-lg font-bold tracking-wide text-slate-900">{{ strtoupper($org['nama']) }}</p>
                    <p class="text-xs text-slate-500">{{ $org['badan'] }} · {{ $org['desa'] }}, {{ $org['kecamatan'] }}, {{ $org['kabupaten'] }} – {{ $org['provinsi'] }}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-[11px] font-medium uppercase tracking-wider text-amber-600">Periode</p>
                <p class="text-sm font-semibold text-slate-700">{{ $this->periodeLabel() }}</p>
            </div>
        </div>

        <div class="mb-6 mt-4 text-center">
            <h2 class="text-base font-bold uppercase tracking-wide text-slate-900">{{ $this->title() }}</h2>
            <p class="text-xs text-slate-500">Untuk periode yang berakhir {{ $toText }}</p>
        </div>

        @switch($type)
            {{-- ============================ LABA RUGI ============================ --}}
            @case('laba-rugi')
                @php($d = $this->labaRugi())
                <div class="mx-auto max-w-3xl text-sm">
                    <div class="pt-2 pb-1 font-semibold text-slate-800">Pendapatan</div>
                    @foreach ($d['pendapatan']['rows'] as $r)
                        <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">{{ $r['label'] }}</span><span class="tabular-nums text-slate-700">{{ $rp($r['value']) }}</span></div>
                    @endforeach

                    <div class="pt-4 pb-1 font-semibold text-slate-800">Harga Pokok Penjualan</div>
                    <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">Persediaan Awal</span><span class="tabular-nums text-slate-700">{{ $rp($d['hpp']['persediaan_awal']) }}</span></div>
                    <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">Pembelian Bersih</span><span class="tabular-nums text-slate-700">{{ $rp($d['hpp']['pembelian_bersih']) }}</span></div>
                    <div class="flex justify-between border-b border-slate-100 py-1.5 font-medium"><span class="pl-4 text-slate-700">Barang Tersedia Dijual</span><span class="tabular-nums text-slate-800">{{ $rp($d['hpp']['barang_tersedia']) }}</span></div>
                    <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">Persediaan Akhir</span><span class="tabular-nums text-slate-700">{{ $rp($d['hpp']['persediaan_akhir']) }}</span></div>
                    <div class="flex justify-between border-y border-slate-200 bg-slate-50 px-1 py-2 font-semibold text-slate-800"><span>Harga Pokok Penjualan</span><span class="tabular-nums">{{ $rp($d['hpp']['hpp']) }}</span></div>

                    <div class="mt-2 flex justify-between border-y-2 border-emerald-200 py-2 font-bold text-emerald-700"><span>Laba Kotor Penjualan</span><span class="tabular-nums">{{ $rp($d['laba_kotor']) }}</span></div>

                    <div class="pt-4 pb-1 font-semibold text-slate-800">Pendapatan Lain</div>
                    @foreach ($d['pendapatan_lain']['rows'] as $r)
                        <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">{{ $r['label'] }}</span><span class="tabular-nums text-slate-700">{{ $rp($r['value']) }}</span></div>
                    @endforeach
                    <div class="flex justify-between border-y border-slate-200 bg-slate-50 px-1 py-2 font-semibold text-slate-800"><span>Total Pendapatan Lain</span><span class="tabular-nums">{{ $rp($d['pendapatan_lain']['total']) }}</span></div>

                    <div class="mt-2 flex justify-between border-y-2 border-emerald-200 py-2 font-bold text-emerald-700"><span>Laba Setelah Pendapatan Lain</span><span class="tabular-nums">{{ $rp($d['laba_setelah_lain']) }}</span></div>

                    <div class="pt-4 pb-1 font-semibold text-slate-800">Beban</div>
                    @foreach ($d['beban']['rows'] as $r)
                        <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">{{ $r['label'] }}</span><span class="tabular-nums text-slate-700">{{ $rp($r['value']) }}</span></div>
                    @endforeach
                    <div class="flex justify-between border-y border-slate-200 bg-slate-50 px-1 py-2 font-semibold text-slate-800"><span>Total Beban</span><span class="tabular-nums">{{ $rp($d['beban']['total']) }}</span></div>

                    <div class="mt-2 flex justify-between border-y-2 border-emerald-200 py-2 font-bold text-emerald-700"><span>Laba Sebelum Pajak</span><span class="tabular-nums">{{ $rp($d['laba_sebelum_pajak']) }}</span></div>
                    @forelse ($d['pajak']['rows'] as $r)
                        <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">{{ $r['label'] }}</span><span class="tabular-nums text-slate-700">{{ $rp($r['value']) }}</span></div>
                    @empty
                        <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">Pajak Usaha</span><span class="tabular-nums text-slate-700">{{ $rp(0) }}</span></div>
                    @endforelse

                    <div class="mt-2 flex justify-between rounded-lg bg-emerald-600 px-3 py-2.5 text-base font-bold text-white"><span>Laba Bersih {{ $this->periodeLabel() }}</span><span class="tabular-nums">{{ $rp($d['laba_bersih']) }}</span></div>
                    <div class="mt-1.5 flex justify-between rounded-lg bg-emerald-50 px-3 py-2 font-semibold text-emerald-700"><span>Laba Kumulatif Januari–{{ $this->periodeLabel() }}</span><span class="tabular-nums">{{ $rp($d['laba_kumulatif']) }}</span></div>
                </div>
                @break

            {{-- ============================ NERACA ============================ --}}
            @case('neraca')
                @php($d = $this->neraca())
                <div class="mx-auto max-w-3xl text-sm">
                    <div class="flex items-center justify-between pt-2 pb-1">
                        <span class="font-semibold text-slate-800">Aktiva Lancar</span>
                        <x-ui.badge :color="$d['seimbang'] ? 'emerald' : 'red'">{{ $d['seimbang'] ? 'Seimbang' : 'Tidak Seimbang' }}</x-ui.badge>
                    </div>
                    @foreach ($d['aktiva_lancar']['rows'] as $r)
                        <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">{{ $r['label'] }}</span><span class="tabular-nums text-slate-700">{{ $rp($r['value']) }}</span></div>
                    @endforeach
                    <div class="flex justify-between border-y border-slate-200 bg-slate-50 px-1 py-2 font-semibold text-slate-800"><span>Total Aktiva Lancar</span><span class="tabular-nums">{{ $rp($d['aktiva_lancar']['total']) }}</span></div>

                    <div class="pt-4 pb-1 font-semibold text-slate-800">Aktiva Tetap</div>
                    @foreach ($d['aktiva_tetap']['rows'] as $r)
                        <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">{{ $r['label'] }}</span><span class="tabular-nums {{ $r['contra'] ? 'text-rose-600' : 'text-slate-700' }}">{{ $r['contra'] ? '− '.$rp($r['value']) : $rp($r['value']) }}</span></div>
                    @endforeach
                    <div class="flex justify-between border-y border-slate-200 bg-slate-50 px-1 py-2 font-semibold text-slate-800"><span>Total Aktiva Tetap Bersih</span><span class="tabular-nums">{{ $rp($d['aktiva_tetap']['net']) }}</span></div>

                    <div class="mt-2 flex justify-between border-y-2 border-blue-200 py-2 text-base font-bold text-blue-700"><span>Total Aktiva</span><span class="tabular-nums">{{ $rp($d['total_aktiva']) }}</span></div>

                    <div class="pt-5 pb-1 font-semibold text-slate-800">Kewajiban</div>
                    @foreach ($d['kewajiban']['rows'] as $r)
                        <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">{{ $r['label'] }}</span><span class="tabular-nums text-slate-700">{{ $rp($r['value']) }}</span></div>
                    @endforeach
                    <div class="flex justify-between border-y border-slate-200 bg-slate-50 px-1 py-2 font-semibold text-slate-800"><span>Total Kewajiban</span><span class="tabular-nums">{{ $rp($d['kewajiban']['total']) }}</span></div>

                    <div class="pt-4 pb-1 font-semibold text-slate-800">Ekuitas</div>
                    @foreach ($d['ekuitas']['rows'] as $r)
                        <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">{{ $r['label'] }}</span><span class="tabular-nums text-slate-700">{{ $rp($r['value']) }}</span></div>
                    @endforeach
                    <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">Laba Januari–{{ $this->periodeLabel() }}</span><span class="tabular-nums text-slate-700">{{ $rp($d['ekuitas']['laba']) }}</span></div>
                    <div class="flex justify-between border-y border-slate-200 bg-slate-50 px-1 py-2 font-semibold text-slate-800"><span>Total Ekuitas</span><span class="tabular-nums">{{ $rp($d['ekuitas']['total']) }}</span></div>

                    <div class="mt-2 flex justify-between border-y-2 border-blue-200 py-2 text-base font-bold text-blue-700"><span>Total Pasiva (Kewajiban + Ekuitas)</span><span class="tabular-nums">{{ $rp($d['total_pasiva']) }}</span></div>
                </div>
                @break

            {{-- ======================= PERUBAHAN MODAL ======================= --}}
            @case('perubahan-modal')
                @php($d = $this->perubahanModal())
                <div class="mx-auto max-w-3xl text-sm">
                    <div class="pt-2 pb-1 font-semibold text-slate-800">Modal Awal per Dana</div>
                    @foreach ($d['rows'] as $r)
                        <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">{{ $r['label'] }}</span><span class="tabular-nums text-slate-700">{{ $rp($r['value']) }}</span></div>
                    @endforeach
                    <div class="flex justify-between border-y border-slate-200 bg-slate-50 px-1 py-2 font-semibold text-slate-800"><span>Total Modal Awal</span><span class="tabular-nums">{{ $rp($d['total_awal']) }}</span></div>

                    <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">Laba Januari–{{ $this->periodeLabel() }}</span><span class="tabular-nums text-emerald-700">+ {{ $rp($d['laba']) }}</span></div>
                    <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">Bagi Hasil / Penggunaan Dana</span><span class="tabular-nums text-slate-700">{{ $rp($d['perubahan_dana']) }}</span></div>

                    <div class="mt-2 flex justify-between border-y-2 border-blue-200 py-2 text-base font-bold text-blue-700"><span>Modal Akhir per {{ $toText }}</span><span class="tabular-nums">{{ $rp($d['modal_akhir']) }}</span></div>
                </div>
                @break

            {{-- ============================ ARUS KAS ============================ --}}
            @case('arus-kas')
                @php($d = $this->arusKas())
                <div class="mx-auto max-w-3xl text-sm">
                    <div class="flex justify-between border-b border-slate-50 py-2"><span class="text-slate-600">Saldo Kas Awal</span><span class="tabular-nums text-slate-700">{{ $rp($d['kas_awal']) }}</span></div>
                    <div class="pt-3 pb-1 font-semibold text-slate-800">Arus Kas Periode</div>
                    <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">Aktivitas Operasi</span><span class="tabular-nums text-slate-700">{{ $rp($d['operasi']) }}</span></div>
                    <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">Aktivitas Investasi</span><span class="tabular-nums text-slate-700">{{ $rp($d['investasi']) }}</span></div>
                    <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="pl-4 text-slate-600">Aktivitas Pendanaan</span><span class="tabular-nums text-slate-700">{{ $rp($d['pendanaan']) }}</span></div>
                    <div class="flex justify-between border-y border-slate-200 bg-slate-50 px-1 py-2 font-semibold text-slate-800"><span>Kenaikan/(Penurunan) Kas Bersih</span><span class="tabular-nums">{{ $rp($d['arus_bersih']) }}</span></div>

                    <div class="mt-2 flex justify-between border-y-2 border-blue-200 py-2 text-base font-bold text-blue-700"><span>Saldo Kas Akhir</span><span class="tabular-nums">{{ $rp($d['kas_akhir']) }}</span></div>
                </div>
                @break

            {{-- ========================= LAPORAN KREDIT ========================= --}}
            @case('kredit')
                @php($d = $this->kredit())
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <div class="rounded-xl border border-t-4 border-slate-200 border-t-blue-500 p-3"><p class="text-[11px] font-semibold uppercase text-slate-500">Total Pokok Pinjaman</p><p class="mt-1 text-lg font-bold tabular-nums text-blue-700">{{ $rp($d['total_pokok']) }}</p></div>
                    <div class="rounded-xl border border-t-4 border-slate-200 border-t-amber-500 p-3"><p class="text-[11px] font-semibold uppercase text-slate-500">Total Sisa Pokok</p><p class="mt-1 text-lg font-bold tabular-nums text-amber-700">{{ $rp($d['total_sisa']) }}</p></div>
                    <div class="rounded-xl border border-t-4 border-slate-200 border-t-emerald-500 p-3"><p class="text-[11px] font-semibold uppercase text-slate-500">Pendapatan Bunga</p><p class="mt-1 text-lg font-bold tabular-nums text-emerald-700">{{ $rp($d['pendapatan_bunga']) }}</p></div>
                    <div class="rounded-xl border border-t-4 border-slate-200 border-t-blue-500 p-3"><p class="text-[11px] font-semibold uppercase text-slate-500">Pinjaman Aktif</p><p class="mt-1 text-lg font-bold tabular-nums text-blue-700">{{ $d['aktif'] }}</p></div>
                    <div class="rounded-xl border border-t-4 border-slate-200 border-t-rose-500 p-3"><p class="text-[11px] font-semibold uppercase text-slate-500">Kredit Terlambat</p><p class="mt-1 text-lg font-bold tabular-nums text-rose-600">{{ $d['terlambat'] }}</p></div>
                </div>

                <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <th class="px-3 py-3">No. Pinjaman</th><th class="px-3 py-3">Peminjam</th><th class="px-3 py-3">Tgl Pencairan</th>
                                <th class="px-3 py-3 text-right">Pokok</th><th class="px-3 py-3 text-right">Total Bunga</th><th class="px-3 py-3 text-center">Tenor</th>
                                <th class="px-3 py-3 text-right">Pokok Dibayar</th><th class="px-3 py-3 text-right">Bunga Dibayar</th><th class="px-3 py-3 text-right">Sisa Pokok</th><th class="px-3 py-3">Angsuran Berikutnya</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($d['rows'] as $r)
                                <tr class="{{ $r['terlambat'] ? 'bg-rose-50/40' : '' }}">
                                    <td class="px-3 py-2.5 font-medium text-slate-900"><a href="{{ route('loans.show', $r['id']) }}" wire:navigate class="text-emerald-700 hover:underline">{{ $r['nomor'] }}</a></td>
                                    <td class="px-3 py-2.5 text-slate-600">{{ $r['peminjam'] }}</td>
                                    <td class="px-3 py-2.5 tabular-nums text-slate-500">{{ $r['tanggal'] }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ $rp($r['pokok']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ $rp($r['bunga']) }}</td>
                                    <td class="px-3 py-2.5 text-center tabular-nums text-slate-500">{{ $r['tenor'] }} bln</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ $rp($r['pokok_dibayar']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ $rp($r['bunga_dibayar']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums font-medium text-slate-900">{{ $rp($r['sisa_pokok']) }}</td>
                                    <td class="px-3 py-2.5 tabular-nums {{ $r['terlambat'] ? 'font-medium text-rose-600' : 'text-slate-500' }}">{{ $r['next'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="px-3 py-10 text-center text-slate-500">Belum ada pinjaman tercairkan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @break

            {{-- ======================= LAPORAN OMZET DESA ======================= --}}
            @case('omzet-desa')
                @php($d = $this->omzet())
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-xl border border-t-4 border-slate-200 border-t-emerald-500 p-3"><p class="text-[11px] font-semibold uppercase text-slate-500">Omzet Usaha Dagang</p><p class="mt-1 text-lg font-bold tabular-nums text-emerald-700">{{ $rp($d['omzet_dagang']) }}</p></div>
                    <div class="rounded-xl border border-t-4 border-slate-200 border-t-blue-500 p-3"><p class="text-[11px] font-semibold uppercase text-slate-500">Pendapatan Bunga Kredit</p><p class="mt-1 text-lg font-bold tabular-nums text-blue-700">{{ $rp($d['bunga_kredit']) }}</p></div>
                    <div class="rounded-xl border border-t-4 border-slate-200 border-t-blue-500 p-3"><p class="text-[11px] font-semibold uppercase text-slate-500">Pendapatan Bunga Bank</p><p class="mt-1 text-lg font-bold tabular-nums text-blue-700">{{ $rp($d['bunga_bank']) }}</p></div>
                    <div class="rounded-xl border border-t-4 border-slate-200 border-t-emerald-500 p-3"><p class="text-[11px] font-semibold uppercase text-slate-500">Total Omzet BUMDes</p><p class="mt-1 text-lg font-bold tabular-nums text-emerald-700">{{ $rp($d['total']) }}</p></div>
                </div>

                <div class="mt-6 mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-800">Histori Laporan Omzet Desa</h3>
                    <a href="{{ route('reports.laporan-desa') }}" wire:navigate
                       class="no-print inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">
                        Buka &amp; Ajukan ke Pimpinan
                    </a>
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <th class="px-3 py-3">No. Laporan</th><th class="px-3 py-3">Periode</th><th class="px-3 py-3 text-right">Total Omzet</th><th class="px-3 py-3">Diajukan</th><th class="px-3 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($d['histori'] as $lap)
                                <tr>
                                    <td class="px-3 py-2.5 font-medium text-slate-900">{{ $lap->nomor_laporan }}</td>
                                    <td class="px-3 py-2.5 text-slate-600">{{ $lap->periode ? (($bulanList[$lap->periode->bulan] ?? $lap->periode->bulan).' '.$lap->periode->tahun) : '—' }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ $rp($lap->total_omzet) }}</td>
                                    <td class="px-3 py-2.5 tabular-nums text-slate-500">{{ $lap->submitted_at?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-3 py-2.5">
                                        @php($s = $lap->status->value)
                                        <x-ui.badge :color="in_array($s, ['disetujui','dikirim','diterima']) ? 'emerald' : ($s === 'diajukan' ? 'amber' : 'slate')">{{ ucfirst(str_replace('_', ' ', $s)) }}</x-ui.badge>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-10 text-center text-slate-500">Belum ada laporan omzet desa.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @break

            {{-- ============================ BUKU BESAR ============================ --}}
            @case('buku-besar')
                @php($bb = $this->bukuBesar())
                @php($dk = fn ($v) => ((float) $v >= 0 ? $rp(abs((float) $v)).' D' : $rp(abs((float) $v)).' K'))
                <div class="space-y-5 text-sm">
                    @forelse ($bb as $akun)
                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="flex items-center justify-between gap-3 bg-slate-50 px-4 py-2.5">
                                <span class="font-semibold text-slate-800">{{ $akun['akun']->kode_akun }} — {{ $akun['akun']->nama_akun }}</span>
                                <span class="shrink-0 text-xs text-slate-500">Saldo awal: <span class="font-medium tabular-nums text-slate-700">{{ $dk($akun['saldo_awal']) }}</span></span>
                            </div>
                            @if ($akun['entries'] !== [])
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-slate-100">
                                        <thead class="bg-white">
                                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                                <th class="px-4 py-2">Tanggal</th><th class="px-4 py-2">Nomor</th><th class="px-4 py-2">Keterangan</th>
                                                <th class="px-4 py-2 text-right">Debit</th><th class="px-4 py-2 text-right">Kredit</th><th class="px-4 py-2 text-right">Saldo</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            @foreach ($akun['entries'] as $e)
                                                <tr>
                                                    <td class="whitespace-nowrap px-4 py-1.5 tabular-nums text-slate-500">{{ Carbon::parse($e['tanggal'])->format('d/m/Y') }}</td>
                                                    <td class="whitespace-nowrap px-4 py-1.5 text-slate-600">{{ $e['nomor'] }}</td>
                                                    <td class="px-4 py-1.5 text-slate-600">{{ $e['keterangan'] }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums text-slate-700">{{ (float) $e['debit'] ? $rp($e['debit']) : '' }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums text-slate-700">{{ (float) $e['kredit'] ? $rp($e['kredit']) : '' }}</td>
                                                    <td class="whitespace-nowrap px-4 py-1.5 text-right tabular-nums text-slate-600">{{ $dk($e['saldo']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-slate-50 font-semibold text-slate-800">
                                                <td class="px-4 py-2" colspan="5">Saldo Akhir</td>
                                                <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums">{{ $dk($akun['saldo_akhir']) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @else
                                <p class="px-4 py-3 text-xs text-slate-400">Tidak ada mutasi; saldo tetap.</p>
                            @endif
                        </div>
                    @empty
                        <p class="py-10 text-center text-slate-500">Belum ada mutasi pada periode ini.</p>
                    @endforelse
                </div>
                @break
        @endswitch

        {{-- Tanda tangan --}}
        <div class="mt-10 flex justify-end">
            <div class="text-center text-sm">
                <p class="text-slate-600">{{ $org['kota_ttd'] }}, {{ $toText }}</p>
                <p class="text-slate-600">{{ $org['jabatan_ttd'] }}</p>
                <div class="h-16"></div>
                <p class="font-semibold text-slate-900 underline">{{ auth()->user()->nama ?? '—' }}</p>
            </div>
        </div>
    </div>
</div>
