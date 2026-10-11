<?php

namespace Tests\Feature\Livewire;

use App\Domain\Accounting\PostingService;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\PreviewImport;
use App\Domain\Reporting\LaporanDesaService;
use App\Enums\LaporanDesaStatus;
use App\Livewire\Reports\LaporanDesa;
use App\Models\Akun;
use App\Models\LaporanDesa as LaporanDesaModel;
use App\Models\PeriodeAkuntansi;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LaporanDesaUiTest extends TestCase
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
        $unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();

        $rows = [
            ['kode_akun' => '1-1010', 'unit_kode' => '', 'saldo_debit' => '1000000', 'saldo_kredit' => '0', 'keterangan' => ''],
            ['kode_akun' => '3-3010', 'unit_kode' => '', 'saldo_debit' => '0', 'saldo_kredit' => '1000000', 'keterangan' => ''],
        ];
        (new CommitImport)->handle($this->periode, (new PreviewImport)->handle($rows), $this->admin);
        app(LockOpeningBalance::class)->handle($this->periode, $this->admin);

        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-10', 'unit_usaha_id' => $unit->id,
            'kategori_kode' => 'jasa', 'akun_kas_id' => (int) Akun::where('system_key', 'cash')->value('id'),
            'akun_lawan_id' => (int) Akun::where('system_key', 'sales')->value('id'), 'nominal' => '500000',
        ], $this->admin);
        app(PostingService::class)->post($draft, 1, $this->admin, 'jasa-ui', 'h');
    }

    public function test_admin_generate_draft(): void
    {
        Livewire::actingAs($this->admin)->test(LaporanDesa::class, ['periodeId' => $this->periode->id])
            ->assertOk()
            ->call('generate')
            ->assertHasNoErrors()
            ->assertSee('LD-202601-v1');

        $this->assertSame('500000.00', LaporanDesaModel::first()->omzet_dagang);
    }

    public function test_pimpinan_tidak_boleh_generate(): void
    {
        Livewire::actingAs($this->pimpinan)->test(LaporanDesa::class, ['periodeId' => $this->periode->id])
            ->call('generate')
            ->assertForbidden();
    }

    public function test_admin_tidak_boleh_approve(): void
    {
        $laporan = app(LaporanDesaService::class)->generate($this->periode, $this->admin);
        app(LaporanDesaService::class)->submit($laporan, $this->admin);

        Livewire::actingAs($this->admin)->test(LaporanDesa::class, ['periodeId' => $this->periode->id])
            ->call('approve', $laporan->id)
            ->assertForbidden();
    }

    public function test_alur_generate_submit_approve(): void
    {
        $component = Livewire::actingAs($this->admin)->test(LaporanDesa::class, ['periodeId' => $this->periode->id]);
        $component->call('generate')->assertHasNoErrors();
        $laporan = LaporanDesaModel::firstOrFail();
        $component->call('submit', $laporan->id)->assertHasNoErrors();

        Livewire::actingAs($this->pimpinan)->test(LaporanDesa::class, ['periodeId' => $this->periode->id])
            ->call('approve', $laporan->id)
            ->assertHasNoErrors();

        $this->assertSame(LaporanDesaStatus::Disetujui, $laporan->fresh()->status);
    }

    public function test_ekspor_resmi_mengunduh_pdf(): void
    {
        $laporan = app(LaporanDesaService::class)->generate($this->periode, $this->admin);
        app(LaporanDesaService::class)->submit($laporan, $this->admin);
        app(LaporanDesaService::class)->approve($laporan->fresh(), $this->pimpinan);

        $response = $this->actingAs($this->admin)->get(route('reports.laporan-desa.export', [
            'laporan' => $laporan->id, 'format' => 'pdf',
        ]));

        $response->assertOk();
        $response->assertDownload('omzet-desa_2026-01_semua-unit.pdf');
    }

    public function test_catat_pengiriman_ke_desa(): void
    {
        $laporan = app(LaporanDesaService::class)->generate($this->periode, $this->admin);
        app(LaporanDesaService::class)->submit($laporan, $this->admin);
        app(LaporanDesaService::class)->approve($laporan->fresh(), $this->pimpinan);

        Livewire::actingAs($this->admin)->test(LaporanDesa::class, ['periodeId' => $this->periode->id])
            ->call('startDelivery', $laporan->id)
            ->set('deliverKirim', '2026-02-01')
            ->set('deliverTerima', '2026-02-03')
            ->set('deliverBukti', 'Tanda terima Sekdes')
            ->call('recordDelivery')
            ->assertHasNoErrors();

        $fresh = $laporan->fresh();
        $this->assertSame(LaporanDesaStatus::Diterima, $fresh->status);
        $this->assertSame('2026-02-01', $fresh->tanggal_dikirim->toDateString());
        $this->assertSame('Tanda terima Sekdes', $fresh->bukti_penerimaan);
    }
}
