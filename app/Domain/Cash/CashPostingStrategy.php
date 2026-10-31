<?php

namespace App\Domain\Cash;

use App\Domain\Accounting\Contracts\PostingStrategy;
use App\Domain\Accounting\JournalLine;
use App\Domain\Accounting\JournalPlan;
use App\Domain\Accounting\SystemAccounts;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Enums\SaldoRincianJenis;
use App\Models\AlokasiPelunasan;
use App\Models\Transaksi;
use Illuminate\Support\Collection;

/**
 * Posting kas (dokumen08 §2 & §6). Pemasukan: Dr Kas; Cr akun lawan. Pengeluaran:
 * Dr akun lawan; Cr Kas. Pelunasan hanya mengurangi piutang/utang (TIDAK mencatat
 * pendapatan lagi) dan wajib teralokasi ke invoice/opening tanpa lebih bayar.
 * Setoran pinjaman lama: Dr Kas; Cr Piutang Pedagang Pantai (Σ pokok) + Cr
 * Pendapatan Bunga (Σ bunga) + Cr Pendapatan Denda (Σ denda).
 */
class CashPostingStrategy implements PostingStrategy
{
    private const PELUNASAN = ['pelunasan_piutang', 'pelunasan_utang', 'setoran_pinjaman_lama'];

    /** Jenis rincian saldo awal yang boleh dilunasi oleh tiap kategori. */
    private const JENIS_OPENING = [
        'pelunasan_piutang' => SaldoRincianJenis::PiutangUsaha,
        'pelunasan_utang' => SaldoRincianJenis::UtangUsaha,
        'setoran_pinjaman_lama' => SaldoRincianJenis::Pinjaman,
    ];

    public function __construct(private SettlementQuery $settlement) {}

    public function plan(Transaksi $trx): JournalPlan
    {
        $sys = new SystemAccounts;
        $kas = $trx->akun_kas_id ?? $sys->id('cash');
        $total = (string) $trx->total;
        $kode = $trx->kategori_kode;
        $arus = $this->arusKas($kode);

        if ($kode === 'setoran_pinjaman_lama') {
            $alokasi = $this->assertNoOverpayment($trx);
            $pokok = $alokasi->reduce(fn ($s, $a) => Decimal::add($s, (string) $a->nominal), '0');
            $bunga = $alokasi->reduce(fn ($s, $a) => Decimal::add($s, (string) $a->bunga), '0');
            $denda = $alokasi->reduce(fn ($s, $a) => Decimal::add($s, (string) $a->denda), '0');

            $lines = [new JournalLine($kas, debit: $total, keterangan: 'Setoran pinjaman pedagang lama', kelompokArusKas: $arus)];
            if (Decimal::isPositive($pokok)) {
                $lines[] = new JournalLine($sys->id('loan_receivable'), kredit: $pokok, keterangan: 'Pokok pinjaman pedagang lama');
            }
            if (Decimal::isPositive($bunga)) {
                $lines[] = new JournalLine($sys->id('interest_income'), kredit: $bunga, keterangan: 'Bunga pinjaman pedagang lama');
            }
            if (Decimal::isPositive($denda)) {
                $lines[] = new JournalLine($sys->id('penalty_income'), kredit: $denda, keterangan: 'Denda pinjaman pedagang lama');
            }

            return new JournalPlan(...$lines);
        }

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

        $label = config("accounting.categories.{$kode}.label");

        return $trx->jenis_transaksi->value === 'pemasukan'
            ? new JournalPlan(
                new JournalLine($kas, debit: $total, keterangan: $label ?? 'Pemasukan', kelompokArusKas: $arus),
                new JournalLine($trx->akun_lawan_id, kredit: $total, keterangan: $trx->keterangan ?? $label ?? 'Pemasukan'),
            )
            : new JournalPlan(
                new JournalLine($trx->akun_lawan_id, debit: $total, keterangan: $trx->keterangan ?? $label ?? 'Pengeluaran'),
                new JournalLine($kas, kredit: $total, keterangan: $label ?? 'Pengeluaran', kelompokArusKas: $arus),
            );
    }

    /**
     * Kelompok arus kas per kategori (dokumen10 §4) dari config
     * accounting.categories.*.arus (default operasi). null = pemindahan antar
     * kas/bank: kedua sisi tanpa kelompok sehingga tidak dihitung arus kas.
     */
    private function arusKas(string $kode): ?string
    {
        $kategori = config("accounting.categories.{$kode}", []);

        return array_key_exists('arus', $kategori) ? $kategori['arus'] : 'operasi';
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

    /** @return Collection<int, AlokasiPelunasan> */
    private function assertNoOverpayment(Transaksi $trx): Collection
    {
        $alokasi = AlokasiPelunasan::with('openingRincian.saldoAwalAkun')->where('pembayaran_transaksi_id', $trx->id)->get();

        if ($alokasi->isEmpty()) {
            throw DomainException::invalidState('Pelunasan tanpa alokasi.');
        }

        // Jumlah alokasi (pokok + bunga + denda) harus sama dengan total kas.
        $sum = $alokasi->reduce(fn ($s, $a) => Decimal::add(
            Decimal::add($s, (string) $a->nominal),
            Decimal::add((string) $a->bunga, (string) $a->denda),
        ), '0');
        if (Decimal::compare($sum, (string) $trx->total) !== 0) {
            throw DomainException::invalidState('Jumlah alokasi tidak sama dengan total kas.');
        }

        $jenisOpening = self::JENIS_OPENING[$trx->kategori_kode];

        // Bunga/denda hanya untuk setoran pinjaman lama; pelunasan nota wajib berpokok.
        if ($trx->kategori_kode !== 'setoran_pinjaman_lama'
            && $alokasi->contains(fn ($a) => ! Decimal::isPositive((string) $a->nominal)
                || Decimal::isPositive((string) $a->bunga) || Decimal::isPositive((string) $a->denda))) {
            throw DomainException::invalidState('Alokasi pelunasan nota harus berupa pokok > 0 tanpa bunga/denda.');
        }

        // Per target: alokasi ≤ outstanding.
        foreach ($alokasi->groupBy('target_key') as $group) {
            $groupSum = $group->reduce(fn ($s, $a) => Decimal::add($s, (string) $a->nominal), '0');
            $first = $group->first();

            if ($first->invoice_transaksi_id && $trx->kategori_kode === 'setoran_pinjaman_lama') {
                throw DomainException::invalidState('Setoran pinjaman lama hanya untuk pinjaman dari saldo awal.');
            }

            // Item saldo awal: jenis harus sesuai kategori dan cutover sudah dikunci.
            if ($first->opening_rincian_id) {
                $rincian = $first->openingRincian;
                if ($rincian->jenis !== $jenisOpening) {
                    throw DomainException::invalidState("Rincian saldo awal {$rincian->kode_referensi} bukan {$jenisOpening->value}.");
                }
                if ($rincian->saldoAwalAkun->locked_at === null) {
                    throw DomainException::openingNotLocked('Saldo awal belum dikunci; rinciannya belum bisa dilunasi.');
                }
            }

            $outstanding = $first->invoice_transaksi_id
                ? $this->settlement->invoiceOutstanding($first->invoice_transaksi_id, $trx->id)
                : $this->settlement->openingOutstanding($first->opening_rincian_id, $trx->id);

            if (Decimal::compare($groupSum, $outstanding) > 0) {
                throw DomainException::overpayment(
                    "Alokasi {$groupSum} melebihi sisa {$outstanding} pada target {$first->target_key}."
                );
            }
        }

        return $alokasi;
    }
}
