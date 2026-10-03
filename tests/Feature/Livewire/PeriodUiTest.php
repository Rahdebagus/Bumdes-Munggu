<?php

namespace Tests\Feature\Livewire;

use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\PreviewImport;
use App\Domain\Period\ClosePeriod;
use App\Enums\PeriodeStatus;
use App\Livewire\Period\Index;
use App\Models\PeriodeAkuntansi;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PeriodUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pimpinan;

    private PeriodeAkuntansi $periode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->admin = User::factory()->create();
        $this->pimpinan = User::factory()->pimpinan()->create();
        $this->periode = PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka']);

        $rows = [
            ['kode_akun' => '1-1010', 'unit_kode' => '', 'saldo_debit' => '1000000', 'saldo_kredit' => '0', 'keterangan' => ''],
            ['kode_akun' => '3-3010', 'unit_kode' => '', 'saldo_debit' => '0', 'saldo_kredit' => '1000000', 'keterangan' => ''],
        ];
        (new CommitImport)->handle($this->periode, (new PreviewImport)->handle($rows), $this->admin);
        app(LockOpeningBalance::class)->handle($this->periode, $this->admin);
    }

    public function test_admin_dapat_menutup_periode_bersih(): void
    {
        Livewire::actingAs($this->admin)->test(Index::class)
            ->assertOk()
            ->assertSee('Periode Akuntansi')
            ->call('close', $this->periode->id)
            ->assertHasNoErrors();

        $this->assertSame(PeriodeStatus::Ditutup, $this->periode->fresh()->status);
    }

    public function test_pimpinan_tidak_boleh_menutup_periode(): void
    {
        Livewire::actingAs($this->pimpinan)->test(Index::class)
            ->call('close', $this->periode->id)
            ->assertForbidden();

        $this->assertSame(PeriodeStatus::Terbuka, $this->periode->fresh()->status);
    }

    public function test_admin_dapat_membuat_periode_baru(): void
    {
        Livewire::actingAs($this->admin)->test(Index::class)
            ->set('newBulan', 10)
            ->set('newTahun', 2026)
            ->call('createPeriod')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('periode_akuntansi', [
            'bulan' => 10, 'tahun' => 2026, 'status' => 'terbuka',
            'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-10-31',
        ]);
    }

    public function test_membuat_periode_duplikat_ditolak(): void
    {
        Livewire::actingAs($this->admin)->test(Index::class)
            ->set('newBulan', 1)
            ->set('newTahun', 2026)
            ->call('createPeriod')
            ->assertHasErrors('periode');
    }

    public function test_pimpinan_tidak_boleh_membuat_periode(): void
    {
        Livewire::actingAs($this->pimpinan)->test(Index::class)
            ->set('newBulan', 11)
            ->set('newTahun', 2026)
            ->call('createPeriod')
            ->assertForbidden();
    }

    public function test_admin_dapat_reopen_dengan_alasan(): void
    {
        app(ClosePeriod::class)->handle($this->periode, $this->admin);

        Livewire::actingAs($this->admin)->test(Index::class)
            ->call('startReopen', $this->periode->id)
            ->set('reopenReason', 'Koreksi')
            ->call('reopen')
            ->assertHasNoErrors();

        $this->assertSame(PeriodeStatus::Terbuka, $this->periode->fresh()->status);
    }
}
