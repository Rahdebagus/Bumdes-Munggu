<?php

namespace Tests\Feature\Reporting;

use App\Domain\Accounting\PostingService;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\PreviewImport;
use App\Domain\Reporting\LaporanDesaService;
use App\Domain\Shared\DomainException;
use App\Enums\LaporanDesaStatus;
use App\Models\Akun;
use App\Models\LaporanDesa;
use App\Models\PeriodeAkuntansi;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Siklus snapshot Laporan Omzet Desa (dokumen10 §Alur snapshot): generate →
 * submit → approve (cek revision) → immutable → revisi berversi.
 */
class LaporanDesaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pimpinan;

    private PeriodeAkuntansi $periode;

    private UnitUsaha $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->admin = User::factory()->create();
        $this->pimpinan = User::factory()->pimpinan()->create();
        $this->periode = PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka']);
        $this->unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();
        $this->lockOpening();
        $this->incomeJasa('500000', 'jasa-1');
    }

    private function akunId(string $key): int
    {
        return (int) Akun::where('system_key', $key)->value('id');
    }

    private function lockOpening(): void
    {
        $rows = [
            ['kode_akun' => '1-1010', 'unit_kode' => '', 'saldo_debit' => '1000000', 'saldo_kredit' => '0', 'keterangan' => ''],
            ['kode_akun' => '3-3010', 'unit_kode' => '', 'saldo_debit' => '0', 'saldo_kredit' => '1000000', 'keterangan' => ''],
        ];
        (new CommitImport)->handle($this->periode, (new PreviewImport)->handle($rows), $this->admin);
        app(LockOpeningBalance::class)->handle($this->periode, $this->admin);
    }

    private function incomeJasa(string $nominal, string $key): void
    {
        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-10', 'unit_usaha_id' => $this->unit->id,
            'kategori_kode' => 'jasa', 'akun_kas_id' => $this->akunId('cash'),
            'akun_lawan_id' => $this->akunId('sales'), 'nominal' => $nominal,
        ], $this->admin);
        app(PostingService::class)->post($draft, 1, $this->admin, $key, 'h');
    }

    public function test_generate_draft_menghitung_omzet(): void
    {
        $laporan = app(LaporanDesaService::class)->generate($this->periode, $this->admin);

        $this->assertSame(LaporanDesaStatus::Draft, $laporan->status);
        $this->assertSame(1, $laporan->version);
        $this->assertSame('500000.00', $laporan->omzet_dagang);
        $this->assertSame('500000.00', $laporan->total_omzet);
        $this->assertNotNull($laporan->snapshot_hash);
        $this->assertArrayHasKey('transaksi_ids', $laporan->snapshot_data);
    }

    public function test_submit_lalu_approve_oleh_pimpinan(): void
    {
        $svc = app(LaporanDesaService::class);
        $laporan = $svc->generate($this->periode, $this->admin);
        $svc->submit($laporan, $this->admin);
        $this->assertSame(LaporanDesaStatus::Diajukan, $laporan->fresh()->status);

        $approved = $svc->approve($laporan->fresh(), $this->pimpinan);
        $this->assertSame(LaporanDesaStatus::Disetujui, $approved->status);
        $this->assertSame($this->pimpinan->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_approval_hanya_pimpinan_via_gate(): void
    {
        $this->assertTrue(Gate::forUser($this->pimpinan)->allows('approve-laporan'));
        $this->assertFalse(Gate::forUser($this->admin)->allows('approve-laporan'));
    }

    public function test_approve_ditolak_bila_data_berubah_sejak_diajukan(): void
    {
        $svc = app(LaporanDesaService::class);
        $laporan = $svc->generate($this->periode, $this->admin);
        $svc->submit($laporan, $this->admin);

        // Data periode berubah setelah diajukan.
        $this->incomeJasa('250000', 'jasa-2');

        try {
            $svc->approve($laporan->fresh(), $this->pimpinan);
            $this->fail('Seharusnya REVISION_CHANGED.');
        } catch (DomainException $e) {
            $this->assertSame('REVISION_CHANGED', $e->domainCode);
        }

        // Ajukan ulang (regenerate) lalu approve sukses dengan nilai terbaru.
        $svc->submit($laporan->fresh(), $this->admin);
        $approved = $svc->approve($laporan->fresh(), $this->pimpinan);
        $this->assertSame(LaporanDesaStatus::Disetujui, $approved->status);
        $this->assertSame('750000.00', $approved->omzet_dagang);
    }

    public function test_approved_immutable_dan_revisi_membuat_versi_baru(): void
    {
        $svc = app(LaporanDesaService::class);
        $laporan = $svc->generate($this->periode, $this->admin);
        $svc->submit($laporan, $this->admin);
        $v1 = $svc->approve($laporan->fresh(), $this->pimpinan);

        // Generate ulang ditolak (sudah disetujui).
        try {
            $svc->generate($this->periode, $this->admin);
            $this->fail('Seharusnya INVALID_STATE (immutable).');
        } catch (DomainException $e) {
            $this->assertSame('INVALID_STATE', $e->domainCode);
        }

        // Revisi membuat versi 2 tertaut ke v1; v1 tetap ada.
        $v2 = $svc->revise($v1, $this->admin);
        $this->assertSame(2, $v2->version);
        $this->assertSame($v1->id, $v2->previous_version_id);
        $this->assertSame(LaporanDesaStatus::Draft, $v2->status);
        $this->assertSame(LaporanDesaStatus::Disetujui, $v1->fresh()->status, 'Versi lama tetap disetujui/immutable.');
        $this->assertSame(2, LaporanDesa::where('periode_id', $this->periode->id)->count());
    }
}
