<?php

namespace Tests\Feature\Lending;

use App\Domain\Accounting\PostingService;
use App\Domain\Accounting\ReverseTransaction;
use App\Domain\Lending\DisburseLoan;
use App\Domain\Lending\SaveInstallmentPayment;
use App\Domain\Lending\SaveLoanDraft;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Enums\PinjamanStatus;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Pihak;
use App\Models\Pinjaman;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LendingTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private UnitUsaha $unit;

    private Pihak $peminjam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->actor = User::factory()->create();
        PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->unit = UnitUsaha::where('kode_unit', 'UNIT-KREDIT')->firstOrFail();
        $this->peminjam = Pihak::factory()->peminjam()->create();
    }

    private function cash(): int
    {
        return (int) Akun::where('system_key', 'cash')->value('id');
    }

    private function debit(Transaksi $trx, string $key): string
    {
        return Decimal::of((string) TransaksiDetail::where('transaksi_id', $trx->id)->where('akun_id', (int) Akun::where('system_key', $key)->value('id'))->sum('debit'));
    }

    private function kredit(Transaksi $trx, string $key): string
    {
        return Decimal::of((string) TransaksiDetail::where('transaksi_id', $trx->id)->where('akun_id', (int) Akun::where('system_key', $key)->value('id'))->sum('kredit'));
    }

    private function newLoan(string $pokok = '1000000', string $rate = '10', int $tenor = 3): Pinjaman
    {
        return app(SaveLoanDraft::class)->handle([
            'tanggal_pinjaman' => '2026-01-10',
            'unit_usaha_id' => $this->unit->id,
            'peminjam_id' => $this->peminjam->id,
            'pokok_pinjaman' => $pokok,
            'persentase_bunga' => $rate,
            'tenor' => $tenor,
            'basis_bunga' => 'flat_total',
            'tanggal_angsuran_pertama' => '2026-02-01',
        ], $this->actor);
    }

    private function disburse(Pinjaman $loan): Transaksi
    {
        return app(DisburseLoan::class)->handle($loan, '2026-01-15', $this->cash(), $this->actor, 'disb-'.$loan->id, 'h');
    }

    private function pay(array $allocations, string $tanggal = '2026-01-20'): Transaksi
    {
        $draft = app(SaveInstallmentPayment::class)->handle([
            'tanggal' => $tanggal, 'akun_kas_id' => $this->cash(), 'allocations' => $allocations,
        ], $this->actor);

        return app(PostingService::class)->post($draft, 1, $this->actor, 'pay-'.$draft->id, 'h');
    }

    public function test_disbursement_posts_receivable_and_activates_loan(): void
    {
        $loan = $this->newLoan();
        $this->assertSame('100000.00', $loan->total_bunga);

        $pencairan = $this->disburse($loan);

        $this->assertSame(PinjamanStatus::Aktif, $loan->fresh()->status);
        $this->assertSame('1000000.00', $this->debit($pencairan, 'loan_receivable'));
        $this->assertSame('1000000.00', $this->kredit($pencairan, 'cash'));
        $this->assertSame('1000000.00', $loan->fresh()->sisa_pokok);
    }

    public function test_partial_payment_splits_and_updates_cache(): void
    {
        $loan = $this->newLoan();
        $this->disburse($loan);
        $angsuran1 = $loan->angsuran()->where('angsuran_ke', 1)->firstOrFail();

        $pay = $this->pay([['angsuran_id' => $angsuran1->id, 'pokok' => '100000', 'bunga' => '10000', 'denda' => '0']]);

        $this->assertSame('110000.00', $this->debit($pay, 'cash'));
        $this->assertSame('100000.00', $this->kredit($pay, 'loan_receivable'));
        $this->assertSame('10000.00', $this->kredit($pay, 'interest_income'));

        $angsuran1->refresh();
        $this->assertSame('100000.00', $angsuran1->pokok_dibayar);
        $this->assertSame('10000.00', $angsuran1->bunga_dibayar);
        $this->assertSame('sebagian', $angsuran1->status->value);
        $this->assertSame('900000.00', $loan->fresh()->sisa_pokok);

        // Pembayaran kedua untuk jadwal yang sama = row baru (histori).
        $this->pay([['angsuran_id' => $angsuran1->id, 'pokok' => '100000', 'bunga' => '0', 'denda' => '0']], '2026-01-21');
        $this->assertSame('200000.00', $angsuran1->fresh()->pokok_dibayar);
        $this->assertSame('800000.00', $loan->fresh()->sisa_pokok);
    }

    public function test_reverse_payment_restores_outstanding(): void
    {
        $loan = $this->newLoan();
        $this->disburse($loan);
        $angsuran1 = $loan->angsuran()->where('angsuran_ke', 1)->firstOrFail();

        $pay = $this->pay([['angsuran_id' => $angsuran1->id, 'pokok' => '100000', 'bunga' => '10000', 'denda' => '0']]);
        $this->assertSame('900000.00', $loan->fresh()->sisa_pokok);

        app(ReverseTransaction::class)->reverse($pay, $pay->tanggal->toDateString(), 'koreksi', $this->actor, 'rev-'.$pay->id, 'h');

        $this->assertSame('0.00', $angsuran1->fresh()->pokok_dibayar);
        $this->assertSame('1000000.00', $loan->fresh()->sisa_pokok);
        $this->assertSame('belum_dibayar', $angsuran1->fresh()->status->value);
    }

    public function test_overpayment_rejected(): void
    {
        $loan = $this->newLoan();
        $this->disburse($loan);
        $angsuran1 = $loan->angsuran()->where('angsuran_ke', 1)->firstOrFail();

        try {
            $this->pay([['angsuran_id' => $angsuran1->id, 'pokok' => '400000', 'bunga' => '0', 'denda' => '0']]);
            $this->fail('Seharusnya OVERPAYMENT');
        } catch (DomainException $e) {
            $this->assertSame('OVERPAYMENT', $e->domainCode);
        }
    }

    public function test_cross_loan_payment_rejected(): void
    {
        $loanA = $this->newLoan();
        $this->disburse($loanA);
        $loanB = $this->newLoan();
        $this->disburse($loanB);

        $a1 = $loanA->angsuran()->where('angsuran_ke', 1)->firstOrFail();
        $b1 = $loanB->angsuran()->where('angsuran_ke', 1)->firstOrFail();

        $this->expectException(DomainException::class);
        app(SaveInstallmentPayment::class)->handle([
            'tanggal' => '2026-01-20', 'akun_kas_id' => $this->cash(),
            'allocations' => [
                ['angsuran_id' => $a1->id, 'pokok' => '10000', 'bunga' => '0', 'denda' => '0'],
                ['angsuran_id' => $b1->id, 'pokok' => '10000', 'bunga' => '0', 'denda' => '0'],
            ],
        ], $this->actor);
    }

    public function test_reverse_disbursement_blocked_when_installment_paid(): void
    {
        $loan = $this->newLoan();
        $pencairan = $this->disburse($loan);
        $angsuran1 = $loan->angsuran()->where('angsuran_ke', 1)->firstOrFail();
        $this->pay([['angsuran_id' => $angsuran1->id, 'pokok' => '100000', 'bunga' => '10000', 'denda' => '0']]);

        try {
            app(ReverseTransaction::class)->reverse($pencairan, $pencairan->tanggal->toDateString(), 'batal', $this->actor, 'rev-pc', 'h');
            $this->fail('Seharusnya REVERSE_DEPENDENCY');
        } catch (DomainException $e) {
            $this->assertSame('REVERSE_DEPENDENCY', $e->domainCode);
        }
    }
}
