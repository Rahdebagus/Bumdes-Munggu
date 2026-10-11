<?php

namespace App\Domain\Reporting;

use App\Domain\Shared\Decimal;
use App\Models\Akun;
use Illuminate\Support\Carbon;

/**
 * Menyusun laporan keuangan RINCI per-akun (bukan hanya system_key) dari
 * ReportService. Pengelompokan mengikuti kolom report_group pada CoA sehingga
 * baris laporan selalu mencerminkan akun nyata di database (data-driven). Total
 * tiap laporan direkonsiliasi dengan ReportService (labaBersih, neraca) agar
 * konsisten dengan ekspor PDF/XLSX. Semua nominal string decimal 2.
 */
class StatementService
{
    public function __construct(private ReportService $reports) {}

    /**
     * Laba rugi rinci: pendapatan, skedul HPP, laba kotor, pendapatan lain,
     * beban per-akun, laba bersih, dan laba kumulatif sejak cutover.
     */
    public function labaRugi(string $from, string $to, ?int $unitId = null, string $cutover = '2026-01-01'): array
    {
        // Mutasi periode (tanpa opening) → akun laba rugi.
        $movement = $this->reports->accountNet($from, $to, includeOpening: false, unitId: $unitId);

        $pendapatan = $this->groupRows('pendapatan', $movement);
        $pendapatanLain = $this->groupRows('pendapatan_lain', $movement);
        $hppRows = $this->groupRows('hpp', $movement);
        $beban = $this->groupRows('beban', $movement);
        $pajak = $this->groupRows('beban_pajak', $movement);

        // Skedul HPP: persediaan awal/akhir dari saldo sistem (dengan opening).
        $beforeFrom = Carbon::parse($from)->subDay()->toDateString();
        $persediaanAkhir = $this->reports->systemKeyBalances($to, $unitId)['inventory'] ?? '0';
        $persediaanAwal = $this->reports->systemKeyBalances($beforeFrom, $unitId)['inventory'] ?? '0';
        $hpp = $hppRows['total'];
        $pembelianBersih = Decimal::sub(Decimal::add($hpp, $persediaanAkhir), $persediaanAwal);
        $barangTersedia = Decimal::add($persediaanAwal, $pembelianBersih);

        $labaKotor = Decimal::sub($pendapatan['total'], $hpp);
        $labaSetelahLain = Decimal::add($labaKotor, $pendapatanLain['total']);
        $labaSebelumPajak = Decimal::sub($labaSetelahLain, $beban['total']);
        $labaBersih = Decimal::sub($labaSebelumPajak, $pajak['total']);

        return [
            'pendapatan' => $pendapatan,
            'hpp' => [
                'persediaan_awal' => Decimal::of($persediaanAwal),
                'pembelian_bersih' => $pembelianBersih,
                'barang_tersedia' => $barangTersedia,
                'persediaan_akhir' => Decimal::of($persediaanAkhir),
                'hpp' => $hpp,
            ],
            'laba_kotor' => $labaKotor,
            'pendapatan_lain' => $pendapatanLain,
            'laba_setelah_lain' => $labaSetelahLain,
            'beban' => $beban,
            'laba_sebelum_pajak' => $labaSebelumPajak,
            'pajak' => $pajak,
            'laba_bersih' => $labaBersih,
            'laba_kumulatif' => $this->reports->labaBersih($cutover, $to, $unitId),
        ];
    }

    /**
     * Neraca rinci: aktiva lancar, aktiva tetap (bruto − akumulasi), kewajiban,
     * dan ekuitas (termasuk laba berjalan). Seimbang bila aktiva = pasiva.
     */
    public function neraca(string $asOf, ?int $unitId = null, string $cutover = '2026-01-01'): array
    {
        $balances = $this->reports->accountNet(null, $asOf, includeOpening: true, unitId: $unitId);

        $lancar = $this->groupRows('aset_lancar', $balances);

        // Aktiva tetap: akun kredit-normal (akumulasi) adalah kontra → dikurangkan.
        $tetapRows = [];
        $tetapNet = '0';
        foreach ($this->accounts('aset_tetap') as $akun) {
            $natural = $this->natural($akun, $balances[$akun->id] ?? '0');
            $contra = $akun->saldo_normal->value === 'kredit';
            $tetapRows[] = ['label' => $akun->nama_akun, 'kode' => $akun->kode_akun, 'value' => $natural, 'contra' => $contra];
            $tetapNet = $contra ? Decimal::sub($tetapNet, $natural) : Decimal::add($tetapNet, $natural);
        }

        $totalAktiva = Decimal::add($lancar['total'], $tetapNet);

        $kewajiban = $this->groupRows('kewajiban', $balances);

        $ekuitasRows = [];
        $ekuitasAkun = '0';
        foreach ($this->accounts('ekuitas') as $akun) {
            $natural = $this->natural($akun, $balances[$akun->id] ?? '0');
            $ekuitasRows[] = ['label' => $akun->nama_akun, 'kode' => $akun->kode_akun, 'value' => $natural];
            $ekuitasAkun = Decimal::add($ekuitasAkun, $natural);
        }
        $laba = $this->reports->labaBersih($cutover, $asOf, $unitId);
        $totalEkuitas = Decimal::add($ekuitasAkun, $laba);
        $totalPasiva = Decimal::add($kewajiban['total'], $totalEkuitas);

        return [
            'aktiva_lancar' => $lancar,
            'aktiva_tetap' => ['rows' => $tetapRows, 'net' => $tetapNet],
            'total_aktiva' => $totalAktiva,
            'kewajiban' => $kewajiban,
            'ekuitas' => ['rows' => $ekuitasRows, 'laba' => Decimal::of($laba), 'total' => $totalEkuitas],
            'total_pasiva' => $totalPasiva,
            'seimbang' => Decimal::compare($totalAktiva, $totalPasiva) === 0,
        ];
    }

    /**
     * Perubahan modal (sejak cutover s/d as_of): modal awal per dana (ekuitas
     * akun di awal) + perubahan dana periode + laba berjalan = modal akhir.
     * Modal akhir = total ekuitas neraca (rekonsiliasi).
     */
    public function perubahanModal(string $asOf, ?int $unitId = null, string $cutover = '2026-01-01'): array
    {
        $beforeCutover = Carbon::parse($cutover)->subDay()->toDateString();
        $awalBalances = $this->reports->accountNet(null, $beforeCutover, includeOpening: true, unitId: $unitId);
        $akhirBalances = $this->reports->accountNet(null, $asOf, includeOpening: true, unitId: $unitId);

        $rows = [];
        $totalAwal = '0';
        $totalAkhirAkun = '0';
        foreach ($this->accounts('ekuitas') as $akun) {
            $awal = $this->natural($akun, $awalBalances[$akun->id] ?? '0');
            $akhir = $this->natural($akun, $akhirBalances[$akun->id] ?? '0');
            $rows[] = ['label' => $akun->nama_akun, 'kode' => $akun->kode_akun, 'value' => $awal];
            $totalAwal = Decimal::add($totalAwal, $awal);
            $totalAkhirAkun = Decimal::add($totalAkhirAkun, $akhir);
        }

        $perubahanDana = Decimal::sub($totalAkhirAkun, $totalAwal);
        $laba = $this->reports->labaBersih($cutover, $asOf, $unitId);
        $modalAkhir = Decimal::add($totalAkhirAkun, $laba);

        return [
            'rows' => $rows,
            'total_awal' => $totalAwal,
            'perubahan_dana' => $perubahanDana,
            'laba' => Decimal::of($laba),
            'modal_akhir' => $modalAkhir,
        ];
    }

    /**
     * Baris per-akun untuk satu report_group + totalnya (nilai natural).
     *
     * @param  array<int, string>  $net
     * @return array{rows: array<int, array{label: string, kode: string, value: string}>, total: string}
     */
    private function groupRows(string $group, array $net): array
    {
        $rows = [];
        $total = '0';
        foreach ($this->accounts($group) as $akun) {
            $value = $this->natural($akun, $net[$akun->id] ?? '0');
            $rows[] = ['label' => $akun->nama_akun, 'kode' => $akun->kode_akun, 'value' => $value];
            $total = Decimal::add($total, $value);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /** Akun aktif pada satu report_group, urut kode. @return \Illuminate\Support\Collection<int, Akun> */
    private function accounts(string $group)
    {
        return Akun::where('is_active', true)->where('report_group', $group)->orderBy('kode_akun')->get();
    }

    /** Saldo natural: akun debit → net; akun kredit → −net. */
    private function natural(Akun $akun, string $net): string
    {
        return $akun->saldo_normal->value === 'debit' ? Decimal::of($net) : Decimal::mul($net, '-1');
    }
}
