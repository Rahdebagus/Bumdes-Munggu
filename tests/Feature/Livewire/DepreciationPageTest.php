<?php

namespace Tests\Feature\Livewire;

use App\Domain\FixedAssets\RegisterAsset;
use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\PreviewImport;
use App\Livewire\FixedAssets\Depreciation;
use App\Models\AsetTetap;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DepreciationPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pimpinan;

    private PeriodeAkuntansi $periode;

    private AsetTetap $aset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->admin = User::factory()->create();
        $this->pimpinan = User::factory()->pimpinan()->create();
        $this->periode = PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka']);
        $unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();

        $rows = [
            ['kode_akun' => '1-1010', 'unit_kode' => '', 'saldo_debit' => '5000000', 'saldo_kredit' => '0', 'keterangan' => ''],
            ['kode_akun' => '3-3010', 'unit_kode' => '', 'saldo_debit' => '0', 'saldo_kredit' => '5000000', 'keterangan' => ''],
        ];
        (new CommitImport)->handle($this->periode, (new PreviewImport)->handle($rows), $this->admin);
        app(LockOpeningBalance::class)->handle($this->periode, $this->admin);

        $this->aset = app(RegisterAsset::class)->handle([
            'kode_aset' => 'AST-DEP-1', 'unit_usaha_id' => $unit->id, 'nama_aset' => 'Mesin',
            'tanggal_perolehan' => '2026-01-05', 'harga_perolehan' => '1200000', 'nilai_residu' => '0',
            'umur_manfaat' => 12, 'tanggal_mulai_susut' => '2026-01-31',
        ], $this->admin);
    }

    public function test_page_menampilkan_aset_siap_diposting(): void
    {
        Livewire::actingAs($this->admin)->test(Depreciation::class, ['periodeId' => $this->periode->id])
            ->assertOk()
            ->assertSee('AST-DEP-1')
            ->assertSee('Siap diposting');
    }

    public function test_admin_dapat_posting_penyusutan(): void
    {
        Livewire::actingAs($this->admin)->test(Depreciation::class, ['periodeId' => $this->periode->id])
            ->call('post', $this->aset->id)
            ->assertHasNoErrors();

        $this->assertSame(1, Transaksi::where('posting_key', "DEP:{$this->aset->id}:{$this->periode->id}")
            ->whereNotNull('posted_at')->count());
    }

    public function test_batch_posting_semua_eligible(): void
    {
        Livewire::actingAs($this->admin)->test(Depreciation::class, ['periodeId' => $this->periode->id])
            ->call('postAllEligible')
            ->assertHasNoErrors();

        $this->assertSame('100000.00', Transaksi::where('posting_key', "DEP:{$this->aset->id}:{$this->periode->id}")->value('total'));
    }

    public function test_pimpinan_tidak_boleh_posting(): void
    {
        Livewire::actingAs($this->pimpinan)->test(Depreciation::class, ['periodeId' => $this->periode->id])
            ->call('post', $this->aset->id)
            ->assertForbidden();
    }
}
