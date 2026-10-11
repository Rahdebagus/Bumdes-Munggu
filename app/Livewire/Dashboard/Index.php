<?php

namespace App\Livewire\Dashboard;

use App\Domain\Reporting\ReportService;
use App\Enums\AngsuranStatus;
use App\Enums\StatusUmum;
use App\Enums\UnitJenisUsaha;
use App\Models\Angsuran;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Index extends Component
{
    use AuthorizesRequests;

    private const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** Periode aktif = periode terbaru (bulan berjalan). Null bila belum ada periode. */
    public function periodeAktif(): ?PeriodeAkuntansi
    {
        return PeriodeAkuntansi::orderByDesc('tahun')->orderByDesc('bulan')->first();
    }

    /**
     * KPI dihitung dari ReportService (ledger efektif), bukan angka acak atau
     * hardcoded (dokumen07 §Dashboard, dokumen10). Nilai periode berjalan memakai
     * rentang bulan periode aktif; laba kumulatif memakai rentang sejak cutover.
     * Tanpa periode ber-opening terkunci → empty state jujur ('—').
     *
     * @return array<int, array{label: string, value: string, hint: string, color: string, icon: string}>
     */
    public function kpis(): array
    {
        $month = $this->monthWindow();

        if ($month === null) {
            $kosong = 'Belum ada periode dengan saldo awal terkunci';

            return [
                ['label' => 'Saldo Kas dan Bank', 'value' => '—', 'hint' => $kosong, 'color' => 'blue', 'icon' => 'wallet'],
                ['label' => 'Omzet Usaha Dagang', 'value' => '—', 'hint' => 'Menunggu transaksi terposting', 'color' => 'emerald', 'icon' => 'store'],
                ['label' => 'Pendapatan Kredit', 'value' => '—', 'hint' => 'Menunggu transaksi terposting', 'color' => 'blue', 'icon' => 'percent'],
                ['label' => 'Beban Periode', 'value' => '—', 'hint' => 'Menunggu transaksi terposting', 'color' => 'rose', 'icon' => 'trending-down'],
                ['label' => 'Laba Bersih', 'value' => '—', 'hint' => 'Menunggu transaksi terposting', 'color' => 'emerald', 'icon' => 'trending-up'],
                ['label' => 'Sisa Piutang Pinjaman', 'value' => '—', 'hint' => 'Pokok belum dibayar', 'color' => 'amber', 'icon' => 'coins'],
            ];
        }

        [$from, $to, $label] = $month;
        $svc = app(ReportService::class);
        $lr = $svc->labaRugi($from, $to);
        $neraca = $svc->neraca($to, cutoverFrom: $this->cutover());
        $saldo = $svc->systemKeyBalances($to);
        $kas = $saldo['cash'] ?? '0';
        $bank = $saldo['bank'] ?? '0';

        $beban = (float) $lr['beban_operasional'] + (float) $lr['penyusutan'] + (float) $lr['beban_selisih_persediaan'];
        $labaKumulatif = $svc->labaBersih($this->cutover(), $to);
        $sisaPokok = $svc->kredit($to)['total_sisa_pokok'];

        $unitDagang = $this->unitDagangNama();

        return [
            [
                'label' => 'Saldo Kas dan Bank',
                'value' => $this->rupiah($neraca['kas_bank']),
                'hint' => 'Kas '.$this->rupiah($kas).' + Bank '.$this->rupiah($bank),
                'color' => 'blue', 'icon' => 'wallet',
            ],
            [
                'label' => 'Omzet Usaha Dagang',
                'value' => $this->rupiah($lr['penjualan_bersih']),
                'hint' => ($unitDagang !== '' ? $unitDagang.' · ' : '').$label,
                'color' => 'emerald', 'icon' => 'store',
            ],
            [
                'label' => 'Pendapatan Kredit',
                'value' => $this->rupiah($lr['pendapatan_kredit']),
                'hint' => 'Bunga + denda · '.$label,
                'color' => 'blue', 'icon' => 'percent',
            ],
            [
                'label' => 'Beban Periode',
                'value' => $this->rupiah((string) $beban),
                'hint' => 'Operasional + penyusutan + selisih stok',
                'color' => 'rose', 'icon' => 'trending-down',
            ],
            [
                'label' => 'Laba Bersih',
                'value' => $this->rupiah($lr['laba_bersih']),
                'hint' => 'Kumulatif s/d '.$label.': '.$this->rupiah($labaKumulatif),
                'color' => 'emerald', 'icon' => 'trending-up',
            ],
            [
                'label' => 'Sisa Piutang Pinjaman',
                'value' => $this->rupiah($sisaPokok),
                'hint' => 'Pokok belum dibayar',
                'color' => 'amber', 'icon' => 'coins',
            ],
        ];
    }

    /**
     * Komposisi pendapatan periode berjalan: penjualan bersih per unit dagang +
     * pendapatan kredit + pendapatan lain. Hanya baris bernilai > 0.
     *
     * @return array{total: string, rows: array<int, array{label: string, value: string, percent: float}>}
     */
    public function komposisi(): array
    {
        $month = $this->monthWindow();
        if ($month === null) {
            return ['total' => '0', 'rows' => []];
        }

        [$from, $to] = $month;
        $svc = app(ReportService::class);

        $entries = [];
        foreach (UnitUsaha::where('jenis_usaha', UnitJenisUsaha::Dagang->value)->orderBy('nama_unit')->get() as $unit) {
            $nilai = $svc->labaRugi($from, $to, $unit->id)['penjualan_bersih'];
            if ((float) $nilai > 0) {
                $entries[] = ['label' => $unit->nama_unit, 'value' => $nilai];
            }
        }

        $konsolidasi = $svc->labaRugi($from, $to);
        if ((float) $konsolidasi['pendapatan_kredit'] > 0) {
            $entries[] = ['label' => 'Bunga Kredit', 'value' => $konsolidasi['pendapatan_kredit']];
        }
        if ((float) $konsolidasi['pendapatan_lain'] > 0) {
            $entries[] = ['label' => 'Pendapatan Lain', 'value' => $konsolidasi['pendapatan_lain']];
        }

        usort($entries, fn ($a, $b) => (float) $b['value'] <=> (float) $a['value']);
        $total = array_sum(array_map(fn ($e) => (float) $e['value'], $entries));
        $maks = max(array_map(fn ($e) => (float) $e['value'], $entries ?: [['value' => 1]]));

        $rows = array_map(fn ($e) => [
            'label' => $e['label'],
            'value' => $e['value'],
            'percent' => $maks > 0 ? round((float) $e['value'] / $maks * 100, 1) : 0.0,
        ], $entries);

        return ['total' => (string) $total, 'rows' => $rows];
    }

    /**
     * Rekonsiliasi kas periode berjalan: saldo awal + masuk − keluar = saldo akhir.
     *
     * @return array{saldo_awal: string, masuk: string, keluar: string, kenaikan: string, saldo_akhir: string}|null
     */
    public function rekonsiliasiKas(): ?array
    {
        $month = $this->monthWindow();
        if ($month === null) {
            return null;
        }

        [$from, $to] = $month;
        $svc = app(ReportService::class);
        $arus = $svc->arusKas($from, $to);
        $gross = $svc->cashGrossFlow($from, $to);

        return [
            'saldo_awal' => $arus['kas_awal'],
            'masuk' => $gross['masuk'],
            'keluar' => $gross['keluar'],
            'kenaikan' => $arus['arus_bersih'],
            'saldo_akhir' => $arus['kas_akhir'],
        ];
    }

    /**
     * Ringkasan posisi: total aktiva, kewajiban, modal (as of akhir periode).
     *
     * @return array{aktiva: string, kewajiban: string, modal: string}|null
     */
    public function ringkasanNeraca(): ?array
    {
        $month = $this->monthWindow();
        if ($month === null) {
            return null;
        }

        [, $to] = $month;
        $n = app(ReportService::class)->neraca($to, cutoverFrom: $this->cutover());

        return [
            'aktiva' => $n['total_aset'],
            'kewajiban' => $n['utang'],
            'modal' => $n['total_ekuitas'],
        ];
    }

    /**
     * Transaksi terbaru lintas unit (semua status), untuk pemantauan.
     *
     * @return Collection<int, Transaksi>
     */
    public function transaksiTerbaru(): Collection
    {
        return Transaksi::with(['unitUsaha', 'pihak'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    /**
     * Angsuran jatuh tempo: belum lunas, diurut tanggal jatuh tempo terdekat.
     *
     * @return Collection<int, Angsuran>
     */
    public function angsuranJatuhTempo(): Collection
    {
        return Angsuran::with(['pinjaman.peminjam', 'pinjaman.transaksiPencairan'])
            ->whereIn('status', [
                AngsuranStatus::BelumDibayar->value,
                AngsuranStatus::Sebagian->value,
                AngsuranStatus::Terlambat->value,
            ])
            ->whereHas('pinjaman', fn ($q) => $q->whereNotNull('transaksi_id'))
            ->orderBy('tanggal_jatuh_tempo')
            ->limit(5)
            ->get();
    }

    /**
     * Produk dengan stok menipis (stok terkini ≤ stok minimum), unit aktif.
     *
     * @return Collection<int, Produk>
     */
    public function stokMenipis(): Collection
    {
        return Produk::with('unitUsaha')
            ->where('status', StatusUmum::Aktif->value)
            ->where('stok_minimum', '>', 0)
            ->whereColumn('stok_terkini', '<=', 'stok_minimum')
            ->orderByRaw('(stok_terkini - stok_minimum) asc')
            ->limit(5)
            ->get();
    }

    /** Rentang bulan periode aktif + label (mis. "Juni 2026"). Null bila belum ada opening terkunci. */
    private function monthWindow(): ?array
    {
        if (! PeriodeAkuntansi::whereNotNull('opening_locked_at')->exists()) {
            return null;
        }

        $periode = $this->periodeAktif();
        if ($periode === null) {
            return null;
        }

        return [
            $periode->tanggal_mulai->toDateString(),
            $periode->tanggal_selesai->toDateString(),
            (self::BULAN[$periode->bulan] ?? ('Bln '.$periode->bulan)).' '.$periode->tahun,
        ];
    }

    /** Tanggal cutover: awal periode ber-opening terkunci paling awal. */
    private function cutover(): string
    {
        $cutover = PeriodeAkuntansi::whereNotNull('opening_locked_at')->orderBy('tanggal_mulai')->first();

        return $cutover?->tanggal_mulai?->toDateString() ?? now()->startOfYear()->toDateString();
    }

    /** Nama unit dagang digabung (" + ") untuk sub-caption kartu omzet. */
    private function unitDagangNama(): string
    {
        return UnitUsaha::where('jenis_usaha', UnitJenisUsaha::Dagang->value)
            ->orderBy('nama_unit')
            ->pluck('nama_unit')
            ->implode(' + ');
    }

    private function rupiah(string $value): string
    {
        return 'Rp '.number_format((float) $value, 2, ',', '.');
    }

    /**
     * Mutation khusus Admin dengan policy server (Pimpinan tertolak meski tombol
     * tidak terlihat). Pola untuk seluruh mutation Livewire.
     */
    public function clearReportCache(): void
    {
        $this->authorize('perform-admin-mutation');

        Cache::flush();

        session()->flash('status', 'Cache aplikasi dibersihkan.');
    }

    public function render()
    {
        return view('livewire.dashboard.index');
    }
}
