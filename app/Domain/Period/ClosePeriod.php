<?php

namespace App\Domain\Period;

use App\Domain\Reporting\ReportService;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Enums\PeriodeStatus;
use App\Models\AsetTetap;
use App\Models\LogAktivitas;
use App\Models\PeriodeAkuntansi;
use App\Models\StokOpname;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Menutup periode akuntansi (dokumen10 §Rekonsiliasi — FAIL pada penutupan).
 * Menolak bila ada rekonsiliasi FAIL, penyusutan aset eligible belum diposting,
 * atau stok opname disetujui belum diposting. Draft tertinggal = peringatan
 * (tidak memblokir, dicatat). Sukses → status ditutup + closed_at/by + revision++.
 */
class ClosePeriod
{
    public function __construct(private ReportService $reports) {}

    /**
     * Daftar penghambat penutupan (FAIL). Kosong = boleh ditutup.
     *
     * @return array<int, string>
     */
    public function blockers(PeriodeAkuntansi $periode): array
    {
        $blockers = [];
        $from = $this->cutoverStart($periode);
        $to = $periode->tanggal_selesai->toDateString();

        foreach ($this->reports->reconciliation($from, $to, null, $from) as $c) {
            if (! $c['match'] && $c['severity'] === 'FAIL') {
                $blockers[] = "Rekonsiliasi {$c['label']} tidak cocok ({$c['expected']} ≠ {$c['actual']}).";
            }
        }

        foreach ($this->assetsMissingDepreciation($periode) as $kode) {
            $blockers[] = "Penyusutan aset {$kode} belum diposting untuk periode ini.";
        }

        $opnamePending = StokOpname::where('periode_id', $periode->id)
            ->where('status', 'disetujui')->count();
        if ($opnamePending > 0) {
            $blockers[] = "Ada {$opnamePending} stok opname disetujui belum diposting.";
        }

        return $blockers;
    }

    /** @return array<int, string> peringatan (tidak memblokir) */
    public function warnings(PeriodeAkuntansi $periode): array
    {
        $warnings = [];
        $draft = Transaksi::where('periode_id', $periode->id)->where('status', 'draft')->count();
        if ($draft > 0) {
            $warnings[] = "Ada {$draft} transaksi draft tertinggal di periode ini.";
        }

        return $warnings;
    }

    public function handle(PeriodeAkuntansi $periode, User $actor): void
    {
        DB::transaction(function () use ($periode, $actor) {
            $p = PeriodeAkuntansi::whereKey($periode->id)->lockForUpdate()->firstOrFail();

            if ($p->status !== PeriodeStatus::Terbuka) {
                throw DomainException::invalidState("Periode {$p->bulan}/{$p->tahun} sudah tertutup.");
            }
            if ($p->opening_locked_at === null) {
                throw DomainException::openingNotLocked("Saldo awal periode {$p->bulan}/{$p->tahun} belum dikunci.");
            }

            $blockers = $this->blockers($p);
            if ($blockers !== []) {
                throw DomainException::closeBlocked('Penutupan ditolak: '.implode(' ', $blockers));
            }

            $p->update([
                'status' => PeriodeStatus::Ditutup->value,
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'revision' => $p->revision + 1,
            ]);

            LogAktivitas::create([
                'user_id' => $actor->id,
                'modul' => 'Periode',
                'aksi' => 'close',
                'referensi_tipe' => 'periode_akuntansi',
                'referensi_id' => $p->id,
                'alasan' => "Tutup periode {$p->bulan}/{$p->tahun}",
            ]);
        });
    }

    private function cutoverStart(PeriodeAkuntansi $periode): string
    {
        $cutover = PeriodeAkuntansi::whereNotNull('opening_locked_at')
            ->orderBy('tanggal_mulai')->first();

        return $cutover?->tanggal_mulai?->toDateString() ?? $periode->tanggal_mulai->toDateString();
    }

    /**
     * Aset eligible (mulai susut ≤ akhir periode, basis tersisa) yang belum punya
     * penyusutan terposting untuk periode ini.
     *
     * @return array<int, string>
     */
    private function assetsMissingDepreciation(PeriodeAkuntansi $periode): array
    {
        $to = $periode->tanggal_selesai->toDateString();
        $missing = [];

        $assets = AsetTetap::where('status', 'aktif')
            ->whereDate('tanggal_mulai_susut', '<=', $to)->get();

        foreach ($assets as $a) {
            $basis = Decimal::sub((string) $a->harga_perolehan, (string) $a->nilai_residu);
            $accumulated = Decimal::add((string) $a->akumulasi_awal, (string) Transaksi::where('sumber_tipe', 'aset_tetap')
                ->where('sumber_id', $a->id)->where('jenis_transaksi', 'penyusutan')
                ->whereNotNull('posted_at')->where('status', 'posted')
                ->whereDate('tanggal', '<=', $to)->sum('total'));

            // Sudah tersusut penuh → tidak eligible.
            if (Decimal::compare($accumulated, $basis) >= 0) {
                continue;
            }

            $hasThisPeriod = Transaksi::where('posting_key', "DEP:{$a->id}:{$periode->id}")
                ->whereNotNull('posted_at')->where('status', 'posted')->exists();

            if (! $hasThisPeriod) {
                $missing[] = $a->kode_aset;
            }
        }

        return $missing;
    }
}
