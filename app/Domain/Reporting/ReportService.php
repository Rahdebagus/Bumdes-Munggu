<?php

namespace App\Domain\Reporting;

use App\Domain\Shared\Decimal;
use App\Models\Akun;
use App\Models\AlokasiPelunasan;
use App\Models\AsetTetap;
use App\Models\BuktiTransaksi;
use App\Models\MutasiStok;
use App\Models\Pinjaman;
use App\Models\Produk;
use App\Models\SaldoAwalAkun;
use App\Models\StokOpname;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Illuminate\Support\Carbon;

/**
 * Laporan dari ledger efektif = saldo awal (cutover terkunci) + jurnal
 * posted_at≠null pada rentang tanggal (dokumen10). Tidak memakai SUM header
 * transaksi. Semua nominal string decimal 2.
 */
class ReportService
{
    /** Net (debit − kredit) per akun_id dari jurnal terposting pada [from,to]. */
    private function journalNet(?string $from, string $to, ?int $unitId = null): array
    {
        $rows = TransaksiDetail::query()
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->whereNotNull('transaksi.posted_at')
            ->whereDate('transaksi.tanggal', '<=', $to)
            ->when($from, fn ($q) => $q->whereDate('transaksi.tanggal', '>=', $from))
            ->when($unitId, fn ($q) => $q->where('transaksi.unit_usaha_id', $unitId))
            ->groupBy('transaksi_detail.akun_id')
            ->selectRaw('transaksi_detail.akun_id, SUM(transaksi_detail.debit - transaksi_detail.kredit) as net')
            ->pluck('net', 'akun_id');

        return $rows->map(fn ($v) => Decimal::of((string) $v))->all();
    }

    /** Net opening (saldo_debit − saldo_kredit) per akun_id, hanya cutover terkunci. */
    private function openingNet(?int $unitId = null): array
    {
        $rows = SaldoAwalAkun::query()
            ->whereNotNull('locked_at')
            ->when($unitId, fn ($q) => $q->where('unit_usaha_id', $unitId))
            ->groupBy('akun_id')
            ->selectRaw('akun_id, SUM(saldo_debit - saldo_kredit) as net')
            ->pluck('net', 'akun_id');

        return $rows->map(fn ($v) => Decimal::of((string) $v))->all();
    }

    /**
     * Net (debit − kredit) per akun_id = jurnal terposting [from,to] (+ opening
     * terkunci bila includeOpening). Dipakai laporan rinci per-akun (bukan hanya
     * system_key). @return array<int, string>
     */
    public function accountNet(?string $from, string $to, bool $includeOpening = true, ?int $unitId = null): array
    {
        $journal = $this->journalNet($from, $to, $unitId);
        $opening = $includeOpening ? $this->openingNet($unitId) : [];

        $ids = array_unique(array_merge(array_keys($journal), array_keys($opening)));
        $out = [];
        foreach ($ids as $id) {
            $out[(int) $id] = Decimal::add($journal[$id] ?? '0', $opening[$id] ?? '0');
        }

        return $out;
    }

    /**
     * Σ net(debit − kredit) seluruh akun ber-jenis $jenis (laporan basis-akun:
     * mencakup akun bernama tanpa system_key). Kontra otomatis benar (tanda net).
     */
    private function jenisNet(string $jenis, ?string $from, string $to, bool $includeOpening, ?int $unitId = null): string
    {
        $net = $this->accountNet($from, $to, $includeOpening, $unitId);
        $sum = '0';
        foreach (Akun::where('jenis_akun', $jenis)->pluck('id') as $id) {
            $sum = Decimal::add($sum, $net[(int) $id] ?? '0');
        }

        return $sum;
    }

    /** Σ saldo natural (positif sesuai saldo normal tiap akun) seluruh akun ber-jenis $jenis. */
    private function jenisNatural(string $jenis, ?string $from, string $to, bool $includeOpening, ?int $unitId = null): string
    {
        $net = $this->accountNet($from, $to, $includeOpening, $unitId);
        $sum = '0';
        foreach (Akun::where('jenis_akun', $jenis)->get(['id', 'saldo_normal']) as $a) {
            $n = $net[$a->id] ?? '0';
            $sum = Decimal::add($sum, $a->saldo_normal->value === 'debit' ? $n : Decimal::mul($n, '-1'));
        }

        return $sum;
    }

    /**
     * Mutasi bruto (debit, kredit) jurnal terposting [from,to] seluruh akun
     * ber-jenis $jenis. @return array{debit: string, kredit: string}
     */
    private function jenisMovementGross(string $jenis, string $from, string $to, ?int $unitId = null): array
    {
        $ids = Akun::where('jenis_akun', $jenis)->pluck('id')->map(fn ($v) => (int) $v)->all();
        if ($ids === []) {
            return ['debit' => '0.00', 'kredit' => '0.00'];
        }

        $row = TransaksiDetail::query()
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->whereIn('transaksi_detail.akun_id', $ids)
            ->whereNotNull('transaksi.posted_at')
            ->whereDate('transaksi.tanggal', '>=', $from)
            ->whereDate('transaksi.tanggal', '<=', $to)
            ->when($unitId, fn ($q) => $q->where('transaksi.unit_usaha_id', $unitId))
            ->selectRaw('SUM(transaksi_detail.debit) as d, SUM(transaksi_detail.kredit) as k')
            ->first();

        return ['debit' => Decimal::of((string) ($row->d ?? '0')), 'kredit' => Decimal::of((string) ($row->k ?? '0'))];
    }

    /** Saldo natural per system_key (positif sesuai saldo normal). */
    public function systemKeyBalances(string $asOf, ?int $unitId = null, bool $includeOpening = true, ?string $from = null): array
    {
        $journal = $this->journalNet($from, $asOf, $unitId);
        $opening = $includeOpening ? $this->openingNet($unitId) : [];

        $akun = Akun::whereNotNull('system_key')->get(['id', 'system_key', 'saldo_normal']);
        $result = [];
        foreach ($akun as $a) {
            $net = Decimal::add($journal[$a->id] ?? '0', $opening[$a->id] ?? '0');
            // natural: akun debit → net; akun kredit → -net.
            $natural = $a->saldo_normal->value === 'debit' ? $net : Decimal::mul($net, '-1');
            $result[$a->system_key] = $natural;
        }

        return $result;
    }

    public function labaBersih(string $from, string $to, ?int $unitId = null): string
    {
        // P&L basis-akun (jenis), tanpa opening: Σ pendapatan − (Σ hpp + Σ beban).
        // Mencakup akun bernama tanpa system_key (bunga bank, beban rinci, pajak).
        $pendapatan = $this->jenisNatural('pendapatan', $from, $to, false, $unitId);
        $beban = Decimal::add(
            $this->jenisNatural('hpp', $from, $to, false, $unitId),
            $this->jenisNatural('beban', $from, $to, false, $unitId),
        );

        return Decimal::sub($pendapatan, $beban);
    }

    /** @return array<string, string> */
    public function labaRugi(string $from, string $to, ?int $unitId = null): array
    {
        $b = $this->systemKeyBalances($to, $unitId, includeOpening: false, from: $from);
        $penjualan = $b['sales'] ?? '0';
        $hpp = $b['cogs'] ?? '0';
        $labaKotor = Decimal::sub($penjualan, $hpp);
        $pendapatanKredit = Decimal::add($b['interest_income'] ?? '0', $b['penalty_income'] ?? '0');
        $pendapatanLain = $b['inventory_gain'] ?? '0';
        $beban = $b['operating_expense'] ?? '0';
        $penyusutan = $b['depreciation_expense'] ?? '0';
        $bebanSelisih = $b['inventory_loss'] ?? '0';

        return [
            'penjualan_bersih' => $penjualan,
            'hpp' => $hpp,
            'laba_kotor' => $labaKotor,
            'pendapatan_kredit' => $pendapatanKredit,
            'pendapatan_lain' => $pendapatanLain,
            'beban_operasional' => $beban,
            'penyusutan' => $penyusutan,
            'beban_selisih_persediaan' => $bebanSelisih,
            'laba_bersih' => $this->labaBersih($from, $to, $unitId),
        ];
    }

    /** @return array<string, string> */
    public function neraca(string $asOf, ?int $unitId = null, string $cutoverFrom = '2026-01-01'): array
    {
        $b = $this->systemKeyBalances($asOf, $unitId);
        $kas = Decimal::add($b['cash'] ?? '0', $b['bank'] ?? '0');
        $akumulasi = $b['accumulated_depreciation'] ?? '0';
        $asetTetapNeto = Decimal::sub($b['fixed_asset'] ?? '0', $akumulasi);
        $laba = $this->labaBersih($cutoverFrom, $asOf, $unitId);

        // Total berbasis akun (jenis) → mencakup akun bernama tanpa system_key
        // (dana-dana ekuitas, dsb). Kontra (akumulasi penyusutan) otomatis benar.
        $aset = $this->jenisNet('aset', null, $asOf, true, $unitId);
        $kewajiban = Decimal::mul($this->jenisNet('kewajiban', null, $asOf, true, $unitId), '-1');
        $modal = Decimal::mul($this->jenisNet('modal', null, $asOf, true, $unitId), '-1');
        $ekuitas = Decimal::add($modal, $laba);

        return [
            'kas_bank' => $kas,
            'piutang_usaha' => $b['trade_receivable'] ?? '0.00',
            'piutang_pinjaman' => $b['loan_receivable'] ?? '0.00',
            'persediaan' => $b['inventory'] ?? '0.00',
            'aset_tetap_bruto' => $b['fixed_asset'] ?? '0.00',
            'akumulasi_penyusutan' => $akumulasi,
            'aset_tetap_neto' => $asetTetapNeto,
            'total_aset' => $aset,
            'utang' => $kewajiban,
            'modal' => $modal,
            'laba_kumulatif' => $laba,
            'total_ekuitas' => $ekuitas,
            'total_kewajiban_ekuitas' => Decimal::add($kewajiban, $ekuitas),
            'seimbang' => Decimal::compare($aset, Decimal::add($kewajiban, $ekuitas)) === 0,
        ];
    }

    /** @return array{operasi: string, investasi: string, pendanaan: string, arus_bersih: string, kas_awal: string, kas_akhir: string} */
    public function arusKas(string $from, string $to, ?int $unitId = null): array
    {
        $rows = TransaksiDetail::query()
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->join('akun', 'akun.id', '=', 'transaksi_detail.akun_id')
            ->where('akun.is_cash', true)
            ->whereNotNull('transaksi.posted_at')
            ->whereDate('transaksi.tanggal', '>=', $from)
            ->whereDate('transaksi.tanggal', '<=', $to)
            ->when($unitId, fn ($q) => $q->where('transaksi.unit_usaha_id', $unitId))
            ->groupBy('transaksi_detail.kelompok_arus_kas')
            ->selectRaw('transaksi_detail.kelompok_arus_kas as grp, SUM(transaksi_detail.debit - transaksi_detail.kredit) as net')
            ->pluck('net', 'grp');

        $operasi = Decimal::of((string) ($rows['operasi'] ?? '0'));
        $investasi = Decimal::of((string) ($rows['investasi'] ?? '0'));
        $pendanaan = Decimal::of((string) ($rows['pendanaan'] ?? '0'));
        $bersih = Decimal::add(Decimal::add($operasi, $investasi), $pendanaan);

        // Kas awal = opening kas + jurnal kas sebelum `from`; kas akhir s/d `to`.
        $kasAwal = $this->cashAtDate($from, $unitId, exclusiveBefore: true);
        $kasAkhir = $this->cashAtDate($to, $unitId, exclusiveBefore: false);

        return [
            'operasi' => $operasi,
            'investasi' => $investasi,
            'pendanaan' => $pendanaan,
            'arus_bersih' => $bersih,
            'kas_awal' => $kasAwal,
            'kas_akhir' => $kasAkhir,
        ];
    }

    /**
     * Arus kas bruto pada akun kas/bank untuk [from,to]: masuk = Σ debit,
     * keluar = Σ kredit (posted). Rekonsiliasi dashboard: saldo awal + masuk −
     * keluar = saldo akhir. Konsisten dengan arusKas (arus_bersih = masuk − keluar).
     *
     * @return array{masuk: string, keluar: string}
     */
    public function cashGrossFlow(string $from, string $to, ?int $unitId = null): array
    {
        $cashIds = $this->cashAkunIds();
        if ($cashIds === []) {
            return ['masuk' => '0.00', 'keluar' => '0.00'];
        }

        $row = TransaksiDetail::query()
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->whereIn('transaksi_detail.akun_id', $cashIds)
            ->whereNotNull('transaksi.posted_at')
            ->whereDate('transaksi.tanggal', '>=', $from)
            ->whereDate('transaksi.tanggal', '<=', $to)
            ->when($unitId, fn ($q) => $q->where('transaksi.unit_usaha_id', $unitId))
            ->selectRaw('SUM(transaksi_detail.debit) as d, SUM(transaksi_detail.kredit) as k')
            ->first();

        return ['masuk' => Decimal::of((string) ($row->d ?? '0')), 'keluar' => Decimal::of((string) ($row->k ?? '0'))];
    }

    private function cashAkunIds(): array
    {
        return Akun::where('is_cash', true)->pluck('id')->all();
    }

    /** Saldo kas (opening + jurnal) s/d tanggal; exclusiveBefore=true → sebelum tanggal. */
    private function cashAtDate(string $date, ?int $unitId, bool $exclusiveBefore): string
    {
        $cashIds = $this->cashAkunIds();
        if ($cashIds === []) {
            return '0.00';
        }

        $opening = SaldoAwalAkun::query()->whereIn('akun_id', $cashIds)
            ->whereNotNull('locked_at')
            ->when($unitId, fn ($q) => $q->where('unit_usaha_id', $unitId))
            ->selectRaw('SUM(saldo_debit - saldo_kredit) as net')->value('net');

        $journal = TransaksiDetail::query()
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->whereIn('transaksi_detail.akun_id', $cashIds)
            ->whereNotNull('transaksi.posted_at')
            ->when($exclusiveBefore,
                fn ($q) => $q->whereDate('transaksi.tanggal', '<', $date),
                fn ($q) => $q->whereDate('transaksi.tanggal', '<=', $date))
            ->when($unitId, fn ($q) => $q->where('transaksi.unit_usaha_id', $unitId))
            ->selectRaw('SUM(transaksi_detail.debit - transaksi_detail.kredit) as net')->value('net');

        return Decimal::add((string) ($opening ?? '0'), (string) ($journal ?? '0'));
    }

    public function omzetDesa(string $from, string $to, ?int $unitId = null): string
    {
        $b = $this->systemKeyBalances($to, $unitId, includeOpening: false, from: $from);

        // Penjualan bersih + pendapatan kredit (bunga+denda) + pendapatan lain. Bukan modal/pokok/pajak.
        return Decimal::add(
            Decimal::add($b['sales'] ?? '0', $b['interest_income'] ?? '0'),
            Decimal::add($b['penalty_income'] ?? '0', $b['inventory_gain'] ?? '0')
        );
    }

    /**
     * Rincian omzet desa untuk snapshot laporan_desa.
     *
     * @return array{omzet_dagang: string, pendapatan_kredit: string, pendapatan_lain: string, total_omzet: string}
     */
    public function omzetDesaBreakdown(string $from, string $to, ?int $unitId = null): array
    {
        $b = $this->systemKeyBalances($to, $unitId, includeOpening: false, from: $from);
        $dagang = Decimal::of($b['sales'] ?? '0');
        $kredit = Decimal::add($b['interest_income'] ?? '0', $b['penalty_income'] ?? '0');
        $lain = Decimal::of($b['inventory_gain'] ?? '0');

        return [
            'omzet_dagang' => $dagang,
            'pendapatan_kredit' => $kredit,
            'pendapatan_lain' => $lain,
            'total_omzet' => Decimal::add($dagang, Decimal::add($kredit, $lain)),
        ];
    }

    /**
     * Penanda revisi data untuk rentang periode: jumlah baris jurnal terposting
     * pada [from,to]. Berubah bila ada posting/pembalik yang menyentuh rentang
     * ini (dokumen10: deteksi perubahan antara preview, submit, approval).
     */
    public function dataRevision(string $from, string $to, ?int $unitId = null): int
    {
        return (int) TransaksiDetail::query()
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->whereNotNull('transaksi.posted_at')
            ->whereDate('transaksi.tanggal', '>=', $from)
            ->whereDate('transaksi.tanggal', '<=', $to)
            ->when($unitId, fn ($q) => $q->where('transaksi.unit_usaha_id', $unitId))
            ->count();
    }

    /** Akun id untuk sekumpulan system_key. @return array<int, int> */
    private function akunIdsForKeys(array $systemKeys): array
    {
        return Akun::whereIn('system_key', $systemKeys)->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Mutasi debit/kredit terposting pada [from,to] untuk akun system_key.
     *
     * @return array{debit: string, kredit: string}
     */
    private function keyMovement(array $systemKeys, ?string $from, string $to, ?int $unitId = null): array
    {
        $ids = $this->akunIdsForKeys($systemKeys);
        if ($ids === []) {
            return ['debit' => '0.00', 'kredit' => '0.00'];
        }

        $row = TransaksiDetail::query()
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->whereIn('transaksi_detail.akun_id', $ids)
            ->whereNotNull('transaksi.posted_at')
            ->whereDate('transaksi.tanggal', '<=', $to)
            ->when($from, fn ($q) => $q->whereDate('transaksi.tanggal', '>=', $from))
            ->when($unitId, fn ($q) => $q->where('transaksi.unit_usaha_id', $unitId))
            ->selectRaw('SUM(transaksi_detail.debit) as d, SUM(transaksi_detail.kredit) as k')
            ->first();

        return ['debit' => Decimal::of((string) ($row->d ?? '0')), 'kredit' => Decimal::of((string) ($row->k ?? '0'))];
    }

    /**
     * Perubahan modal (dokumen10 §3): ekuitas awal + penambahan − pengurangan +
     * laba periode = ekuitas akhir. Ekuitas akhir = ekuitas neraca pada cutoff sama.
     *
     * @return array<string, string>
     */
    public function perubahanModal(string $from, string $to, ?int $unitId = null, string $cutoverFrom = '2026-01-01'): array
    {
        // Basis-akun (jenis=modal): mencakup seluruh dana ekuitas bernama.
        $mv = $this->jenisMovementGross('modal', $from, $to, $unitId);
        $penambahan = $mv['kredit'];
        $pengurangan = $mv['debit'];
        $netChange = Decimal::sub($penambahan, $pengurangan);

        $modalAkhirAkun = Decimal::mul($this->jenisNet('modal', null, $to, true, $unitId), '-1');
        $modalAwalAkun = Decimal::sub($modalAkhirAkun, $netChange);

        $sebelum = Carbon::parse($from)->subDay()->toDateString();
        $labaAwal = $this->labaBersih($cutoverFrom, $sebelum, $unitId);
        $labaPeriode = $this->labaBersih($from, $to, $unitId);

        $ekuitasAwal = Decimal::add($modalAwalAkun, $labaAwal);
        $ekuitasAkhir = Decimal::add(Decimal::add($ekuitasAwal, $netChange), $labaPeriode);

        return [
            'ekuitas_awal' => $ekuitasAwal,
            'penambahan_modal' => $penambahan,
            'pengurangan_modal' => $pengurangan,
            'laba_periode' => $labaPeriode,
            'ekuitas_akhir' => $ekuitasAkhir,
        ];
    }

    /**
     * Laporan kredit (dokumen10 §5): kontrak pinjaman tercairkan s/d as_of.
     * Pisahkan sisa pokok dari sisa tagihan. Pinjaman dicairkan setelah cutoff
     * tidak masuk.
     *
     * @return array{rows: array<int, array<string, string>>, total_sisa_pokok: string, total_sisa_tagihan: string}
     */
    public function kredit(string $asOf, ?int $unitId = null): array
    {
        $loans = Pinjaman::with(['peminjam', 'angsuran', 'transaksiPencairan'])
            ->whereHas('transaksiPencairan', fn ($q) => $q->whereNotNull('posted_at')->whereDate('tanggal', '<=', $asOf))
            ->when($unitId, fn ($q) => $q->where('unit_usaha_id', $unitId))
            ->get();

        $rows = [];
        $totalSisaPokok = '0';
        $totalSisaTagihan = '0';

        foreach ($loans as $loan) {
            $pokokDibayar = Decimal::of((string) $loan->angsuran->sum('pokok_dibayar'));
            $bungaDibayar = Decimal::of((string) $loan->angsuran->sum('bunga_dibayar'));
            $dendaDibayar = Decimal::of((string) $loan->angsuran->sum('denda_dibayar'));
            $sisaPokok = (string) $loan->sisa_pokok;
            $bungaTagih = Decimal::of((string) $loan->angsuran->sum('bunga_tagihan'));
            $dendaTagih = Decimal::of((string) $loan->angsuran->sum('denda_tagihan'));
            $sisaBunga = Decimal::sub($bungaTagih, $bungaDibayar);
            $sisaDenda = Decimal::sub($dendaTagih, $dendaDibayar);
            $sisaTagihan = Decimal::add($sisaPokok, Decimal::add($sisaBunga, $sisaDenda));

            $rows[] = [
                'nomor' => $loan->transaksiPencairan?->nomor_transaksi ?? ('PJM-'.$loan->id),
                'peminjam' => $loan->peminjam?->nama ?? '—',
                'pokok_awal' => (string) $loan->pokok_pinjaman,
                'pokok_dibayar' => $pokokDibayar,
                'bunga_dibayar' => $bungaDibayar,
                'denda_dibayar' => $dendaDibayar,
                'sisa_pokok' => Decimal::of($sisaPokok),
                'sisa_tagihan' => $sisaTagihan,
                'status' => $loan->status->value,
            ];
            $totalSisaPokok = Decimal::add($totalSisaPokok, $sisaPokok);
            $totalSisaTagihan = Decimal::add($totalSisaTagihan, $sisaTagihan);
        }

        return ['rows' => $rows, 'total_sisa_pokok' => $totalSisaPokok, 'total_sisa_tagihan' => $totalSisaTagihan];
    }

    /**
     * Buku besar: saldo awal (sebelum from) + mutasi [from,to] dengan saldo
     * berjalan per akun. Signed debit−kredit. Hanya akun dengan saldo/mutasi.
     *
     * @return array<int, array{akun: Akun, saldo_awal: string, entries: array<int, array<string, string>>, saldo_akhir: string}>
     */
    public function bukuBesar(string $from, string $to, ?int $unitId = null, ?int $akunId = null): array
    {
        $sebelum = Carbon::parse($from)->subDay()->toDateString();
        $openBefore = $this->journalNet(null, $sebelum, $unitId);
        $openingSaldo = $this->openingNet($unitId);

        $akunQuery = Akun::query()->when($akunId, fn ($q) => $q->whereKey($akunId))->orderBy('kode_akun');
        $hasil = [];

        foreach ($akunQuery->get() as $akun) {
            $entries = TransaksiDetail::query()
                ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
                ->where('transaksi_detail.akun_id', $akun->id)
                ->whereNotNull('transaksi.posted_at')
                ->whereDate('transaksi.tanggal', '>=', $from)
                ->whereDate('transaksi.tanggal', '<=', $to)
                ->when($unitId, fn ($q) => $q->where('transaksi.unit_usaha_id', $unitId))
                ->orderBy('transaksi.tanggal')
                ->orderBy('transaksi.id')
                ->orderBy('transaksi_detail.nomor_baris')
                ->get(['transaksi.tanggal', 'transaksi.nomor_transaksi', 'transaksi_detail.keterangan',
                    'transaksi_detail.debit', 'transaksi_detail.kredit']);

            $saldoAwal = Decimal::add($openingSaldo[$akun->id] ?? '0', $openBefore[$akun->id] ?? '0');

            if ($entries->isEmpty() && Decimal::isZero($saldoAwal)) {
                continue;
            }

            $saldo = $saldoAwal;
            $list = [];
            foreach ($entries as $e) {
                $saldo = Decimal::add($saldo, Decimal::sub((string) $e->debit, (string) $e->kredit));
                $list[] = [
                    'tanggal' => Carbon::parse($e->tanggal)->toDateString(),
                    'nomor' => (string) $e->nomor_transaksi,
                    'keterangan' => (string) ($e->keterangan ?? ''),
                    'debit' => Decimal::of((string) $e->debit),
                    'kredit' => Decimal::of((string) $e->kredit),
                    'saldo' => $saldo,
                ];
            }

            $hasil[] = [
                'akun' => $akun,
                'saldo_awal' => $saldoAwal,
                'entries' => $list,
                'saldo_akhir' => $saldo,
            ];
        }

        return $hasil;
    }

    /**
     * Rekonsiliasi (dokumen10 §Rekonsiliasi): perbandingan decimal 2, toleransi
     * 0,00. Mengembalikan daftar check dengan expected/actual/match/severity.
     *
     * @return array<int, array{key: string, label: string, expected: string, actual: string, match: bool, severity: string}>
     */
    public function reconciliation(string $from, string $to, ?int $unitId = null, string $cutoverFrom = '2026-01-01'): array
    {
        $checks = [];
        $add = function (string $key, string $label, string $expected, string $actual, string $severity) use (&$checks): void {
            $checks[] = [
                'key' => $key, 'label' => $label,
                'expected' => Decimal::of($expected), 'actual' => Decimal::of($actual),
                'match' => Decimal::compare($expected, $actual) === 0, 'severity' => $severity,
            ];
        };

        // 1. Jurnal: total debit = total kredit global (posted, s/d to).
        $jurnal = TransaksiDetail::query()
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->whereNotNull('transaksi.posted_at')
            ->whereDate('transaksi.tanggal', '<=', $to)
            ->when($unitId, fn ($q) => $q->where('transaksi.unit_usaha_id', $unitId))
            ->selectRaw('SUM(transaksi_detail.debit) as d, SUM(transaksi_detail.kredit) as k')->first();
        $add('jurnal', 'Jurnal: total debit = kredit', (string) ($jurnal->d ?? '0'), (string) ($jurnal->k ?? '0'), 'FAIL');

        // 2. Neraca seimbang.
        $neraca = $this->neraca($to, $unitId, $cutoverFrom);
        $add('neraca', 'Neraca: aset = kewajiban + ekuitas', $neraca['total_aset'], $neraca['total_kewajiban_ekuitas'], 'FAIL');

        // 3. Kas/bank: ledger = arus kas closing.
        $arus = $this->arusKas($from, $to, $unitId);
        $add('kas', 'Kas/bank: ledger = arus kas closing', $neraca['kas_bank'], $arus['kas_akhir'], 'FAIL');

        // 4. Stok: nilai mutasi akhir per produk = akun persediaan.
        $add('stok', 'Stok: subledger = akun persediaan', $this->inventorySubledger($to, $unitId), $neraca['persediaan'], 'FAIL');

        // 5. Piutang dagang: outstanding invoice kredit = ledger.
        $add('piutang_usaha', 'Piutang dagang: subledger = ledger',
            $this->receivableSubledger($to, $unitId), $neraca['piutang_usaha'], 'FAIL');

        // 6. Utang dagang.
        $add('utang_usaha', 'Utang dagang: subledger = ledger',
            $this->payableSubledger($to, $unitId), $neraca['utang'], 'FAIL');

        // 7. Pinjaman: sisa pokok subledger = akun piutang pinjaman.
        $add('pinjaman', 'Pinjaman: sisa pokok subledger = ledger',
            $this->kredit($to, $unitId)['total_sisa_pokok'], $neraca['piutang_pinjaman'], 'FAIL');

        // 8. Aset: perolehan − akumulasi subledger = ledger aset tetap neto.
        $asetSub = $this->assetSubledger($to, $unitId);
        $add('aset', 'Aset: subledger = ledger', $asetSub, $neraca['aset_tetap_neto'], 'FAIL');

        // 9. Laba: laba rugi = laba periode perubahan modal.
        $pm = $this->perubahanModal($from, $to, $unitId, $cutoverFrom);
        $add('laba', 'Laba: laba rugi = perubahan modal', $this->labaBersih($from, $to, $unitId), $pm['laba_periode'], 'FAIL');

        // 10. Modal: ekuitas akhir perubahan modal = ekuitas neraca.
        $add('modal', 'Modal: perubahan modal = ekuitas neraca', $pm['ekuitas_akhir'], $neraca['total_ekuitas'], 'FAIL');

        return $checks;
    }

    /**
     * Peringatan non-saldo untuk penutupan (dokumen10): bukti belum valid,
     * draft tertinggal, penyusutan & opname belum selesai.
     *
     * @return array<int, array{key: string, label: string, count: int, severity: string}>
     */
    public function reconciliationWarnings(string $to, ?int $unitId = null): array
    {
        $buktiBelum = BuktiTransaksi::where('status_verifikasi', '!=', 'valid')->count();
        $draft = Transaksi::where('status', 'draft')
            ->when($unitId, fn ($q) => $q->where('unit_usaha_id', $unitId))->count();
        $opnameMenggantung = StokOpname::where('status', 'disetujui')
            ->when($unitId, fn ($q) => $q->where('unit_usaha_id', $unitId))->count();

        return [
            ['key' => 'bukti', 'label' => 'Bukti belum valid', 'count' => $buktiBelum, 'severity' => 'WARNING'],
            ['key' => 'draft', 'label' => 'Draft tertinggal', 'count' => $draft, 'severity' => 'WARNING'],
            ['key' => 'opname', 'label' => 'Opname disetujui belum diposting', 'count' => $opnameMenggantung, 'severity' => 'FAIL_ON_CLOSE'],
        ];
    }

    private function inventorySubledger(string $asOf, ?int $unitId): string
    {
        $produk = Produk::query()->when($unitId, fn ($q) => $q->where('unit_usaha_id', $unitId))->pluck('id');
        $total = '0';
        foreach ($produk as $id) {
            $last = MutasiStok::where('produk_id', $id)
                ->whereDate('tanggal', '<=', $asOf)
                ->orderByDesc('urutan')->first();
            if ($last) {
                $total = Decimal::add($total, (string) $last->saldo_nilai);
            }
        }

        return $total;
    }

    private function receivableSubledger(string $asOf, ?int $unitId): string
    {
        // Σ (total invoice penjualan kredit − alokasi pelunasan terposting) s/d as_of.
        $invoices = Transaksi::where('jenis_transaksi', 'penjualan')
            ->where('metode_pembayaran', 'kredit')
            ->whereNotNull('posted_at')->where('status', 'posted')
            ->whereDate('tanggal', '<=', $asOf)
            ->when($unitId, fn ($q) => $q->where('unit_usaha_id', $unitId))
            ->get(['id', 'total']);

        $total = '0';
        foreach ($invoices as $inv) {
            $alokasi = AlokasiPelunasan::where('invoice_transaksi_id', $inv->id)
                ->whereHas('pembayaranTransaksi', fn ($q) => $q->whereNotNull('posted_at')->where('status', 'posted')
                    ->whereDate('tanggal', '<=', $asOf))
                ->sum('nominal');
            $total = Decimal::add($total, Decimal::sub((string) $inv->total, (string) $alokasi));
        }

        return $total;
    }

    private function payableSubledger(string $asOf, ?int $unitId): string
    {
        $invoices = Transaksi::where('jenis_transaksi', 'pembelian')
            ->where('metode_pembayaran', 'kredit')
            ->whereNotNull('posted_at')->where('status', 'posted')
            ->whereDate('tanggal', '<=', $asOf)
            ->when($unitId, fn ($q) => $q->where('unit_usaha_id', $unitId))
            ->get(['id', 'total']);

        $total = '0';
        foreach ($invoices as $inv) {
            $alokasi = AlokasiPelunasan::where('invoice_transaksi_id', $inv->id)
                ->whereHas('pembayaranTransaksi', fn ($q) => $q->whereNotNull('posted_at')->where('status', 'posted')
                    ->whereDate('tanggal', '<=', $asOf))
                ->sum('nominal');
            $total = Decimal::add($total, Decimal::sub((string) $inv->total, (string) $alokasi));
        }

        return $total;
    }

    private function assetSubledger(string $asOf, ?int $unitId): string
    {
        $aset = AsetTetap::query()->when($unitId, fn ($q) => $q->where('unit_usaha_id', $unitId))
            ->whereDate('tanggal_perolehan', '<=', $asOf)->get();

        $total = '0';
        foreach ($aset as $a) {
            $akumulasi = Decimal::add((string) $a->akumulasi_awal, (string) Transaksi::where('sumber_tipe', 'aset_tetap')
                ->where('sumber_id', $a->id)->where('jenis_transaksi', 'penyusutan')
                ->whereNotNull('posted_at')->where('status', 'posted')
                ->whereDate('tanggal', '<=', $asOf)->sum('total'));
            $total = Decimal::add($total, Decimal::sub((string) $a->harga_perolehan, $akumulasi));
        }

        return $total;
    }
}
