<?php

namespace App\Domain\Inventory;

use App\Domain\Accounting\PostingService;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Enums\StokOpnameStatus;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\Produk;
use App\Models\StokOpname;
use App\Models\StokOpnameDetail;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alur stok opname: draft → selesai → menunggu_persetujuan → disetujui → posted;
 * revisi mengembalikan ke draft & menghapus approval (dokumen04/08/09). Snapshot
 * stock_version saat hitung untuk stale detection ketika posting.
 */
class StocktakeService
{
    public function __construct(private PostingService $posting) {}

    /**
     * @param  array{tanggal: string, periode_id: int, unit_usaha_id: int, items: array<int, array{produk_id: int, stok_fisik: string}>}  $data
     */
    public function start(array $data, User $actor): StokOpname
    {
        return DB::transaction(function () use ($data, $actor) {
            $opname = StokOpname::create([
                'nomor_opname' => 'OP-'.strtoupper((string) Str::ulid()),
                'tanggal' => $data['tanggal'],
                'periode_id' => $data['periode_id'],
                'unit_usaha_id' => $data['unit_usaha_id'],
                'status' => StokOpnameStatus::Draft->value,
                'nilai_selisih' => 0,
                'created_by' => $actor->id,
                'counted_at' => now(),
                'revision' => 1,
            ]);

            $totalSelisih = '0';
            foreach ($data['items'] as $it) {
                $produk = Produk::findOrFail($it['produk_id']);
                $sistem = (string) $produk->stok_terkini;
                $avg = (string) $produk->harga_pokok_rata;
                $selisih = Decimal::sub($it['stok_fisik'], $sistem, Decimal::QTY);
                $nilaiSelisih = Decimal::mul($selisih, $avg);

                StokOpnameDetail::create([
                    'stok_opname_id' => $opname->id,
                    'produk_id' => $produk->id,
                    'stok_sistem' => $sistem,
                    'stok_fisik' => $it['stok_fisik'],
                    'selisih' => $selisih,
                    'harga_pokok' => $avg,
                    'nilai_selisih' => $nilaiSelisih,
                    'stock_version_snapshot' => $produk->stock_version,
                ]);

                $totalSelisih = Decimal::add($totalSelisih, $nilaiSelisih);
            }

            $opname->update(['nilai_selisih' => $totalSelisih]);

            return $opname;
        });
    }

    public function complete(StokOpname $o): void
    {
        $this->transition($o, StokOpnameStatus::Draft, StokOpnameStatus::Selesai);
    }

    public function submit(StokOpname $o): void
    {
        $this->transition($o, StokOpnameStatus::Selesai, StokOpnameStatus::MenungguPersetujuan, ['submitted_at' => now()]);
    }

    public function approve(StokOpname $o, User $pimpinan): void
    {
        $this->transition($o, StokOpnameStatus::MenungguPersetujuan, StokOpnameStatus::Disetujui, [
            'approved_by' => $pimpinan->id,
            'approved_at' => now(),
        ]);
    }

    public function requestRevision(StokOpname $o): void
    {
        if (! in_array($o->status, [StokOpnameStatus::MenungguPersetujuan, StokOpnameStatus::Disetujui], true)) {
            throw DomainException::invalidState('Hanya opname menunggu/ disetujui yang dapat diminta revisi.');
        }

        $o->update([
            'status' => StokOpnameStatus::Draft->value,
            'approved_by' => null,
            'approved_at' => null,
            'revision' => $o->revision + 1,
        ]);
    }

    public function post(StokOpname $o, User $actor, string $requestKey, string $requestHash): Transaksi
    {
        if ($o->status !== StokOpnameStatus::Disetujui) {
            throw DomainException::invalidState('Opname harus disetujui sebelum diposting.');
        }

        return DB::transaction(function () use ($o, $actor, $requestKey, $requestHash) {
            $trx = Transaksi::create([
                'nomor_transaksi' => 'OPN-'.strtoupper((string) Str::ulid()),
                'tanggal' => $o->tanggal->toDateString(),
                'periode_id' => $o->periode_id,
                'unit_usaha_id' => $o->unit_usaha_id,
                'user_id' => $actor->id,
                'jenis_transaksi' => TransaksiJenis::Penyesuaian->value,
                'status' => TransaksiStatus::Draft->value,
                'sumber_tipe' => 'stok_opname',
                'sumber_id' => $o->id,
                'total' => 0,
                'request_key' => (string) Str::uuid(),
                'version' => 1,
                'keterangan' => 'Stok opname '.$o->nomor_opname,
            ]);

            $posted = $this->posting->post($trx, 1, $actor, $requestKey, $requestHash);

            $o->update([
                'status' => StokOpnameStatus::Posted->value,
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            return $posted;
        });
    }

    private function transition(StokOpname $o, StokOpnameStatus $from, StokOpnameStatus $to, array $extra = []): void
    {
        if ($o->status !== $from) {
            throw DomainException::invalidState("Transisi opname tidak valid dari {$o->status->value} ke {$to->value}.");
        }

        $o->update(array_merge(['status' => $to->value], $extra));
    }
}
