<div class="space-y-6">
    {{-- Petunjuk pengisian + unduh format --}}
    <div x-data="{ buka: true }" class="rounded-xl border border-blue-200 bg-blue-50 text-sm text-blue-950">
        <button type="button" @click="buka = !buka" class="flex w-full items-center justify-between gap-3 px-5 py-3 text-left">
            <span class="font-semibold">Petunjuk: cara mengisi saldo awal</span>
            <span class="text-xs text-blue-700" x-text="buka ? 'Sembunyikan' : 'Tampilkan'"></span>
        </button>
        <div x-show="buka" x-collapse class="space-y-4 border-t border-blue-200 px-5 py-4">
            <p>Saldo awal adalah posisi <strong>neraca</strong> (harta, utang, modal) pada tanggal <strong>cutover</strong> — hari terakhir sebelum mulai mencatat di aplikasi.
                Ambil angkanya dari Neraca Excel terakhir. Akun pendapatan, HPP dan biaya <strong>tidak</strong> diisi.</p>

            <ol class="list-decimal space-y-1 pl-5">
                <li>Pilih / buat <strong>periode cutover</strong> di bawah (mis. Januari 2026).</li>
                <li><strong>Unduh format</strong> lalu isi lembar <code class="rounded bg-white px-1">saldo_awal</code> — satu baris per akun per unit. Daftar kode akun & unit ada di lembar lain pada file yang sama.</li>
                <li>Unggah file di kartu <strong>Impor Saldo Awal</strong> → <em>Preview</em> → periksa → <em>Simpan</em>.</li>
                <li>Untuk akun kontrol (Persediaan, Piutang, Utang, Pinjaman, Inventaris) isi <strong>Rincian Akun Kontrol</strong> sampai jumlahnya sama.</li>
                <li>Bila badge <em>Debit = Kredit</em> dan <em>Rincian kontrol cocok</em> hijau, klik <strong>Kunci Saldo Awal</strong>. Setelah dikunci, transaksi bisa diposting.</li>
            </ol>

            <div class="overflow-x-auto rounded-lg border border-blue-200 bg-white">
                <table class="min-w-full text-xs text-slate-700">
                    <thead class="bg-slate-50 text-left font-semibold text-slate-500">
                        <tr><th class="px-3 py-2">Kolom</th><th class="px-3 py-2">Isi dengan</th><th class="px-3 py-2">Contoh</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr><td class="px-3 py-1.5 font-mono">kode_akun</td><td class="px-3 py-1.5">Kode akun dari lembar "Daftar Akun" (format <strong>teks</strong>)</td><td class="px-3 py-1.5 font-mono">1.1.01.01</td></tr>
                        <tr><td class="px-3 py-1.5 font-mono">unit_kode</td><td class="px-3 py-1.5">Kode unit dari lembar "Daftar Unit"; bila tidak dipisah per unit pakai satu unit saja</td><td class="px-3 py-1.5 font-mono">UNIT-DAGANG</td></tr>
                        <tr><td class="px-3 py-1.5 font-mono">saldo_debit</td><td class="px-3 py-1.5">Akun harta: Kas, Bank, Piutang, Persediaan, Inventaris. Angka polos tanpa titik/Rp</td><td class="px-3 py-1.5 font-mono">10000000</td></tr>
                        <tr><td class="px-3 py-1.5 font-mono">saldo_kredit</td><td class="px-3 py-1.5">Akun utang, modal, dana-dana, akumulasi penyusutan</td><td class="px-3 py-1.5 font-mono">50000000</td></tr>
                        <tr><td class="px-3 py-1.5 font-mono">keterangan</td><td class="px-3 py-1.5">Bebas, mis. sumber angka</td><td class="px-3 py-1.5">Rekening koran 31/12</td></tr>
                    </tbody>
                </table>
            </div>

            <p><strong>Contoh seimbang:</strong> Kas Tunai 10.000.000 (debit) + Kas di Bank 40.000.000 (debit) = Modal Desa Munggu 50.000.000 (kredit).</p>
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-900"><strong>Paling mudah untuk mulai:</strong> isi Kas, Bank, Modal &amp; Dana saja. Stok barang dicatat sebagai stok awal produk
                (<code>php artisan produk:import produk.xlsx --stok-awal=YYYY-MM-DD</code>, otomatis D Persediaan — K Modal). Jangan isi Persediaan di sini <em>dan</em> stok awal produk sekaligus — akan tercatat dua kali.</p>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('opening-balance.template', 'xlsx') }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800">Unduh format Excel (.xlsx)</a>
                <a href="{{ route('opening-balance.template', 'csv') }}" class="inline-flex items-center gap-2 rounded-lg border border-blue-300 bg-white px-4 py-2 font-medium text-blue-800 hover:bg-blue-100">Unduh format CSV</a>
            </div>
        </div>
    </div>

    {{-- Pemilihan / pembuatan periode cutover --}}
    <x-ui.card title="Periode Cutover" subtitle="Satu tanggal cutover per implementasi (default demo: awal tahun)">
        <div class="flex flex-wrap items-end gap-4">
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Periode</label>
                <select wire:model.live="periodeId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— pilih periode —</option>
                    @foreach ($periods as $p)
                        <option value="{{ $p->id }}">{{ str_pad($p->bulan, 2, '0', STR_PAD_LEFT) }}/{{ $p->tahun }} ({{ $p->status->value }}{{ $p->opening_locked_at ? ', terkunci' : '' }})</option>
                    @endforeach
                </select>
                @error('periodeId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @can('perform-admin-mutation')
                <div class="flex items-end gap-2 border-l border-slate-200 pl-4">
                    <div class="space-y-1">
                        <label class="block text-xs text-slate-500">Bulan</label>
                        <input type="number" min="1" max="12" wire:model="newBulan" class="w-20 rounded-lg border border-slate-300 px-2 py-2 text-sm">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs text-slate-500">Tahun</label>
                        <input type="number" wire:model="newTahun" class="w-28 rounded-lg border border-slate-300 px-2 py-2 text-sm">
                    </div>
                    <x-ui.button variant="secondary" wire:click="createPeriode">Buat/pilih periode</x-ui.button>
                </div>
            @endcan
        </div>
    </x-ui.card>

    @if ($periode)
        @php $locked = $periode->opening_locked_at !== null; @endphp

        {{-- Import Excel --}}
        @can('perform-admin-mutation')
            @unless ($locked)
                <x-ui.card title="Impor Saldo Awal (Excel/CSV)" subtitle="Kolom: kode_akun, unit_kode, saldo_debit, saldo_kredit, keterangan">
                    <div class="flex flex-wrap items-end gap-3">
                        <input type="file" wire:model="file" accept=".xlsx,.xls,.csv" class="text-sm">
                        <x-ui.button variant="secondary" wire:click="preview" wire:loading.attr="disabled">Preview</x-ui.button>
                        <div wire:loading wire:target="file" class="text-sm text-slate-400">Mengunggah…</div>
                        <a href="{{ route('opening-balance.template', 'xlsx') }}" class="ml-auto text-sm font-medium text-blue-700 hover:underline">Belum punya file? Unduh format Excel</a>
                    </div>
                    @error('file') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('import') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                    @if ($preview)
                        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">#</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Akun</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Debit</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Kredit</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Catatan / Error</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($preview['rows'] as $row)
                                        <tr class="{{ $row['errors'] ? 'bg-red-50' : '' }}">
                                            <td class="px-3 py-1.5">{{ $row['no'] }}</td>
                                            <td class="px-3 py-1.5">{{ $row['kode_akun'] }} {{ $row['nama_akun'] ? '— '.$row['nama_akun'] : '' }}</td>
                                            <td class="px-3 py-1.5 text-right tabular-nums">{{ $row['saldo_debit'] }}</td>
                                            <td class="px-3 py-1.5 text-right tabular-nums">{{ $row['saldo_kredit'] }}</td>
                                            <td class="px-3 py-1.5 text-red-600">{{ implode('; ', $row['errors']) ?: $row['keterangan'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-slate-50 font-medium">
                                    <tr>
                                        <td class="px-3 py-2" colspan="2">Total ({{ $preview['row_count'] }} baris)</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $preview['total_debit'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $preview['total_kredit'] }}</td>
                                        <td class="px-3 py-2">
                                            @if ($preview['balanced'])
                                                <x-ui.badge color="emerald">seimbang</x-ui.badge>
                                            @else
                                                <x-ui.badge color="red">tidak seimbang</x-ui.badge>
                                            @endif
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-3 flex items-center gap-3">
                            <x-ui.button wire:click="commit" :disabled="$preview['has_errors']">
                                Commit {{ $preview['row_count'] }} baris
                            </x-ui.button>
                            @if ($preview['has_errors'])
                                <span class="text-sm text-red-600">Ada baris bermasalah — perbaiki file lalu preview ulang.</span>
                            @endif
                        </div>
                    @endif
                </x-ui.card>
            @endunless
        @endcan

        {{-- Rekonsiliasi + Lock --}}
        <x-ui.card title="Rekonsiliasi & Kunci" subtitle="Saldo harus seimbang dan rincian == akun kontrol sebelum dikunci">
            <div class="flex flex-wrap items-center gap-3">
                @if ($recon['balanced'])
                    <x-ui.badge color="emerald">Debit = Kredit ({{ $recon['total_debit'] }})</x-ui.badge>
                @else
                    <x-ui.badge color="red">Tidak seimbang: D {{ $recon['total_debit'] }} / K {{ $recon['total_kredit'] }}</x-ui.badge>
                @endif

                @if (empty($recon['control_issues']))
                    <x-ui.badge color="emerald">Rincian kontrol cocok</x-ui.badge>
                @else
                    <x-ui.badge color="red">{{ count($recon['control_issues']) }} akun kontrol tidak cocok</x-ui.badge>
                @endif

                @if ($locked)
                    <x-ui.badge color="blue">Terkunci {{ $periode->opening_locked_at?->format('d M Y H:i') }}</x-ui.badge>
                @elseif (auth()->user()->isAdmin())
                    <x-ui.button wire:click="lock" :disabled="! $recon['ok']">Kunci Saldo Awal</x-ui.button>
                @endif
            </div>
            @error('lock') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

            @if (! empty($recon['control_issues']))
                <ul class="mt-3 list-disc pl-5 text-sm text-red-600">
                    @foreach ($recon['control_issues'] as $issue)
                        <li>{{ $issue['akun'] }} ({{ $issue['system_key'] }}): kontrol {{ $issue['kontrol'] }} ≠ rincian {{ $issue['rincian'] }}</li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        {{-- Daftar saldo awal --}}
        <x-ui.card title="Saldo Awal Tersimpan">
            <x-ui.table :headers="['Akun', 'Unit', 'Debit', 'Kredit', 'Rincian']"
                        :is-empty="$openingRows->isEmpty()" empty="Belum ada saldo awal untuk periode ini.">
                @foreach ($openingRows as $r)
                    <tr wire:key="saldo-{{ $r->id }}">
                        <td class="px-4 py-2">{{ $r->akun?->kode_akun }} — {{ $r->akun?->nama_akun }}</td>
                        <td class="px-4 py-2">{{ $r->unitUsaha?->nama_unit ?? 'Semua' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $r->saldo_debit }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $r->saldo_kredit }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-slate-500">{{ $r->rincian->count() ?: '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        {{-- Rincian akun kontrol --}}
        @if ($controlRows->isNotEmpty())
            <x-ui.card title="Rincian Akun Kontrol" subtitle="Jumlah rincian harus sama dengan saldo akun kontrol">
                @can('perform-admin-mutation')
                    @unless ($locked)
                        <div class="mb-4 grid grid-cols-1 gap-3 rounded-lg bg-slate-50 p-3 sm:grid-cols-5">
                            <select wire:model="rincianSaldoId" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                                <option value="">— akun kontrol —</option>
                                @foreach ($controlRows as $r)
                                    <option value="{{ $r->id }}">{{ $r->akun?->kode_akun }} {{ $r->akun?->nama_akun }}</option>
                                @endforeach
                            </select>
                            <select wire:model="rincianJenis" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                                @foreach ($jenisRincian as $j)
                                    <option value="{{ $j->value }}">{{ $j->value }}</option>
                                @endforeach
                            </select>
                            <input wire:model="rincianKode" placeholder="kode_referensi" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                            <input wire:model="rincianNominal" type="number" step="0.01" placeholder="nominal" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                            <x-ui.button variant="secondary" wire:click="addRincian">Tambah rincian</x-ui.button>
                        </div>
                        @error('rincianKode') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        @error('rincianSaldoId') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    @endunless
                @endcan

                <div class="space-y-2">
                    @foreach ($controlRows as $r)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="text-sm font-medium text-slate-800">{{ $r->akun?->kode_akun }} — {{ $r->akun?->nama_akun }}</p>
                            <ul class="mt-1 text-sm text-slate-600">
                                @forelse ($r->rincian as $rc)
                                    <li>{{ $rc->jenis->value }} · {{ $rc->kode_referensi }} · {{ $rc->nominal }}</li>
                                @empty
                                    <li class="text-slate-400">Belum ada rincian.</li>
                                @endforelse
                            </ul>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endif
    @endif
</div>
