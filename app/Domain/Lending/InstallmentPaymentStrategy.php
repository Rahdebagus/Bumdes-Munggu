<?php

namespace App\Domain\Lending;

use App\Domain\Accounting\Contracts\PostingStrategy;
use App\Domain\Accounting\JournalLine;
use App\Domain\Accounting\JournalPlan;
use App\Domain\Accounting\SystemAccounts;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Models\PembayaranAngsuran;
use App\Models\Transaksi;

/**
 * Posting pembayaran angsuran (dokumen08 §2 & §5). Dr Kas total; Cr Piutang
 * pinjaman (pokok); Cr Pendapatan bunga; Cr Pendapatan denda. Tolak overpayment
 * per komponen. Cache pinjaman/angsuran disegarkan dari histori.
 */
class InstallmentPaymentStrategy implements PostingStrategy
{
    public function __construct(
        private LoanBalanceQuery $balance,
        private LoanCacheUpdater $cache,
    ) {}

    public function plan(Transaksi $trx): JournalPlan
    {
        $rows = PembayaranAngsuran::with('angsuran')->where('transaksi_id', $trx->id)->get();

        if ($rows->isEmpty()) {
            throw DomainException::invalidState('Pembayaran tanpa alokasi.');
        }
        if ($rows->map(fn ($r) => $r->angsuran->pinjaman_id)->unique()->count() !== 1) {
            throw DomainException::invalidState('Alokasi pembayaran lintas pinjaman.');
        }

        $pokok = '0';
        $bunga = '0';
        $denda = '0';

        foreach ($rows as $r) {
            $paid = $this->balance->paid($r->angsuran); // tidak termasuk trx ini (masih draft)
            $this->assertNotOver($paid['pokok'], (string) $r->pokok, (string) $r->angsuran->pokok_tagihan, 'pokok', $r->angsuran->angsuran_ke);
            $this->assertNotOver($paid['bunga'], (string) $r->bunga, (string) $r->angsuran->bunga_tagihan, 'bunga', $r->angsuran->angsuran_ke);
            $this->assertNotOver($paid['denda'], (string) $r->denda, (string) $r->angsuran->denda_tagihan, 'denda', $r->angsuran->angsuran_ke);

            $pokok = Decimal::add($pokok, (string) $r->pokok);
            $bunga = Decimal::add($bunga, (string) $r->bunga);
            $denda = Decimal::add($denda, (string) $r->denda);
        }

        $sys = new SystemAccounts;
        $kas = $trx->akun_kas_id ?? $sys->id('cash');
        $total = Decimal::add(Decimal::add($pokok, $bunga), $denda);

        $lines = [new JournalLine($kas, debit: $total, keterangan: 'Pembayaran angsuran', kelompokArusKas: 'operasi')];
        if (Decimal::isPositive($pokok)) {
            $lines[] = new JournalLine($sys->id('loan_receivable'), kredit: $pokok, keterangan: 'Angsuran pokok');
        }
        if (Decimal::isPositive($bunga)) {
            $lines[] = new JournalLine($sys->id('interest_income'), kredit: $bunga, keterangan: 'Pendapatan bunga');
        }
        if (Decimal::isPositive($denda)) {
            $lines[] = new JournalLine($sys->id('penalty_income'), kredit: $denda, keterangan: 'Pendapatan denda');
        }

        return new JournalPlan(...$lines);
    }

    public function documentTotal(Transaksi $trx): string
    {
        return (string) $trx->total;
    }

    public function applyEffects(Transaksi $trx): void
    {
        $pinjaman = PembayaranAngsuran::with('angsuran.pinjaman')
            ->where('transaksi_id', $trx->id)->first()?->angsuran?->pinjaman;

        if ($pinjaman) {
            $this->cache->refresh($pinjaman);
        }
    }

    private function assertNotOver(string $sudah, string $tambah, string $tagihan, string $komponen, int $ke): void
    {
        if (Decimal::compare(Decimal::add($sudah, $tambah), $tagihan) > 0) {
            throw DomainException::overpayment("Pembayaran {$komponen} angsuran ke-{$ke} melebihi tagihan.");
        }
    }
}
