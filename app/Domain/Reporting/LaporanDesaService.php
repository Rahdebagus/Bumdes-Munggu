<?php

namespace App\Domain\Reporting;

use App\Domain\Shared\DomainException;
use App\Enums\LaporanDesaStatus;
use App\Models\BuktiTransaksi;
use App\Models\LaporanDesa;
use App\Models\LogAktivitas;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Siklus snapshot Laporan Omzet Desa (dokumen10 §Alur snapshot). Draft dari
 * ReportService (konsolidasi satu periode) → submit (regenerate + data_revision)
 * → approve (lock periode+laporan, cek revision & bukti) → immutable. Koreksi
 * pasca approve = versi baru dengan previous_version_id; versi lama tetap ada.
 * Ekspor resmi dari snapshot, bukan live query.
 */
class LaporanDesaService
{
    private const FINAL = [LaporanDesaStatus::Disetujui, LaporanDesaStatus::Dikirim, LaporanDesaStatus::Diterima];

    public function __construct(private ReportService $reports) {}

    /** Buat/segarkan draft snapshot untuk periode. */
    public function generate(PeriodeAkuntansi $periode, User $actor): LaporanDesa
    {
        return DB::transaction(function () use ($periode, $actor) {
            $latest = LaporanDesa::where('periode_id', $periode->id)->orderByDesc('version')->lockForUpdate()->first();

            if ($latest && ! in_array($latest->status, [LaporanDesaStatus::Draft, LaporanDesaStatus::Revisi], true)) {
                throw DomainException::invalidState('Laporan periode ini sudah diajukan/disetujui; gunakan revisi untuk koreksi.');
            }

            $payload = $this->buildSnapshot($periode);

            if ($latest) {
                $latest->update(array_merge($payload, ['created_by' => $actor->id, 'generated_at' => now()]));
                $this->log($actor, $latest, 'generate');

                return $latest->fresh();
            }

            $laporan = LaporanDesa::create(array_merge($payload, [
                'nomor_laporan' => $this->nomor($periode, 1),
                'periode_id' => $periode->id,
                'status' => LaporanDesaStatus::Draft->value,
                'created_by' => $actor->id,
                'version' => 1,
                'generated_at' => now(),
            ]));
            $this->log($actor, $laporan, 'generate');

            return $laporan;
        });
    }

    /** Draft/revisi → diajukan (regenerate nilai + data_revision terkini). */
    public function submit(LaporanDesa $laporan, User $actor): LaporanDesa
    {
        return DB::transaction(function () use ($laporan, $actor) {
            $l = LaporanDesa::whereKey($laporan->id)->lockForUpdate()->firstOrFail();

            // Draft/revisi diajukan; diajukan boleh diajukan ulang (regenerate)
            // setelah data berubah. Yang sudah final (disetujui+) tidak bisa.
            if (in_array($l->status, self::FINAL, true)) {
                throw DomainException::invalidState('Laporan sudah disetujui; gunakan revisi untuk koreksi.');
            }

            $payload = $this->buildSnapshot($l->periode);
            $l->update(array_merge($payload, [
                'status' => LaporanDesaStatus::Diajukan->value,
                'submitted_at' => now(),
            ]));
            $this->log($actor, $l, 'submit');

            return $l->fresh();
        });
    }

    /** Diajukan → disetujui. Tolak bila data berubah (revision) atau bukti belum valid. */
    public function approve(LaporanDesa $laporan, User $actor): LaporanDesa
    {
        return DB::transaction(function () use ($laporan, $actor) {
            $l = LaporanDesa::whereKey($laporan->id)->lockForUpdate()->firstOrFail();
            PeriodeAkuntansi::whereKey($l->periode_id)->lockForUpdate()->firstOrFail();

            if ($l->status !== LaporanDesaStatus::Diajukan) {
                throw DomainException::invalidState('Hanya laporan berstatus diajukan yang dapat disetujui.');
            }

            $liveRevision = $this->reports->dataRevision(
                $l->periode->tanggal_mulai->toDateString(),
                $l->periode->tanggal_selesai->toDateString(),
            );
            if ($liveRevision !== (int) $l->data_revision) {
                throw DomainException::revisionChanged(
                    "Data periode berubah sejak diajukan (revisi {$l->data_revision} → {$liveRevision}); ajukan ulang."
                );
            }

            if ($this->unverifiedEvidence($l->periode) > 0) {
                throw DomainException::invalidState('Masih ada bukti transaksi belum valid; approval omzet diblokir.');
            }

            $l->update([
                'status' => LaporanDesaStatus::Disetujui->value,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
            $this->log($actor, $l, 'approve');

            return $l->fresh();
        });
    }

    /** Koreksi pasca approval: versi baru (draft) tertaut ke versi sebelumnya. */
    public function revise(LaporanDesa $laporan, User $actor): LaporanDesa
    {
        return DB::transaction(function () use ($laporan, $actor) {
            $l = LaporanDesa::whereKey($laporan->id)->lockForUpdate()->firstOrFail();

            if (! in_array($l->status, self::FINAL, true)) {
                throw DomainException::invalidState('Revisi hanya untuk laporan yang sudah disetujui/dikirim/diterima.');
            }

            $nextVersion = (int) LaporanDesa::where('periode_id', $l->periode_id)->max('version') + 1;
            $payload = $this->buildSnapshot($l->periode);

            $baru = LaporanDesa::create(array_merge($payload, [
                'nomor_laporan' => $this->nomor($l->periode, $nextVersion),
                'periode_id' => $l->periode_id,
                'status' => LaporanDesaStatus::Draft->value,
                'created_by' => $actor->id,
                'version' => $nextVersion,
                'previous_version_id' => $l->id,
                'generated_at' => now(),
            ]));
            $this->log($actor, $baru, 'revise');

            return $baru;
        });
    }

    /**
     * Catat pengiriman/penerimaan manual tanpa mengubah nominal (dokumen10 §Alur
     * snapshot langkah 6).
     *
     * @param  array{tanggal_dikirim?: ?string, tanggal_diterima?: ?string, bukti_penerimaan?: ?string}  $data
     */
    public function recordDelivery(LaporanDesa $laporan, User $actor, array $data): LaporanDesa
    {
        return DB::transaction(function () use ($laporan, $actor, $data) {
            $l = LaporanDesa::whereKey($laporan->id)->lockForUpdate()->firstOrFail();

            if (! in_array($l->status, self::FINAL, true)) {
                throw DomainException::invalidState('Pengiriman hanya untuk laporan yang sudah disetujui.');
            }

            $update = array_filter([
                'tanggal_dikirim' => $data['tanggal_dikirim'] ?? null,
                'tanggal_diterima' => $data['tanggal_diterima'] ?? null,
                'bukti_penerimaan' => $data['bukti_penerimaan'] ?? null,
            ], fn ($v) => $v !== null);

            if (isset($update['tanggal_diterima'])) {
                $update['status'] = LaporanDesaStatus::Diterima->value;
            } elseif (isset($update['tanggal_dikirim'])) {
                $update['status'] = LaporanDesaStatus::Dikirim->value;
            }

            $l->update($update);
            $this->log($actor, $l, 'delivery');

            return $l->fresh();
        });
    }

    /** @return array<string, mixed> kolom nilai + snapshot_data + hash + data_revision */
    private function buildSnapshot(PeriodeAkuntansi $periode): array
    {
        $from = $periode->tanggal_mulai->toDateString();
        $to = $periode->tanggal_selesai->toDateString();

        $omzet = $this->reports->omzetDesaBreakdown($from, $to);
        $dataRevision = $this->reports->dataRevision($from, $to);
        $transaksiIds = Transaksi::whereNotNull('posted_at')->where('status', 'posted')
            ->whereDate('tanggal', '>=', $from)->whereDate('tanggal', '<=', $to)
            ->orderBy('id')->pluck('id')->all();

        $snapshot = [
            'omzet' => $omzet,
            'laba_rugi' => $this->reports->labaRugi($from, $to),
            'neraca' => $this->reports->neraca($to, null, $from),
            'transaksi_ids' => $transaksiIds,
            'data_revision' => $dataRevision,
            'generated_at' => now()->toIso8601String(),
        ];

        return [
            'omzet_dagang' => $omzet['omzet_dagang'],
            'pendapatan_kredit' => $omzet['pendapatan_kredit'],
            'pendapatan_lain' => $omzet['pendapatan_lain'],
            'total_omzet' => $omzet['total_omzet'],
            'snapshot_data' => $snapshot,
            'data_revision' => $dataRevision,
            'snapshot_hash' => hash('sha256', json_encode($snapshot)),
        ];
    }

    private function unverifiedEvidence(PeriodeAkuntansi $periode): int
    {
        return BuktiTransaksi::query()
            ->join('transaksi', 'transaksi.id', '=', 'bukti_transaksi.transaksi_id')
            ->where('transaksi.periode_id', $periode->id)
            ->where('bukti_transaksi.status_verifikasi', '!=', 'valid')
            ->count();
    }

    private function nomor(PeriodeAkuntansi $periode, int $version): string
    {
        return 'LD-'.$periode->tahun.str_pad((string) $periode->bulan, 2, '0', STR_PAD_LEFT)."-v{$version}";
    }

    private function log(User $actor, LaporanDesa $laporan, string $aksi): void
    {
        LogAktivitas::create([
            'user_id' => $actor->id,
            'modul' => 'LaporanDesa',
            'aksi' => $aksi,
            'referensi_tipe' => 'laporan_desa',
            'referensi_id' => $laporan->id,
            'alasan' => "Laporan {$laporan->nomor_laporan} ({$aksi})",
        ]);
    }
}
