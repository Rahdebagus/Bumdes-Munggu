<?php

namespace App\Domain\Cash;

use App\Domain\Accounting\Contracts\PostingStrategy;
use App\Domain\Accounting\JournalLine;
use App\Domain\Accounting\JournalPlan;
use App\Domain\Accounting\SystemAccounts;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Models\AlokasiPelunasan;
use App\Models\Transaksi;

/**
 * Posting kas (dokumen08 §2 & §6). Pemasukan: Dr Kas; Cr akun lawan. Pengeluaran:
 * Dr akun lawan; Cr Kas. Pelunasan hanya mengurangi piutang/utang (TIDAK mencatat
 * pendapatan lagi) dan wajib teralokasi ke invoice/opening tanpa lebih bayar.
 */
class CashPostingStrategy implements PostingStrategy
{
    private const PELUNASAN = ['pelunasan_piutang', 'pelunasan_utang'];

    public function __construct(private SettlementQuery $settlement) {}

    public function plan(Transaksi $trx): JournalPlan
    {
        $sys = new SystemAccounts;
        $kas = $trx->akun_kas_id ?? $sys->id('cash');
        $total = (string) $trx->total;
        $kode = $trx->kategori_kode;
        $arus = $this->arusKas($kode);

        if (in_array($kode, self::PELUNASAN, true)) {
            $this->assertNoOverpayment($trx);

            return $kode === 'pelunasan_piutang'
                ? new JournalPlan(
                    new JournalLine($kas, debit: $total, keterangan: 'Pelunasan piutang', kelompokArusKas: $arus),
                    new JournalLine($sys->id('trade_receivable'), kredit: $total, keterangan: 'Piutang usaha'),
                )
                : new JournalPlan(
                    new JournalLine($sys->id('trade_payable'), debit: $total, keterangan: 'Utang usaha'),
                    new JournalLine($kas, kredit: $total, keterangan: 'Pembayaran utang', kelompokArusKas: $arus),
                );
        }

        if (! $trx->akun_lawan_id) {
            throw DomainException::invalidState('Akun lawan wajib untuk transaksi kas non-pelunasan.');
        }

        return $trx->jenis_transaksi->value === 'pemasukan'
            ? new JournalPlan(
                new JournalLine($kas, debit: $total, keterangan: 'Pemasukan', kelompokArusKas: $arus),
                new JournalLine($trx->akun_lawan_id, kredit: $total, keterangan: $trx->keterangan ?? 'Pemasukan'),
            )
            : new JournalPlan(
                new JournalLine($trx->akun_lawan_id, debit: $total, keterangan: $trx->keterangan ?? 'Pengeluaran'),
                new JournalLine($kas, kredit: $total, keterangan: 'Pengeluaran', kelompokArusKas: $arus),
            );
    }

    /** Kelompok arus kas per kategori (dokumen10 §4). */
    private function arusKas(string $kode): string
    {
        return match ($kode) {
            'modal_masuk', 'modal_keluar' => 'pendanaan',
            'perolehan_aset' => 'investasi',
            default => 'operasi',
        };
    }

    public function documentTotal(Transaksi $trx): string
    {
        return (string) $trx->total;
    }

    public function applyEffects(Transaksi $trx): void
    {
        // Alokasi pelunasan sudah tertulis saat draft; menjadi efektif ketika
        // transaksi kas ini terposting (difilter posted_at pada outstanding).
        // Saat dibalik, status original→reversed membuat SettlementQuery tidak
        // lagi menghitung alokasinya sehingga outstanding pulih otomatis.
    }

    private function assertNoOverpayment(Transaksi $trx): void
    {
        $alokasi = AlokasiPelunasan::where('pembayaran_transaksi_id', $trx->id)->get();

        if ($alokasi->isEmpty()) {
            throw DomainException::invalidState('Pelunasan tanpa alokasi.');
        }

        // Jumlah alokasi harus sama dengan total kas.
        $sum = $alokasi->reduce(fn ($s, $a) => Decimal::add($s, (string) $a->nominal), '0');
        if (Decimal::compare($sum, (string) $trx->total) !== 0) {
            throw DomainException::invalidState('Jumlah alokasi tidak sama dengan total kas.');
        }

        // Per target: alokasi ≤ outstanding.
        foreach ($alokasi->groupBy('target_key') as $group) {
            $groupSum = $group->reduce(fn ($s, $a) => Decimal::add($s, (string) $a->nominal), '0');
            $first = $group->first();

            $outstanding = $first->invoice_transaksi_id
                ? $this->settlement->invoiceOutstanding($first->invoice_transaksi_id, $trx->id)
                : $this->settlement->openingOutstanding($first->opening_rincian_id, $trx->id);

            if (Decimal::compare($groupSum, $outstanding) > 0) {
                throw DomainException::overpayment(
                    "Alokasi {$groupSum} melebihi sisa {$outstanding} pada target {$first->target_key}."
                );
            }
        }
    }
}
