<?php

namespace Tests\Feature\Livewire;

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
}
