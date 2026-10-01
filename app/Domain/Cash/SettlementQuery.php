<?php

namespace App\Domain\Cash;

use App\Domain\Shared\Decimal;
use App\Models\AlokasiPelunasan;
use App\Models\SaldoAwalRincian;
use App\Models\Transaksi;

/**
 * Menghitung sisa (outstanding) invoice kredit atau item opening piutang/utang:
 * nilai − Σ alokasi yang pembayarannya sudah terposting. Alokasi dari transaksi
 * kas yang belum diposting (draft) tidak dihitung (konsisten filter posted).
 */
class SettlementQuery
{
    public function invoiceOutstanding(int $invoiceTransaksiId, ?int $excludeCashTrxId = null): string
    {
        $invoice = Transaksi::findOrFail($invoiceTransaksiId);
        $allocated = $this->sumAllocated('invoice_transaksi_id', $invoiceTransaksiId, $excludeCashTrxId);

        return Decimal::sub((string) $invoice->total, $allocated);
    }

    public function openingOutstanding(int $rincianId, ?int $excludeCashTrxId = null): string
    {
        $rincian = SaldoAwalRincian::findOrFail($rincianId);
        $allocated = $this->sumAllocated('opening_rincian_id', $rincianId, $excludeCashTrxId);

        return Decimal::sub((string) $rincian->nominal, $allocated);
    }

    private function sumAllocated(string $column, int $targetId, ?int $excludeCashTrxId): string
    {
        $sum = AlokasiPelunasan::where($column, $targetId)
            ->whereHas('pembayaranTransaksi', fn ($q) => $q->whereNotNull('posted_at')->where('status', 'posted'))
            ->when($excludeCashTrxId, fn ($q) => $q->where('pembayaran_transaksi_id', '!=', $excludeCashTrxId))
            ->sum('nominal');

        return Decimal::of((string) $sum);
    }
}
