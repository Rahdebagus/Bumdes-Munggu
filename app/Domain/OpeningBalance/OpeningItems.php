<?php

namespace App\Domain\OpeningBalance;

use App\Domain\Shared\Decimal;
use App\Enums\SaldoRincianJenis;
use App\Models\AlokasiPelunasan;
use App\Models\SaldoAwalRincian;
use Illuminate\Support\Collection;

/**
 * Item rincian saldo awal yang masih bisa ditagih/dibayar — nota piutang usaha,
 * nota utang usaha, dan pinjaman pedagang lama dari cutover — beserta sisanya:
 * nominal − Σ alokasi pelunasan yang pembayarannya terposting (s/d as_of).
 * Hanya rincian dari saldo awal yang sudah dikunci.
 */
class OpeningItems
{
    /**
     * @return Collection<int, array{rincian: SaldoAwalRincian, dibayar: string, sisa: string}>
     */
    public function outstanding(
        SaldoRincianJenis $jenis,
        ?string $asOf = null,
        ?int $pihakId = null,
        ?int $unitId = null,
        bool $termasukLunas = false,
    ): Collection {
        $items = SaldoAwalRincian::with('pihak')
            ->where('jenis', $jenis->value)
            ->whereHas('saldoAwalAkun', fn ($q) => $q->whereNotNull('locked_at')
                ->when($unitId, fn ($w) => $w->where('unit_usaha_id', $unitId)))
            ->when($pihakId, fn ($q) => $q->where('pihak_id', $pihakId))
            ->orderBy('tanggal_asal')->orderBy('id')
            ->get();

        $dibayar = AlokasiPelunasan::query()
            ->whereIn('opening_rincian_id', $items->pluck('id'))
            ->whereHas('pembayaranTransaksi', fn ($q) => $q->whereNotNull('posted_at')->where('status', 'posted')
                ->when($asOf, fn ($w) => $w->whereDate('tanggal', '<=', $asOf)))
            ->groupBy('opening_rincian_id')
            ->selectRaw('opening_rincian_id, SUM(nominal) as total')
            ->pluck('total', 'opening_rincian_id');

        return $items
            ->map(function (SaldoAwalRincian $r) use ($dibayar) {
                $bayar = Decimal::of((string) ($dibayar[$r->id] ?? '0'));

                return ['rincian' => $r, 'dibayar' => $bayar, 'sisa' => Decimal::sub((string) $r->nominal, $bayar)];
            })
            ->when(! $termasukLunas, fn ($c) => $c->filter(fn ($row) => Decimal::isPositive($row['sisa'])))
            ->values();
    }

    public function totalSisa(SaldoRincianJenis $jenis, ?string $asOf = null, ?int $unitId = null): string
    {
        return $this->outstanding($jenis, $asOf, unitId: $unitId)
            ->reduce(fn ($total, $row) => Decimal::add($total, $row['sisa']), '0');
    }
}
