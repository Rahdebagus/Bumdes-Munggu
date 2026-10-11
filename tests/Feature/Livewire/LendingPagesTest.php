<?php

namespace Tests\Feature\Livewire;

use App\Domain\Lending\DisburseLoan;
use App\Domain\Lending\SaveLoanDraft;
use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\PreviewImport;
use App\Livewire\Lending\Borrowers;
use App\Livewire\Lending\Payments;
use App\Livewire\Lending\Schedules;
use App\Models\Akun;
use App\Models\Angsuran;
use App\Models\PeriodeAkuntansi;
use App\Models\Pihak;
use App\Models\Pinjaman;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LendingPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pimpinan;

    private Pihak $peminjam;

    private Pinjaman $loan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->admin = User::factory()->create();
        $this->pimpinan = User::factory()->pimpinan()->create();
        $periode = PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka']);
        $kredit = UnitUsaha::where('kode_unit', 'UNIT-KREDIT')->firstOrFail();
        $this->peminjam = Pihak::factory()->peminjam()->create(['nama' => 'I Made Demo']);

        $rows = [
            ['kode_akun' => '1-1010', 'unit_kode' => '', 'saldo_debit' => '5000000', 'saldo_kredit' => '0', 'keterangan' => ''],
            ['kode_akun' => '3-3010', 'unit_kode' => '', 'saldo_debit' => '0', 'saldo_kredit' => '5000000', 'keterangan' => ''],
        ];
        (new CommitImport)->handle($periode, (new PreviewImport)->handle($rows), $this->admin);
        app(LockOpeningBalance::class)->handle($periode, $this->admin);

        $this->loan = app(SaveLoanDraft::class)->handle([
            'tanggal_pinjaman' => '2026-01-05', 'unit_usaha_id' => $kredit->id, 'peminjam_id' => $this->peminjam->id,
            'pokok_pinjaman' => '3000000', 'persentase_bunga' => '10', 'tenor' => 3,
            'basis_bunga' => 'flat_total', 'tanggal_angsuran_pertama' => '2026-01-10',
        ], $this->admin);
        app(DisburseLoan::class)->handle($this->loan, '2026-01-05', (int) Akun::where('system_key', 'cash')->value('id'), $this->admin, 'disb', 'h');
    }

    public function test_payments_page_lists_outstanding(): void
    {
        Livewire::actingAs($this->admin)->test(Payments::class)
            ->assertOk()
            ->assertSee($this->loan->nomor_pinjaman)
            ->assertSee('I Made Demo');
    }

    public function test_admin_dapat_membayar_angsuran(): void
    {
        $a1 = $this->loan->angsuran()->where('angsuran_ke', 1)->firstOrFail();

        Livewire::actingAs($this->admin)->test(Payments::class)
            ->call('startPay', $a1->id)
            ->set('payTanggal', '2026-01-15')
            ->call('pay')
            ->assertHasNoErrors();

        $this->assertSame('lunas', $a1->fresh()->status->value);
    }

    public function test_pimpinan_tidak_boleh_membayar(): void
    {
        $a1 = $this->loan->angsuran()->where('angsuran_ke', 1)->firstOrFail();

        Livewire::actingAs($this->pimpinan)->test(Payments::class)
            ->call('startPay', $a1->id)
            ->assertForbidden();
    }

    public function test_schedules_page_lists_installments(): void
    {
        Livewire::actingAs($this->admin)->test(Schedules::class)
            ->assertOk()
            ->assertSee($this->loan->nomor_pinjaman);

        $this->assertSame(3, Angsuran::where('pinjaman_id', $this->loan->id)->count());
    }

    public function test_borrowers_page_shows_stats(): void
    {
        Livewire::actingAs($this->admin)->test(Borrowers::class)
            ->assertOk()
            ->assertSee('I Made Demo');
    }
}
