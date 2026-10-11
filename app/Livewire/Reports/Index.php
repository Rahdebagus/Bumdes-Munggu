<?php

namespace App\Livewire\Reports;

use App\Domain\Reporting\ReportDataset;
use App\Domain\Reporting\ReportService;
use App\Domain\Reporting\StatementService;
use App\Models\Akun;
use App\Models\LaporanDesa;
use App\Models\PeriodeAkuntansi;
use App\Models\Pinjaman;
use App\Models\UnitUsaha;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Laporan Keuangan')]
class Index extends Component
{
    public const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    #[Url]
    public string $type = 'laba-rugi';

    #[Url]
    public ?int $bulan = null;

    #[Url]
    public ?int $tahun = null;

    #[Url]
    public ?int $unitId = null;

    public function mount(): void
    {
        if ($this->bulan === null || $this->tahun === null) {
            $aktif = PeriodeAkuntansi::orderByDesc('tahun')->orderByDesc('bulan')->first();
            $this->bulan ??= $aktif?->bulan ?? (int) now()->month;
            $this->tahun ??= $aktif?->tahun ?? (int) now()->year;
        }
    }

    /** Rentang tanggal [from, to] dari periode terpilih (atau awal–akhir bulan). */
    public function range(): array
    {
        $periode = PeriodeAkuntansi::where('tahun', $this->tahun)->where('bulan', $this->bulan)->first();
        if ($periode) {
            return [$periode->tanggal_mulai->toDateString(), $periode->tanggal_selesai->toDateString()];
        }

        $awal = Carbon::create($this->tahun, $this->bulan, 1)->startOfMonth();

        return [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()];
    }

    /** Cutover: awal periode ber-opening terkunci paling awal. */
    public function cutover(): string
    {
        $cutover = PeriodeAkuntansi::whereNotNull('opening_locked_at')->orderBy('tanggal_mulai')->first();

        return $cutover?->tanggal_mulai?->toDateString() ?? Carbon::create($this->tahun, 1, 1)->toDateString();
    }

    public function periodeLabel(): string
    {
        return (self::BULAN[$this->bulan] ?? $this->bulan).' '.$this->tahun;
    }

    public function title(): string
    {
        return ReportDataset::TYPES[$this->type] ?? 'Laporan';
    }

    public function unitLabel(): string
    {
        return $this->unitId
            ? (UnitUsaha::find($this->unitId)?->nama_unit ?? "Unit #{$this->unitId}")
            : 'Semua Unit';
    }

    // ---- Dataset per jenis laporan (semua dari database) --------------------

    public function labaRugi(): array
    {
        [$from, $to] = $this->range();

        return app(StatementService::class)->labaRugi($from, $to, $this->unitId, $this->cutover());
    }

    public function neraca(): array
    {
        [, $to] = $this->range();

        return app(StatementService::class)->neraca($to, $this->unitId, $this->cutover());
    }

    public function perubahanModal(): array
    {
        [, $to] = $this->range();

        return app(StatementService::class)->perubahanModal($to, $this->unitId, $this->cutover());
    }

    public function arusKas(): array
    {
        [$from, $to] = $this->range();

        return app(ReportService::class)->arusKas($from, $to, $this->unitId);
    }

    /** Buku besar: saldo awal + mutasi per akun dengan saldo berjalan. */
    public function bukuBesar(): array
    {
        [$from, $to] = $this->range();

        return app(ReportService::class)->bukuBesar($from, $to, $this->unitId);
    }

    /** KPI + baris tabel untuk Laporan Kredit. */
    public function kredit(): array
    {
        [$from, $to] = $this->range();
        $today = now()->toDateString();

        $loans = Pinjaman::with(['peminjam', 'angsuran', 'transaksiPencairan'])
            ->whereHas('transaksiPencairan', fn ($q) => $q->whereNotNull('posted_at')->whereDate('tanggal', '<=', $to))
            ->when($this->unitId, fn ($q) => $q->where('unit_usaha_id', $this->unitId))
            ->orderBy('id')
            ->get();

        $rows = [];
        $totalPokok = 0.0;
        $totalSisa = 0.0;
        $aktif = 0;
        $terlambat = 0;

        foreach ($loans as $loan) {
            $belum = $loan->angsuran->filter(fn ($a) => $a->status->value !== 'lunas');
            $isTerlambat = $loan->status->value === 'macet'
                || $belum->contains(fn ($a) => $a->tanggal_jatuh_tempo && $a->tanggal_jatuh_tempo->toDateString() < $today);
            $next = $belum->sortBy('tanggal_jatuh_tempo')->first();

            $rows[] = [
                'nomor' => $loan->transaksiPencairan?->nomor_transaksi ?? ('PJM-'.$loan->id),
                'peminjam' => $loan->peminjam?->nama ?? '—',
                'tanggal' => $loan->tanggal_pinjaman?->format('d/m/Y'),
                'pokok' => (string) $loan->pokok_pinjaman,
                'bunga' => (string) $loan->total_bunga,
                'persentase' => rtrim(rtrim(number_format((float) $loan->persentase_bunga, 2, ',', '.'), '0'), ','),
                'tenor' => (int) $loan->tenor,
                'pokok_dibayar' => (string) $loan->angsuran->sum('pokok_dibayar'),
                'bunga_dibayar' => (string) $loan->angsuran->sum('bunga_dibayar'),
                'sisa_pokok' => (string) $loan->sisa_pokok,
                'next' => $next?->tanggal_jatuh_tempo?->format('d/m/Y') ?? '—',
                'terlambat' => $isTerlambat,
                'id' => $loan->id,
            ];

            $totalPokok += (float) $loan->pokok_pinjaman;
            $totalSisa += (float) $loan->sisa_pokok;
            $aktif += $loan->status->value === 'aktif' ? 1 : 0;
            $terlambat += $isTerlambat ? 1 : 0;
        }

        $pendapatanBunga = app(ReportService::class)->labaRugi($from, $to, $this->unitId)['pendapatan_kredit'];

        return [
            'rows' => $rows,
            'total_pokok' => (string) $totalPokok,
            'total_sisa' => (string) $totalSisa,
            'pendapatan_bunga' => $pendapatanBunga,
            'aktif' => $aktif,
            'terlambat' => $terlambat,
        ];
    }

    /** KPI + histori untuk Laporan Omzet Desa. */
    public function omzet(): array
    {
        [$from, $to] = $this->range();
        $lr = app(ReportService::class)->labaRugi($from, $to, $this->unitId);

        $dagang = (float) $lr['penjualan_bersih'];
        $bungaKredit = (float) $lr['pendapatan_kredit'];

        // Pendapatan bunga bank: akun bernama tanpa system_key (kredit-normal).
        $bungaBank = 0.0;
        $akunBungaBank = Akun::where('kode_akun', '4-4050')->value('id');
        if ($akunBungaBank) {
            $net = app(ReportService::class)->accountNet($from, $to, false, $this->unitId)[$akunBungaBank] ?? '0';
            $bungaBank = -(float) $net;
        }

        $histori = LaporanDesa::with('periode')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return [
            'omzet_dagang' => (string) $dagang,
            'bunga_kredit' => (string) $bungaKredit,
            'bunga_bank' => (string) $bungaBank,
            'total' => (string) ($dagang + $bungaKredit + $bungaBank),
            'histori' => $histori,
        ];
    }

    public function units()
    {
        return UnitUsaha::orderBy('nama_unit')->get();
    }

    public function tahunOptions(): array
    {
        $years = PeriodeAkuntansi::query()->distinct()->orderByDesc('tahun')->pluck('tahun')->all();
        $now = (int) now()->year;
        $years = array_values(array_unique(array_merge($years, [$now, $this->tahun])));
        rsort($years);

        return $years;
    }

    public function typeOptions(): array
    {
        return ReportDataset::TYPES;
    }

    public function render()
    {
        return view('livewire.reports.index');
    }
}
