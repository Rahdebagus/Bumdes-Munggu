<?php

namespace App\Domain\Reporting;

use App\Models\UnitUsaha;
use Illuminate\Support\Carbon;

/**
 * Menyusun dataset ternormalisasi satu laporan dari ReportService. HTML, PDF,
 * dan XLSX memakai dataset yang sama; frontend tidak menghitung ulang angka
 * resmi (dokumen10 §Kontrak). Nilai tetap string decimal; render yang memformat.
 */
class ReportDataset
{
    public const TYPES = [
        'laba-rugi' => 'Laporan Laba Rugi',
        'neraca' => 'Neraca',
        'arus-kas' => 'Laporan Arus Kas (Langsung)',
        'perubahan-modal' => 'Laporan Perubahan Modal',
        'omzet-desa' => 'Omzet Desa',
        'kredit' => 'Laporan Kredit',
        'buku-besar' => 'Buku Besar',
    ];

    public function __construct(private ReportService $reports) {}

    /**
     * @param  array{from: string, to: string, asOf?: string, unitId?: ?int, cutoverFrom?: string}  $filters
     * @return array{type: string, title: string, periode: string, unit: string, generated_at: string, summary: array<int, array{label: string, value: string}>, detail: ?array{headers: array<int, string>, rows: array<int, array<int, array{text?: string, num?: string}>>, foot?: array<int, array{text?: string, num?: string}>}}
     */
    public function build(string $type, array $filters): array
    {
        $from = $filters['from'];
        $to = $filters['to'];
        $asOf = $filters['asOf'] ?? $to;
        $unitId = $filters['unitId'] ?? null;
        $cutover = $filters['cutoverFrom'] ?? '2026-01-01';

        $unitLabel = $unitId
            ? (UnitUsaha::find($unitId)?->nama_unit ?? "Unit #{$unitId}")
            : 'Konsolidasi (semua unit)';

        $base = [
            'type' => $type,
            'title' => self::TYPES[$type] ?? $type,
            'from' => $from,
            'to' => $to,
            'periode' => Carbon::parse($from)->format('d/m/Y').' – '.Carbon::parse($to)->format('d/m/Y'),
            'unit' => $unitLabel,
            'generated_at' => now()->format('d/m/Y H:i'),
            'detail' => null,
        ];

        return array_merge($base, match ($type) {
            'laba-rugi' => $this->labaRugi($from, $to, $unitId),
            'neraca' => $this->neraca($asOf, $unitId, $cutover),
            'arus-kas' => $this->arusKas($from, $to, $unitId),
            'perubahan-modal' => $this->perubahanModal($from, $to, $unitId, $cutover),
            'omzet-desa' => $this->omzetDesa($from, $to, $unitId),
            'kredit' => $this->kredit($asOf, $unitId),
            'buku-besar' => $this->bukuBesar($from, $to, $unitId),
            default => ['summary' => []],
        });
    }

    private function labaRugi(string $from, string $to, ?int $unitId): array
    {
        $r = $this->reports->labaRugi($from, $to, $unitId);

        return ['summary' => [
            ['label' => 'Penjualan bersih', 'value' => $r['penjualan_bersih']],
            ['label' => 'Harga pokok penjualan', 'value' => $r['hpp']],
            ['label' => 'Laba kotor', 'value' => $r['laba_kotor']],
            ['label' => 'Pendapatan kredit (bunga + denda)', 'value' => $r['pendapatan_kredit']],
            ['label' => 'Pendapatan lain', 'value' => $r['pendapatan_lain']],
            ['label' => 'Beban operasional', 'value' => $r['beban_operasional']],
            ['label' => 'Beban penyusutan', 'value' => $r['penyusutan']],
            ['label' => 'Beban selisih persediaan', 'value' => $r['beban_selisih_persediaan']],
            ['label' => 'Laba bersih', 'value' => $r['laba_bersih']],
        ]];
    }

    private function neraca(string $asOf, ?int $unitId, string $cutover): array
    {
        $r = $this->reports->neraca($asOf, $unitId, $cutover);

        return ['summary' => [
            ['label' => 'Kas & bank', 'value' => $r['kas_bank']],
            ['label' => 'Piutang usaha', 'value' => $r['piutang_usaha']],
            ['label' => 'Piutang pinjaman', 'value' => $r['piutang_pinjaman']],
            ['label' => 'Persediaan', 'value' => $r['persediaan']],
            ['label' => 'Aset tetap bruto', 'value' => $r['aset_tetap_bruto']],
            ['label' => 'Akumulasi penyusutan', 'value' => $r['akumulasi_penyusutan']],
            ['label' => 'Aset tetap neto', 'value' => $r['aset_tetap_neto']],
            ['label' => 'TOTAL ASET', 'value' => $r['total_aset']],
            ['label' => 'Utang', 'value' => $r['utang']],
            ['label' => 'Modal', 'value' => $r['modal']],
            ['label' => 'Laba kumulatif berjalan', 'value' => $r['laba_kumulatif']],
            ['label' => 'Total ekuitas', 'value' => $r['total_ekuitas']],
            ['label' => 'TOTAL KEWAJIBAN + EKUITAS', 'value' => $r['total_kewajiban_ekuitas']],
        ]];
    }

    private function arusKas(string $from, string $to, ?int $unitId): array
    {
        $r = $this->reports->arusKas($from, $to, $unitId);

        return ['summary' => [
            ['label' => 'Kas awal', 'value' => $r['kas_awal']],
            ['label' => 'Arus kas operasi', 'value' => $r['operasi']],
            ['label' => 'Arus kas investasi', 'value' => $r['investasi']],
            ['label' => 'Arus kas pendanaan', 'value' => $r['pendanaan']],
            ['label' => 'Arus kas bersih', 'value' => $r['arus_bersih']],
            ['label' => 'Kas akhir', 'value' => $r['kas_akhir']],
        ]];
    }

    private function perubahanModal(string $from, string $to, ?int $unitId, string $cutover): array
    {
        $r = $this->reports->perubahanModal($from, $to, $unitId, $cutover);

        return ['summary' => [
            ['label' => 'Ekuitas awal', 'value' => $r['ekuitas_awal']],
            ['label' => 'Penambahan modal', 'value' => $r['penambahan_modal']],
            ['label' => 'Pengurangan modal', 'value' => $r['pengurangan_modal']],
            ['label' => 'Laba periode', 'value' => $r['laba_periode']],
            ['label' => 'Ekuitas akhir', 'value' => $r['ekuitas_akhir']],
        ]];
    }

    private function omzetDesa(string $from, string $to, ?int $unitId): array
    {
        return ['summary' => [
            ['label' => 'Omzet desa (penjualan + pendapatan kredit + lain)', 'value' => $this->reports->omzetDesa($from, $to, $unitId)],
        ]];
    }

    private function kredit(string $asOf, ?int $unitId): array
    {
        $r = $this->reports->kredit($asOf, $unitId);
        $rows = [];
        foreach ($r['rows'] as $row) {
            $rows[] = [
                ['text' => $row['nomor']],
                ['text' => $row['peminjam']],
                ['num' => $row['pokok_awal']],
                ['num' => $row['pokok_dibayar']],
                ['num' => $row['bunga_dibayar']],
                ['num' => $row['sisa_pokok']],
                ['num' => $row['sisa_tagihan']],
                ['text' => $row['status']],
            ];
        }

        return [
            'summary' => [
                ['label' => 'Total sisa pokok', 'value' => $r['total_sisa_pokok']],
                ['label' => 'Total sisa tagihan', 'value' => $r['total_sisa_tagihan']],
            ],
            'detail' => [
                'headers' => ['Nomor', 'Peminjam', 'Pokok awal', 'Pokok dibayar', 'Bunga dibayar', 'Sisa pokok', 'Sisa tagihan', 'Status'],
                'rows' => $rows,
                'foot' => [
                    ['text' => 'TOTAL'], ['text' => ''], ['text' => ''], ['text' => ''], ['text' => ''],
                    ['num' => $r['total_sisa_pokok']], ['num' => $r['total_sisa_tagihan']], ['text' => ''],
                ],
            ],
        ];
    }

    private function bukuBesar(string $from, string $to, ?int $unitId): array
    {
        $bb = $this->reports->bukuBesar($from, $to, $unitId);
        $rows = [];
        foreach ($bb as $akun) {
            $rows[] = [
                ['text' => $akun['akun']->kode_akun.' — '.$akun['akun']->nama_akun.' (saldo awal)'],
                ['text' => ''], ['text' => ''], ['text' => ''], ['num' => $akun['saldo_awal']],
            ];
            foreach ($akun['entries'] as $e) {
                $rows[] = [
                    ['text' => $e['tanggal']],
                    ['text' => $e['nomor']],
                    ['num' => $e['debit']],
                    ['num' => $e['kredit']],
                    ['num' => $e['saldo']],
                ];
            }
        }

        return [
            'summary' => [
                ['label' => 'Jumlah akun bergerak', 'value' => (string) count($bb)],
            ],
            'detail' => [
                'headers' => ['Tanggal/Akun', 'Nomor', 'Debit', 'Kredit', 'Saldo'],
                'rows' => $rows,
            ],
        ];
    }
}
