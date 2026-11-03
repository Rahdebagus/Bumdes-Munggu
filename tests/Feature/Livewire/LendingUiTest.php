<?php

namespace Tests\Feature\Livewire;

use App\Domain\Lending\DisburseLoan;
use App\Domain\Lending\SaveInstallmentPayment;
use App\Domain\Lending\SaveLoanDraft;
use App\Livewire\Lending\Loans\Create;
use App\Livewire\Lending\Loans\Show;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Pihak;
use App\Models\Pinjaman;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LendingUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private UnitUsaha $unit;

    private Pihak $peminjam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->admin = User::factory()->create();
        PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->unit = UnitUsaha::where('kode_unit', 'UNIT-KREDIT')->firstOrFail();
        $this->peminjam = Pihak::factory()->peminjam()->create();
    }

    private function fillLoanForm($test)
    {
        return $test
            ->set('unit_usaha_id', $this->unit->id)
            ->set('peminjam_id', $this->peminjam->id)
            ->set('tanggal_pinjaman', '2026-01-10')
            ->set('pokok_pinjaman', '1000000')
            ->set('persentase_bunga', '10')
            ->set('tenor', 3)
            ->set('basis_bunga', 'flat_total')
            ->set('tanggal_angsuran_pertama', '2026-02-01');
    }

    public function test_admin_creates_loan_draft(): void
    {
        $this->fillLoanForm(Livewire::actingAs($this->admin)->test(Create::class))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('pinjaman', ['peminjam_id' => $this->peminjam->id, 'status' => 'draft', 'total_bunga' => 100000]);
        $this->assertSame(3, Pinjaman::first()->angsuran()->count());
    }

    public function test_pimpinan_cannot_create_loan(): void
    {
        $pimpinan = User::factory()->pimpinan()->create();

        $this->fillLoanForm(Livewire::actingAs($pimpinan)->test(Create::class))
            ->call('save')
            ->assertForbidden();
    }

    public function test_pimpinan_cannot_disburse(): void
    {
        $pimpinan = User::factory()->pimpinan()->create();
        $loan = app(SaveLoanDraft::class)->handle([
            'tanggal_pinjaman' => '2026-01-10', 'unit_usaha_id' => $this->unit->id, 'peminjam_id' => $this->peminjam->id,
            'pokok_pinjaman' => '1000000', 'persentase_bunga' => '10', 'tenor' => 3, 'basis_bunga' => 'flat_total',
            'tanggal_angsuran_pertama' => '2026-02-01',
        ], $this->admin);

        Livewire::actingAs($pimpinan)->test(Show::class, ['pinjaman' => $loan])
            ->set('disburseTanggal', '2026-01-15')
            ->set('akunKasId', (int) Akun::where('system_key', 'cash')->value('id'))
            ->call('disburse')
            ->assertForbidden();

        $this->assertSame('draft', $loan->fresh()->status->value);
    }

    private function activeLoan(): Pinjaman
    {
        $loan = app(SaveLoanDraft::class)->handle([
            'tanggal_pinjaman' => '2026-01-10', 'unit_usaha_id' => $this->unit->id, 'peminjam_id' => $this->peminjam->id,
            'pokok_pinjaman' => '10000000', 'persentase_bunga' => '5', 'tenor' => 2, 'basis_bunga' => 'flat_total',
            'tanggal_angsuran_pertama' => '2026-02-01',
        ], $this->admin);

        app(DisburseLoan::class)->handle($loan, '2026-01-15', (int) Akun::where('system_key', 'cash')->value('id'), $this->admin, 'disb-'.$loan->id, 'h');

        return $loan->refresh();
    }

    public function test_selecting_installment_prefills_amounts_and_allows_partial_payment_edits(): void
    {
        $loan = $this->activeLoan();
        $installments = $loan->angsuran()->orderBy('angsuran_ke')->get();

        Livewire::actingAs($this->admin)->test(Show::class, ['pinjaman' => $loan])
            ->assertSeeHtml('wire:model.live="payAngsuranId"')
            ->set('payAngsuranId', $installments[0]->id)
            ->assertSet('payPokok', '5000000.00')
            ->assertSet('payBunga', '250000.00')
            ->assertSet('payDenda', '0.00')
            ->set('payPokok', '1000000')
            ->set('payBunga', '50000')
            ->set('payTanggal', '2026-01-20')
            ->set('akunKasId', (int) Akun::where('system_key', 'cash')->value('id'))
            ->assertSet('payPokok', '1000000')
            ->assertSet('payBunga', '50000')
            ->set('payAngsuranId', $installments[1]->id)
            ->assertSet('payPokok', '5000000.00')
            ->assertSet('payBunga', '250000.00')
            ->assertSet('payTanggal', '2026-01-20')
            ->set('payAngsuranId', '')
            ->assertSet('payAngsuranId', null)
            ->assertSet('payPokok', '0')
            ->assertSet('payBunga', '0')
            ->assertSet('payDenda', '0')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('pembayaran_angsuran', 0);
        $this->assertDatabaseCount('transaksi', 1);
    }

    public function test_prefill_uses_unpaid_principal_interest_and_penalty_after_partial_payment(): void
    {
        $loan = $this->activeLoan();
        $installment = $loan->angsuran()->where('angsuran_ke', 1)->firstOrFail();
        $installment->update(['denda_tagihan' => '20000.50']);
        $cashId = (int) Akun::where('system_key', 'cash')->value('id');

        $form = Livewire::actingAs($this->admin)->test(Show::class, ['pinjaman' => $loan])
            ->set('payAngsuranId', $installment->id)
            ->assertSet('payDenda', '20000.50')
            ->set('payPokok', '1000000.25')
            ->set('payBunga', '50000.50')
            ->set('payDenda', '5000.25')
            ->set('payTanggal', '2026-01-20')
            ->set('akunKasId', $cashId)
            ->call('pay')
            ->assertHasNoErrors()
            ->assertSet('payAngsuranId', null);

        $this->assertSame('sebagian', $installment->fresh()->status->value);

        // Draft payments do not reduce the remaining bill until posted.
        app(SaveInstallmentPayment::class)->handle([
            'tanggal' => '2026-01-20', 'akun_kas_id' => $cashId,
            'allocations' => [['angsuran_id' => $installment->id, 'pokok' => '100000', 'bunga' => '10000', 'denda' => '1000']],
        ], $this->admin);

        $form->set('payAngsuranId', $installment->id)
            ->assertSet('payPokok', '3999999.75')
            ->assertSet('payBunga', '199999.50')
            ->assertSet('payDenda', '15000.25')
            ->call('pay')
            ->assertHasNoErrors()
            ->set('payAngsuranId', $installment->id)
            ->assertSet('payPokok', '0.00')
            ->assertSet('payBunga', '0.00')
            ->assertSet('payDenda', '0.00');

        $this->assertSame('lunas', $installment->fresh()->status->value);
        $this->assertSame('5000000.00', $loan->fresh()->sisa_pokok);
    }

    public function test_installment_from_another_loan_cannot_prefill_or_be_paid(): void
    {
        $loan = $this->activeLoan();
        $otherLoan = $this->activeLoan();
        $installment = $loan->angsuran()->firstOrFail();
        $otherInstallment = $otherLoan->angsuran()->firstOrFail();

        Livewire::actingAs($this->admin)->test(Show::class, ['pinjaman' => $loan])
            ->set('payAngsuranId', $installment->id)
            ->set('payAngsuranId', $otherInstallment->id)
            ->assertSet('payPokok', '0')
            ->assertSet('payBunga', '0')
            ->assertSet('payDenda', '0')
            ->assertHasErrors(['payAngsuranId'])
            ->set('payPokok', '100000')
            ->set('payTanggal', '2026-01-20')
            ->set('akunKasId', (int) Akun::where('system_key', 'cash')->value('id'))
            ->call('pay')
            ->assertHasErrors(['payAngsuranId' => 'exists'])
            ->set('payAngsuranId', null)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('pembayaran_angsuran', 0);
        $this->assertDatabaseCount('transaksi', 2);
    }
}
