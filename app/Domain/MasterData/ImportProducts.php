<?php

namespace App\Domain\MasterData;

use App\Domain\Inventory\PostOpeningStock;
use App\Domain\Shared\Decimal;
use App\Enums\ProdukKategori;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Impor master produk dari CSV/XLSX (dipakai ProdukBumdesSeeder, perintah
 * `produk:import`, dan tombol Impor di Data Produk).
 *
 * Kolom (header baris pertama, tidak peka huruf besar/kecil):
 *   nama*, satuan, kategori*, stok, harga_beli, harga_jual, sumber_harga_jual,
 *   stok_minimum (opsional). Kolom kode diabaikan: kode selalu dari kategori.
 *
 * - Upsert berdasarkan nama (aman diulang): produk ada → harga/kategori/satuan
 *   diperbarui; produk baru → kode otomatis per kategori (KSM-001, ATK-014).
 * - Stok TIDAK ditulis langsung. Bila tanggal stok awal diberikan, produk yang
 *   belum punya mutasi mendapat stok awal (mutasi opening + jurnal) lewat
 *   PostOpeningStock. Produk yang sudah bermutasi: selisih stok → stok opname.
 */
class ImportProducts
{
    private const ALIASES = [
        'nama_produk' => 'nama', 'produk' => 'nama', 'nama_barang' => 'nama',
        'stok_akhir' => 'stok', 'stok_awal' => 'stok', 'qty' => 'stok',
        'harga_pokok' => 'harga_beli', 'hpp' => 'harga_beli',
        'sumber' => 'sumber_harga_jual',
    ];

    public function __construct(
        private SaveProduct $saver,
        private PostOpeningStock $openingStock,
    ) {}

    /**
     * Baca file menjadi baris asosiatif ber-header ternormalisasi.
     *
     * @return array<int, array<string, string>>
     */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File tidak ditemukan: {$path}");
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $raw = in_array($ext, ['xlsx', 'xls', 'ods'], true) ? $this->readSpreadsheet($path) : $this->readCsv($path);

        if ($raw === []) {
            return [];
        }

        $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), array_shift($raw));
        $rows = [];
        foreach ($raw as $cells) {
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                if ($key !== '') {
                    $row[$key] = trim((string) ($cells[$i] ?? ''));
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @param  array{tanggal_stok?: ?string, akun_lawan_id?: ?int, actor?: ?User, dry_run?: bool}  $options
     * @return array{dibuat: int, diperbarui: int, galat: array<int, string>, stok_awal: int, stok_dokumen: array<int, string>, stok_dilewati: array<int, string>}
     */
    public function import(array $rows, array $options = []): array
    {
        $report = ['dibuat' => 0, 'diperbarui' => 0, 'galat' => [], 'stok_awal' => 0, 'stok_dokumen' => [], 'stok_dilewati' => []];
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $stockLines = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $baris = $i + 2; // + header, 1-based
                $nama = preg_replace('/\s+/', ' ', trim($row['nama'] ?? ''));
                if ($nama === '') {
                    $report['galat'][] = "Baris {$baris}: nama kosong, dilewati.";

                    continue;
                }

                $kategori = ProdukKategori::tryFrom(strtolower(trim($row['kategori'] ?? '')));
                if (! $kategori) {
                    $report['galat'][] = "Baris {$baris} ({$nama}): kategori '".($row['kategori'] ?? '')."' tidak dikenal (pakai: ".implode(', ', ProdukKategori::values()).').';

                    continue;
                }

                $sumber = trim($row['sumber_harga_jual'] ?? '');
                $data = [
                    'nama_produk' => mb_substr($nama, 0, 100),
                    'kategori' => $kategori->value,
                    'harga_beli' => $this->number($row['harga_beli'] ?? '0'),
                    'harga_jual' => $this->number($row['harga_jual'] ?? '0'),
                    'sumber_harga_jual' => $sumber !== '' ? mb_substr($sumber, 0, 100) : null,
                ];
                $satuan = $this->satuan($row['satuan'] ?? '');
                if (isset($row['stok_minimum']) && $row['stok_minimum'] !== '') {
                    $data['stok_minimum'] = $this->number($row['stok_minimum']);
                }

                $produk = Produk::where('nama_produk', $data['nama_produk'])->first();
                if ($produk) {
                    if ($satuan !== null) {
                        $data['satuan'] = $satuan;
                    }
                    $this->saver->handle($data, $produk);
                    $report['diperbarui']++;
                } else {
                    $data['satuan'] = $satuan ?? 'Pcs';
                    $data['status'] = 'aktif';
                    $produk = $this->saver->handle($data);
                    $report['dibuat']++;
                }

                $stok = $this->number($row['stok'] ?? '0');
                if (Decimal::isPositive($stok, Decimal::QTY)) {
                    $stockLines[] = ['produk' => $produk->refresh(), 'kuantitas' => $stok, 'harga' => $data['harga_beli']];
                }
            }

            if (! empty($options['tanggal_stok']) && $stockLines !== []) {
                if (! isset($options['actor'])) {
                    throw new RuntimeException('Stok awal butuh user Admin aktif sebagai pemosting.');
                }
                $hasil = $this->openingStock->handle($stockLines, $options['tanggal_stok'], $options['actor'], $options['akun_lawan_id'] ?? null);
                $report['stok_dokumen'] = array_map(fn ($t) => $t->nomor_transaksi, $hasil['transaksi']);
                $report['stok_dilewati'] = $hasil['dilewati'];
                $report['stok_awal'] = count($stockLines) - count($hasil['dilewati']);
            }

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $report;
    }

    /** @return array<int, array<int, mixed>> */
    private function readSpreadsheet(string $path): array
    {
        return IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
    }

    /** @return array<int, array<int, string>> */
    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // BOM
        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $rows = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = $cells;
        }
        fclose($handle);

        return $rows;
    }

    private function normalizeHeader(string $h): string
    {
        $key = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($h)));
        $key = trim((string) $key, '_');

        return self::ALIASES[$key] ?? $key;
    }

    /** Angka dari sel: "51.000", "51000", "51,000.50", "1.500,5" → string desimal. */
    private function number(string $v): string
    {
        $v = trim(str_ireplace(['rp', ' '], '', $v));
        if ($v === '' || $v === '-') {
            return '0';
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $v)) {        // 1.500.000,50
            $v = str_replace(['.', ','], ['', '.'], $v);
        } elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $v)) {  // 1,500,000.50
            $v = str_replace(',', '', $v);
        } else {
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? $v : '0';
    }

    private function satuan(string $v): ?string
    {
        $v = trim($v);

        return ($v === '' || $v === '-') ? null : mb_substr($v, 0, 20);
    }
}
