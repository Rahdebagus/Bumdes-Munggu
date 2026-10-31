<?php

namespace App\Domain\Reporting;

use App\Domain\Cash\SettlementQuery;
use App\Domain\Inventory\StockSnapshot;
use App\Domain\OpeningBalance\OpeningItems;
use App\Domain\Shared\Decimal;
use App\Enums\SaldoRincianJenis;
use App\Models\Akun;
use App\Models\MutasiStok;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Illuminate\Support\Carbon;

/**
 * Laporan turunan Jurnal Umum (semua dari jurnal terposting posted_at≠null +
 * saldo awal terkunci, sama seperti ReportService): Jurnal Umum, Neraca Saldo,
 * Buku Kas, Buku Bank, Kartu Stok (ringkasan persediaan) dan Piutang/Hutang.
 * Nominal string decimal 2; kuantitas decimal 4.
 */
class LedgerReports
{
    public function __construct(private ReportService $reports) {}

    /**
     * Jurnal Umum: baris double-entry per bukti, urut tanggal. Total debet harus
     * sama dengan total kredit.
     *
     * @return array{entries: array<int, array<string, mixed>>, total_debit: string, total_kredit: string, seimbang: bool}
     */
    public function jurnalUmum(string $from, string $to, ?int $unitId = null, ?string $jenis = null, ?string $q = null): array
    {
        $rows = TransaksiDetail::query()
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->join('akun', 'akun.id', '=', 'transaksi_detail.akun_id')
            ->leftJoin('pihak', 'pihak.id', '=', 'transaksi.pihak_id')
            ->whereNotNull('transaksi.posted_at')
            ->whereDate('transaksi.tanggal', '>=', $from)
            ->whereDate('transaksi.tanggal', '<=', $to)
            ->when($unitId, fn ($w) => $w->where('transaksi.unit_usaha_id', $unitId))
            ->when($jenis, fn ($w) => $w->where('transaksi.jenis_transaksi', $jenis))
            ->when($q, fn ($w) => $w->where(fn ($x) => $x
                ->where('transaksi.nomor_transaksi', 'like', "%{$q}%")
                ->orWhere('transaksi.keterangan', 'like', "%{$q}%")
                ->orWhere('akun.kode_akun', 'like', "%{$q}%")
                ->orWhere('akun.nama_akun', 'like', "%{$q}%")))
            ->orderBy('transaksi.tanggal')->orderBy('transaksi.id')->orderBy('transaksi_detail.nomor_baris')
            ->get([
                'transaksi.id as transaksi_id', 'transaksi.tanggal', 'transaksi.nomor_transaksi', 'transaksi.jenis_transaksi',
                'transaksi.keterangan as ket_transaksi', 'pihak.nama as pihak',
                'akun.kode_akun', 'akun.nama_akun', 'transaksi_detail.keterangan', 'transaksi_detail.debit', 'transaksi_detail.kredit',
            ]);

        $d = '0';
        $k = '0';
        $entries = [];
        foreach ($rows as $r) {
            $d = Decimal::add($d, (string) $r->debit);
            $k = Decimal::add($k, (string) $r->kredit);
            $entries[] = [
                'transaksi_id' => (int) $r->transaksi_id,
                'tanggal' => Carbon::parse($r->tanggal)->toDateString(),
                'nomor' => (string) $r->nomor_transaksi,
                'jenis' => (string) $r->jenis_transaksi,
                'akun' => $r->kode_akun.' — '.$r->nama_akun,
                'kode_akun' => (string) $r->kode_akun,
                'uraian' => trim(($r->keterangan ?? '').($r->ket_transaksi && $r->ket_transaksi !== $r->keterangan ? ' · '.$r->ket_transaksi : '').($r->pihak ? ' · '.$r->pihak : ''), ' ·'),
                'debit' => Decimal::of((string) $r->debit),
                'kredit' => Decimal::of((string) $r->kredit),
            ];
        }

        return ['entries' => $entries, 'total_debit' => $d, 'total_kredit' => $k, 'seimbang' => Decimal::compare($d, $k) === 0];
    }

    /**
     * Neraca Saldo: Saldo Awal – Mutasi (Debet/Kredit) – Saldo Akhir per akun.
     * Saldo disajikan sebagai sisi debet/kredit (positif).
     *
     * @return array{rows: array<int, array<string, string>>, total: array<string, string>, seimbang: bool}
     */
    public function neracaSaldo(string $from, string $to, ?int $unitId = null): array
    {
        $sebelum = Carbon::parse($from)->subDay()->toDateString();
        $awal = $this->reports->accountNet(null, $sebelum, includeOpening: true, unitId: $unitId);

        $mutasi = TransaksiDetail::query()
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->whereNotNull('transaksi.posted_at')
            ->whereDate('transaksi.tanggal', '>=', $from)
            ->whereDate('transaksi.tanggal', '<=', $to)
            ->when($unitId, fn ($q) => $q->where('transaksi.unit_usaha_id', $unitId))
            ->groupBy('transaksi_detail.akun_id')
            ->selectRaw('transaksi_detail.akun_id, SUM(transaksi_detail.debit) as d, SUM(transaksi_detail.kredit) as k')
            ->get()->keyBy('akun_id');

        $total = ['awal_d' => '0', 'awal_k' => '0', 'mut_d' => '0', 'mut_k' => '0', 'akhir_d' => '0', 'akhir_k' => '0'];
        $rows = [];

        foreach (Akun::orderBy('kode_akun')->get() as $akun) {
            $netAwal = $awal[$akun->id] ?? '0';
            $md = Decimal::of((string) ($mutasi[$akun->id]->d ?? '0'));
            $mk = Decimal::of((string) ($mutasi[$akun->id]->k ?? '0'));
            $netAkhir = Decimal::add($netAwal, Decimal::sub($md, $mk));

            if (Decimal::isZero($netAwal) && Decimal::isZero($md) && Decimal::isZero($mk)) {
                continue;
            }

            $row = [
                'kode' => $akun->kode_akun,
                'nama' => $akun->nama_akun,
                'awal_d' => Decimal::compare($netAwal, 0) > 0 ? Decimal::of($netAwal) : '0.00',
                'awal_k' => Decimal::compare($netAwal, 0) < 0 ? Decimal::mul($netAwal, '-1') : '0.00',
                'mut_d' => $md,
                'mut_k' => $mk,
                'akhir_d' => Decimal::compare($netAkhir, 0) > 0 ? Decimal::of($netAkhir) : '0.00',
                'akhir_k' => Decimal::compare($netAkhir, 0) < 0 ? Decimal::mul($netAkhir, '-1') : '0.00',
            ];
            foreach (array_keys($total) as $key) {
                $total[$key] = Decimal::add($total[$key], $row[$key]);
            }
            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'seimbang' => Decimal::compare($total['awal_d'], $total['awal_k']) === 0
                && Decimal::compare($total['mut_d'], $total['mut_k']) === 0
                && Decimal::compare($total['akhir_d'], $total['akhir_k']) === 0,
        ];
    }

    /** Akun kas tunai (is_cash bukan bank) atau akun bank. @return array<int, int> */
    public function cashAccountIds(bool $bank): array
    {
        return Akun::where('is_cash', true)->get()
            ->filter(fn ($a) => $bank === ($a->system_key === 'bank' || str_contains(strtolower($a->nama_akun), 'bank')))
            ->pluck('id')->all();
    }

    /**
     * Buku Kas / Buku Bank: saldo awal, mutasi masuk/keluar per transaksi
     * dengan akun lawan & uraian, saldo berjalan.
     *
     * @return array{saldo_awal: string, entries: array<int, array<string, string>>, masuk: string, keluar: string, saldo_akhir: string}
     */
    public function bukuKas(string $from, string $to, ?int $unitId = null, bool $bank = false): array
    {
        $ids = $this->cashAccountIds($bank);
        $sebelum = Carbon::parse($from)->subDay()->toDateString();
        $awalNet = $this->reports->accountNet(null, $sebelum, includeOpening: true, unitId: $unitId);
        $saldo = array_reduce($ids, fn ($s, $id) => Decimal::add($s, $awalNet[$id] ?? '0'), '0');
        $saldoAwal = $saldo;

        $lines = TransaksiDetail::query()
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->join('akun', 'akun.id', '=', 'transaksi_detail.akun_id')
            ->whereIn('transaksi_detail.akun_id', $ids ?: [0])
            ->whereNotNull('transaksi.posted_at')
            ->whereDate('transaksi.tanggal', '>=', $from)
            ->whereDate('transaksi.tanggal', '<=', $to)
            ->when($unitId, fn ($q) => $q->where('transaksi.unit_usaha_id', $unitId))
            ->orderBy('transaksi.tanggal')->orderBy('transaksi.id')->orderBy('transaksi_detail.nomor_baris')
            ->get(['transaksi.id as tid', 'transaksi.tanggal', 'transaksi.nomor_transaksi', 'transaksi.keterangan as ket',
                'akun.nama_akun', 'transaksi_detail.keterangan', 'transaksi_detail.debit', 'transaksi_detail.kredit']);

        // Nama akun lawan per transaksi (baris non-kas).
        $lawan = TransaksiDetail::query()
            ->join('akun', 'akun.id', '=', 'transaksi_detail.akun_id')
            ->whereIn('transaksi_detail.transaksi_id', $lines->pluck('tid')->unique()->all() ?: [0])
            ->whereNotIn('transaksi_detail.akun_id', $ids ?: [0])
            ->get(['transaksi_detail.transaksi_id', 'akun.kode_akun', 'akun.nama_akun'])
            ->groupBy('transaksi_id')
            ->map(fn ($g) => $g->map(fn ($x) => $x->kode_akun.' '.$x->nama_akun)->unique()->implode(', '));

        $masuk = '0';
        $keluar = '0';
        $entries = [];
        foreach ($lines as $l) {
            $saldo = Decimal::add($saldo, Decimal::sub((string) $l->debit, (string) $l->kredit));
            $masuk = Decimal::add($masuk, (string) $l->debit);
            $keluar = Decimal::add($keluar, (string) $l->kredit);
            $entries[] = [
                'tanggal' => Carbon::parse($l->tanggal)->toDateString(),
                'nomor' => (string) $l->nomor_transaksi,
                'akun' => (string) $l->nama_akun,
                'uraian' => trim(($l->ket ?: $l->keterangan) ?? ''),
                'lawan' => (string) ($lawan[$l->tid] ?? ''),
                'masuk' => Decimal::of((string) $l->debit),
                'keluar' => Decimal::of((string) $l->kredit),
                'saldo' => $saldo,
            ];
        }

        return ['saldo_awal' => $saldoAwal, 'entries' => $entries, 'masuk' => $masuk, 'keluar' => $keluar, 'saldo_akhir' => $saldo];
    }

    /**
     * Laporan Persediaan (ringkasan kartu stok) per produk: awal, masuk,
     * keluar, akhir (kuantitas & nilai moving average).
     *
     * @return array{rows: array<int, array<string, string>>, total: array<string, string>}
     */
    public function kartuStok(string $from, string $to, ?int $unitId = null): array
    {
        $produk = Produk::query()->where('lacak_stok', true)
            ->when($unitId, fn ($q) => $q->where('unit_usaha_id', $unitId))
            ->orderBy('kode_produk')->get();

        // Tiga query berkelompok untuk semua produk (bukan tiga query per produk).
        $snapshot = app(StockSnapshot::class);
        $saldoAwal = $snapshot->terakhir($from, inklusif: false, unitId: $unitId);
        $saldoAkhir = $snapshot->terakhir($to, unitId: $unitId);
        $mutasi = MutasiStok::query()
            ->whereIn('produk_id', $produk->pluck('id'))
            ->whereDate('tanggal', '>=', $from)->whereDate('tanggal', '<=', $to)
            ->groupBy('produk_id')
            ->selectRaw('produk_id, SUM(kuantitas_masuk) qm, SUM(kuantitas_keluar) qk, SUM(nilai_masuk) nm, SUM(nilai_keluar) nk')
            ->get()->keyBy('produk_id');

        $rows = [];
        $total = ['nilai_awal' => '0', 'nilai_masuk' => '0', 'nilai_keluar' => '0', 'nilai_akhir' => '0'];
        foreach ($produk as $p) {
            $awal = $saldoAwal->get($p->id);
            $mv = $mutasi->get($p->id);
            $akhir = $saldoAkhir->get($p->id);

            if (! $awal && ! $akhir) {
                continue;
            }

            $row = [
                'kode' => $p->kode_produk,
                'nama' => $p->nama_produk,
                'satuan' => $p->satuan,
                'qty_awal' => Decimal::of((string) ($awal->saldo_kuantitas ?? 0), Decimal::QTY),
                'nilai_awal' => Decimal::of((string) ($awal->saldo_nilai ?? 0)),
                'qty_masuk' => Decimal::of((string) ($mv->qm ?? 0), Decimal::QTY),
                'nilai_masuk' => Decimal::of((string) ($mv->nm ?? 0)),
                'qty_keluar' => Decimal::of((string) ($mv->qk ?? 0), Decimal::QTY),
                'nilai_keluar' => Decimal::of((string) ($mv->nk ?? 0)),
                'qty_akhir' => Decimal::of((string) ($akhir->saldo_kuantitas ?? 0), Decimal::QTY),
                'nilai_akhir' => Decimal::of((string) ($akhir->saldo_nilai ?? 0)),
            ];
            foreach (array_keys($total) as $key) {
                $total[$key] = Decimal::add($total[$key], $row[$key]);
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Piutang & Hutang usaha per nota kredit s/d tanggal: total, dibayar, sisa,
     * umur (hari lewat jatuh tempo).
     *
     * @return array{piutang: array{rows: array<int, array<string, mixed>>, total: string}, hutang: array{rows: array<int, array<string, mixed>>, total: string}}
     */
    public function piutangHutang(string $asOf, ?int $unitId = null): array
    {
        $out = [];
        foreach (['piutang' => 'penjualan', 'hutang' => 'pembelian'] as $key => $jenis) {
            $invoices = Transaksi::with('pihak')
                ->where('jenis_transaksi', $jenis)->where('metode_pembayaran', 'kredit')
                ->whereNotNull('posted_at')->where('status', 'posted')
                ->whereDate('tanggal', '<=', $asOf)
                ->when($unitId, fn ($q) => $q->where('unit_usaha_id', $unitId))
                ->orderBy('tanggal')->get();

            $rows = [];
            $total = '0';

            // Nota lama dari saldo awal (cutover) lebih dulu — urut tanggal nota.
            $jenisOpening = $key === 'piutang' ? SaldoRincianJenis::PiutangUsaha : SaldoRincianJenis::UtangUsaha;
            foreach (app(OpeningItems::class)->outstanding($jenisOpening, $asOf, unitId: $unitId) as $o) {
                $r = $o['rincian'];
                $umur = $r->jatuh_tempo ? (int) $r->jatuh_tempo->diffInDays(Carbon::parse($asOf), false) : 0;
                $rows[] = [
                    'nomor' => $r->kode_referensi.' (saldo awal)',
                    'tanggal' => $r->tanggal_asal?->toDateString(),
                    'jatuh_tempo' => $r->jatuh_tempo?->toDateString(),
                    'pihak' => $r->pihak?->nama ?? '—',
                    'total' => Decimal::of((string) $r->nominal),
                    'dibayar' => $o['dibayar'],
                    'sisa' => $o['sisa'],
                    'lewat_hari' => max(0, $umur),
                ];
                $total = Decimal::add($total, $o['sisa']);
            }

            $terbayar = app(SettlementQuery::class)->invoiceAllocatedMany($invoices->pluck('id')->all(), $asOf);
            foreach ($invoices as $inv) {
                $dibayar = $terbayar[$inv->id] ?? '0.00';
                $sisa = Decimal::sub((string) $inv->total, $dibayar);
                if (! Decimal::isPositive($sisa)) {
                    continue;
                }
                $umur = $inv->jatuh_tempo ? (int) $inv->jatuh_tempo->diffInDays(Carbon::parse($asOf), false) : 0;
                $rows[] = [
                    'nomor' => $inv->nomor_transaksi,
                    'tanggal' => $inv->tanggal->toDateString(),
                    'jatuh_tempo' => $inv->jatuh_tempo?->toDateString(),
                    'pihak' => $inv->pihak?->nama ?? '—',
                    'total' => Decimal::of((string) $inv->total),
                    'dibayar' => Decimal::of($dibayar),
                    'sisa' => $sisa,
                    'lewat_hari' => max(0, $umur),
                ];
                $total = Decimal::add($total, $sisa);
            }
            $out[$key] = ['rows' => $rows, 'total' => $total];
        }

        return $out;
    }
}
