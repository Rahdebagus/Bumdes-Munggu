<?php

namespace App\Domain\Reporting;

use App\Enums\LaporanDesaStatus;
use App\Models\LaporanDesa;
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
        'jurnal-umum' => 'Jurnal Umum',
        'neraca-saldo' => 'Neraca Saldo',
        'buku-kas' => 'Buku Kas',
        'buku-bank' => 'Buku Bank',
        'kartu-stok' => 'Laporan Persediaan (Kartu Stok)',
        'piutang-hutang' => 'Laporan Piutang & Hutang',
    ];

    public function __construct(
        private ReportService $reports,
        private StatementService $statements,
        private LedgerReports $ledger,
    ) {}

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
            'laba-rugi' => $this->labaRugi($from, $to, $unitId, $cutover),
            'neraca' => $this->neraca($asOf, $unitId, $cutover),
            'arus-kas' => $this->arusKas($from, $to, $unitId),
            'perubahan-modal' => $this->perubahanModal($from, $to, $unitId, $cutover),
            'omzet-desa' => $this->omzetDesa($from, $to, $unitId),
            'kredit' => $this->kredit($asOf, $unitId),
            'buku-besar' => $this->bukuBesar($from, $to, $unitId),
            'jurnal-umum' => $this->jurnalUmum($from, $to, $unitId),
            'neraca-saldo' => $this->neracaSaldo($from, $to, $unitId),
            'buku-kas' => $this->bukuKas($from, $to, $unitId, false),
            'buku-bank' => $this->bukuKas($from, $to, $unitId, true),
            'kartu-stok' => $this->kartuStok($from, $to, $unitId),
            'piutang-hutang' => $this->piutangHutang($asOf, $unitId),
            default => ['summary' => []],
        });
    }

    /**
     * Dataset resmi dari snapshot laporan_desa tersimpan (bukan live query).
     * Nilai mengikuti kolom snapshot, bukan ReportService saat ekspor.
     */
    public function fromLaporanDesa(LaporanDesa $laporan): array
    {
        $periode = $laporan->periode;
        $resmi = in_array($laporan->status, [LaporanDesaStatus::Disetujui, LaporanDesaStatus::Dikirim, LaporanDesaStatus::Diterima], true);
        $statusLabel = $resmi
            ? 'RESMI — '.strtoupper($laporan->status->value).' v'.$laporan->version
            : 'DRAFT v'.$laporan->version.' — belum disahkan';

        return [
            'type' => 'omzet-desa',
            'title' => 'Laporan Omzet Desa '.$laporan->nomor_laporan,
            'from' => $periode->tanggal_mulai->toDateString(),
            'to' => $periode->tanggal_selesai->toDateString(),
            'periode' => $periode->tanggal_mulai->format('d/m/Y').' – '.$periode->tanggal_selesai->format('d/m/Y'),
            'unit' => 'Konsolidasi (semua unit)',
            'generated_at' => optional($laporan->generated_at)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
            'status_label' => $statusLabel,
            'sumber' => 'Snapshot laporan_desa v'.$laporan->version.' (hash '.substr((string) $laporan->snapshot_hash, 0, 12).'…)',
            'summary' => [
                ['label' => 'Omzet dagang (penjualan bersih)', 'value' => (string) $laporan->omzet_dagang],
                ['label' => 'Pendapatan kredit (bunga + denda)', 'value' => (string) $laporan->pendapatan_kredit],
                ['label' => 'Pendapatan lain', 'value' => (string) $laporan->pendapatan_lain],
                ['label' => 'TOTAL OMZET DESA', 'value' => (string) $laporan->total_omzet],
            ],
            'detail' => null,
        ];
    }

    private function labaRugi(string $from, string $to, ?int $unitId, string $cutover): array
    {
        $r = $this->reports->labaRugi($from, $to, $unitId);
        $d = $this->statements->labaRugi($from, $to, $unitId, $cutover);

        $rows = [];
        $rows[] = [['text' => 'PENDAPATAN'], ['text' => '']];
        foreach ($d['pendapatan']['rows'] as $x) {
            $rows[] = [['text' => '   '.$x['label']], ['num' => $x['value']]];
        }
        $rows[] = [['text' => 'Harga Pokok Penjualan'], ['text' => '']];
        $rows[] = [['text' => '   Persediaan Awal'], ['num' => $d['hpp']['persediaan_awal']]];
        $rows[] = [['text' => '   Pembelian Bersih'], ['num' => $d['hpp']['pembelian_bersih']]];
        $rows[] = [['text' => '   Barang Tersedia Dijual'], ['num' => $d['hpp']['barang_tersedia']]];
        $rows[] = [['text' => '   Persediaan Akhir'], ['num' => $d['hpp']['persediaan_akhir']]];
        foreach ($d['hpp']['rows'] as $x) {
            $rows[] = [['text' => '      '.$x['label']], ['num' => $x['value']]];
        }
        $rows[] = [['text' => 'Harga Pokok Penjualan'], ['num' => $d['hpp']['hpp']]];
        $rows[] = [['text' => 'Laba Kotor Penjualan'], ['num' => $d['laba_kotor']]];
        $rows[] = [['text' => 'PENDAPATAN LAIN'], ['text' => '']];
        foreach ($d['pendapatan_lain']['rows'] as $x) {
            $rows[] = [['text' => '   '.$x['label']], ['num' => $x['value']]];
        }
        $rows[] = [['text' => 'Total Pendapatan Lain'], ['num' => $d['pendapatan_lain']['total']]];
        $rows[] = [['text' => 'Laba Setelah Pendapatan Lain'], ['num' => $d['laba_setelah_lain']]];
        $rows[] = [['text' => 'BIAYA OPERASIONAL'], ['text' => '']];
        foreach ($d['beban']['rows'] as $x) {
            $rows[] = [['text' => '   '.$x['label']], ['num' => $x['value']]];
        }
        $rows[] = [['text' => 'Total Biaya Operasional'], ['num' => $d['beban']['total']]];
        $rows[] = [['text' => 'BIAYA NON OPERASIONAL'], ['text' => '']];
        foreach ($d['beban_non_operasional']['rows'] as $x) {
            $rows[] = [['text' => '   '.$x['label']], ['num' => $x['value']]];
        }
        $rows[] = [['text' => 'Total Biaya Non Operasional'], ['num' => $d['beban_non_operasional']['total']]];
        $rows[] = [['text' => 'Laba Sebelum Pajak'], ['num' => $d['laba_sebelum_pajak']]];
        foreach ($d['pajak']['rows'] as $x) {
            $rows[] = [['text' => '   '.$x['label']], ['num' => $x['value']]];
        }
        $rows[] = [['text' => 'Laba Bersih'], ['num' => $d['laba_bersih']]];
        $rows[] = [['text' => 'Laba Kumulatif Berjalan'], ['num' => $d['laba_kumulatif']]];

        return [
            'summary' => [
                ['label' => 'Penjualan bersih', 'value' => $r['penjualan_bersih']],
                ['label' => 'Harga pokok penjualan', 'value' => $r['hpp']],
                ['label' => 'Laba kotor', 'value' => $r['laba_kotor']],
                ['label' => 'Pendapatan kredit (bunga + denda)', 'value' => $r['pendapatan_kredit']],
                ['label' => 'Pendapatan lain', 'value' => $r['pendapatan_lain']],
                ['label' => 'Beban operasional', 'value' => $r['beban_operasional']],
                ['label' => 'Beban penyusutan', 'value' => $r['penyusutan']],
                ['label' => 'Beban selisih persediaan', 'value' => $r['beban_selisih_persediaan']],
                ['label' => 'Laba bersih', 'value' => $r['laba_bersih']],
            ],
            'detail' => ['headers' => ['Keterangan', 'Nilai (Rp)'], 'rows' => $rows],
        ];
    }

    private function neraca(string $asOf, ?int $unitId, string $cutover): array
    {
        $r = $this->reports->neraca($asOf, $unitId, $cutover);
        $d = $this->statements->neraca($asOf, $unitId, $cutover);

        $rows = [];
        $rows[] = [['text' => 'AKTIVA LANCAR'], ['text' => '']];
        foreach ($d['aktiva_lancar']['rows'] as $x) {
            $rows[] = [['text' => '   '.$x['label']], ['num' => $x['value']]];
        }
        $rows[] = [['text' => 'Total Aktiva Lancar'], ['num' => $d['aktiva_lancar']['total']]];
        $rows[] = [['text' => 'AKTIVA TETAP'], ['text' => '']];
        foreach ($d['aktiva_tetap']['rows'] as $x) {
            $rows[] = [['text' => '   '.$x['label'].($x['contra'] ? ' (kontra)' : '')], ['num' => $x['value']]];
        }
        $rows[] = [['text' => 'Total Aktiva Tetap Bersih'], ['num' => $d['aktiva_tetap']['net']]];
        $rows[] = [['text' => 'TOTAL AKTIVA'], ['num' => $d['total_aktiva']]];
        $rows[] = [['text' => 'KEWAJIBAN'], ['text' => '']];
        foreach ($d['kewajiban']['rows'] as $x) {
            $rows[] = [['text' => '   '.$x['label']], ['num' => $x['value']]];
        }
        $rows[] = [['text' => 'Total Kewajiban'], ['num' => $d['kewajiban']['total']]];
        $rows[] = [['text' => 'EKUITAS'], ['text' => '']];
        foreach ($d['ekuitas']['rows'] as $x) {
            $rows[] = [['text' => '   '.$x['label']], ['num' => $x['value']]];
        }
        $rows[] = [['text' => '   Laba Berjalan'], ['num' => $d['ekuitas']['laba']]];
        $rows[] = [['text' => 'Total Ekuitas'], ['num' => $d['ekuitas']['total']]];
        $rows[] = [['text' => 'TOTAL PASIVA (Kewajiban + Ekuitas)'], ['num' => $d['total_pasiva']]];

        return [
            'summary' => [
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
            ],
            'detail' => ['headers' => ['Keterangan', 'Nilai (Rp)'], 'rows' => $rows],
        ];
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
        $d = $this->statements->perubahanModal($to, $unitId, $cutover);

        $rows = [];
        $rows[] = [['text' => 'MODAL AWAL PER DANA'], ['text' => '']];
        foreach ($d['rows'] as $x) {
            $rows[] = [['text' => '   '.$x['label']], ['num' => $x['value']]];
        }
        $rows[] = [['text' => 'Total Modal Awal'], ['num' => $d['total_awal']]];
        $rows[] = [['text' => 'Laba Periode'], ['num' => $d['laba']]];
        $rows[] = [['text' => 'Bagi Hasil / Penggunaan Dana'], ['num' => $d['perubahan_dana']]];
        $rows[] = [['text' => 'Modal Akhir'], ['num' => $d['modal_akhir']]];

        return [
            'summary' => [
                ['label' => 'Ekuitas awal', 'value' => $r['ekuitas_awal']],
                ['label' => 'Penambahan modal', 'value' => $r['penambahan_modal']],
                ['label' => 'Pengurangan modal', 'value' => $r['pengurangan_modal']],
                ['label' => 'Laba periode', 'value' => $r['laba_periode']],
                ['label' => 'Ekuitas akhir', 'value' => $r['ekuitas_akhir']],
            ],
            'detail' => ['headers' => ['Keterangan', 'Nilai (Rp)'], 'rows' => $rows],
        ];
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

    private function jurnalUmum(string $from, string $to, ?int $unitId): array
    {
        $j = $this->ledger->jurnalUmum($from, $to, $unitId);
        $rows = [];
        $last = null;
        foreach ($j['entries'] as $e) {
            $first = $e['transaksi_id'] !== $last;
            $last = $e['transaksi_id'];
            $rows[] = [
                ['text' => $first ? Carbon::parse($e['tanggal'])->format('d/m/Y') : ''],
                ['text' => $first ? $e['nomor'] : ''],
                ['text' => $e['akun']],
                ['text' => $e['uraian']],
                ['num' => $e['debit']],
                ['num' => $e['kredit']],
            ];
        }

        return [
            'summary' => [
                ['label' => 'Total debet', 'value' => $j['total_debit']],
                ['label' => 'Total kredit', 'value' => $j['total_kredit']],
            ],
            'status_label' => ($j['seimbang'] ? 'SEIMBANG (debet = kredit)' : 'TIDAK SEIMBANG').' — laporan kerja, belum disahkan',
            'detail' => [
                'headers' => ['Tanggal', 'No. Bukti', 'Akun', 'Uraian', 'Debet', 'Kredit'],
                'rows' => $rows,
                'foot' => [['text' => 'TOTAL'], ['text' => ''], ['text' => ''], ['text' => ''], ['num' => $j['total_debit']], ['num' => $j['total_kredit']]],
            ],
        ];
    }

    private function neracaSaldo(string $from, string $to, ?int $unitId): array
    {
        $n = $this->ledger->neracaSaldo($from, $to, $unitId);
        $rows = array_map(fn ($r) => [
            ['text' => $r['kode']], ['text' => $r['nama']],
            ['num' => $r['awal_d']], ['num' => $r['awal_k']],
            ['num' => $r['mut_d']], ['num' => $r['mut_k']],
            ['num' => $r['akhir_d']], ['num' => $r['akhir_k']],
        ], $n['rows']);
        $t = $n['total'];

        return [
            'summary' => [
                ['label' => 'Saldo akhir debet', 'value' => $t['akhir_d']],
                ['label' => 'Saldo akhir kredit', 'value' => $t['akhir_k']],
            ],
            'status_label' => ($n['seimbang'] ? 'SEIMBANG' : 'TIDAK SEIMBANG').' — laporan kerja, belum disahkan',
            'detail' => [
                'headers' => ['Kode', 'Akun', 'Saldo Awal D', 'Saldo Awal K', 'Jurnal D', 'Jurnal K', 'Saldo Akhir D', 'Saldo Akhir K'],
                'rows' => $rows,
                'foot' => [['text' => 'TOTAL'], ['text' => ''], ['num' => $t['awal_d']], ['num' => $t['awal_k']], ['num' => $t['mut_d']], ['num' => $t['mut_k']], ['num' => $t['akhir_d']], ['num' => $t['akhir_k']]],
            ],
        ];
    }

    private function bukuKas(string $from, string $to, ?int $unitId, bool $bank): array
    {
        $b = $this->ledger->bukuKas($from, $to, $unitId, $bank);
        $rows = [[['text' => ''], ['text' => ''], ['text' => 'Saldo awal'], ['text' => ''], ['text' => ''], ['text' => ''], ['num' => $b['saldo_awal']]]];
        foreach ($b['entries'] as $e) {
            $rows[] = [
                ['text' => Carbon::parse($e['tanggal'])->format('d/m/Y')],
                ['text' => $e['nomor']],
                ['text' => $e['uraian']],
                ['text' => $e['lawan']],
                ['num' => $e['masuk']],
                ['num' => $e['keluar']],
                ['num' => $e['saldo']],
            ];
        }

        return [
            'summary' => [
                ['label' => 'Saldo awal', 'value' => $b['saldo_awal']],
                ['label' => $bank ? 'Total masuk bank' : 'Total kas masuk', 'value' => $b['masuk']],
                ['label' => $bank ? 'Total keluar bank' : 'Total kas keluar', 'value' => $b['keluar']],
                ['label' => 'Saldo akhir', 'value' => $b['saldo_akhir']],
            ],
            'detail' => [
                'headers' => ['Tanggal', 'No. Bukti', 'Uraian', 'Akun Lawan', 'Masuk', 'Keluar', 'Saldo'],
                'rows' => $rows,
                'foot' => [['text' => 'TOTAL'], ['text' => ''], ['text' => ''], ['text' => ''], ['num' => $b['masuk']], ['num' => $b['keluar']], ['num' => $b['saldo_akhir']]],
            ],
        ];
    }

    private function kartuStok(string $from, string $to, ?int $unitId): array
    {
        $k = $this->ledger->kartuStok($from, $to, $unitId);
        $q = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0';
        $rows = array_map(fn ($r) => [
            ['text' => $r['kode']], ['text' => $r['nama']], ['text' => $r['satuan']],
            ['text' => $q($r['qty_awal'])], ['num' => $r['nilai_awal']],
            ['text' => $q($r['qty_masuk'])], ['num' => $r['nilai_masuk']],
            ['text' => $q($r['qty_keluar'])], ['num' => $r['nilai_keluar']],
            ['text' => $q($r['qty_akhir'])], ['num' => $r['nilai_akhir']],
        ], $k['rows']);
        $t = $k['total'];

        return [
            'summary' => [
                ['label' => 'Nilai persediaan awal', 'value' => $t['nilai_awal']],
                ['label' => 'Nilai masuk', 'value' => $t['nilai_masuk']],
                ['label' => 'Nilai keluar (HPP)', 'value' => $t['nilai_keluar']],
                ['label' => 'Nilai persediaan akhir', 'value' => $t['nilai_akhir']],
            ],
            'detail' => [
                'headers' => ['Kode', 'Produk', 'Satuan', 'Qty Awal', 'Nilai Awal', 'Qty Masuk', 'Nilai Masuk', 'Qty Keluar', 'Nilai Keluar', 'Qty Akhir', 'Nilai Akhir'],
                'rows' => $rows,
                'foot' => [['text' => 'TOTAL'], ['text' => ''], ['text' => ''], ['text' => ''], ['num' => $t['nilai_awal']], ['text' => ''], ['num' => $t['nilai_masuk']], ['text' => ''], ['num' => $t['nilai_keluar']], ['text' => ''], ['num' => $t['nilai_akhir']]],
            ],
        ];
    }

    private function piutangHutang(string $asOf, ?int $unitId): array
    {
        $p = $this->ledger->piutangHutang($asOf, $unitId);
        $rows = [];
        foreach (['piutang' => 'PIUTANG USAHA', 'hutang' => 'HUTANG USAHA'] as $key => $label) {
            $rows[] = [['text' => $label], ['text' => ''], ['text' => ''], ['text' => ''], ['text' => ''], ['text' => ''], ['text' => ''], ['text' => '']];
            foreach ($p[$key]['rows'] as $r) {
                $rows[] = [
                    ['text' => $r['nomor']],
                    ['text' => Carbon::parse($r['tanggal'])->format('d/m/Y')],
                    ['text' => $r['jatuh_tempo'] ? Carbon::parse($r['jatuh_tempo'])->format('d/m/Y') : '—'],
                    ['text' => $r['pihak']],
                    ['num' => $r['total']],
                    ['num' => $r['dibayar']],
                    ['num' => $r['sisa']],
                    ['text' => $r['lewat_hari'] > 0 ? $r['lewat_hari'].' hari' : 'belum jatuh tempo'],
                ];
            }
            $rows[] = [['text' => 'Total '.strtolower($label)], ['text' => ''], ['text' => ''], ['text' => ''], ['text' => ''], ['text' => ''], ['num' => $p[$key]['total']], ['text' => '']];
        }

        return [
            'summary' => [
                ['label' => 'Total piutang usaha', 'value' => $p['piutang']['total']],
                ['label' => 'Total hutang usaha', 'value' => $p['hutang']['total']],
            ],
            'detail' => [
                'headers' => ['No. Nota', 'Tanggal', 'Jatuh Tempo', 'Pihak', 'Total', 'Dibayar', 'Sisa', 'Lewat Jatuh Tempo'],
                'rows' => $rows,
            ],
        ];
    }
}
