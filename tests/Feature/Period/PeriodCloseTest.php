<?php

namespace Tests\Feature\Period;

use App\Domain\Accounting\PostingService;
use App\Domain\Accounting\ReverseTransaction;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\FixedAssets\RegisterAsset;
use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\PreviewImport;
use App\Domain\Period\ClosePeriod;
use App\Domain\Period\ReopenPeriod;
use App\Domain\Reporting\ReportService;
use App\Domain\Shared\DomainException;
use App\Enums\PeriodeStatus;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\StokOpname;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tutup/buka periode + guard penutupan + Fixture B (reverse lintas periode).
 */
class PeriodCloseTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private PeriodeAkuntansi $juni;

    private PeriodeAkuntansi $juli;

    private UnitUsaha $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->actor = User::factory()->create();
        $this->juni = PeriodeAkuntansi::factory()->bulan(6)->create(['status' => 'terbuka']);
        $this->juli = PeriodeAkuntansi::factory()->bulan(7)->create(['status' => 'terbuka']);
        $this->unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();
        $this->lockOpening($this->juni, '1000000', '1000000');
    }

    private function akunId(string $key): int
    {
        return (int) Akun::where('system_key', $key)->value('id');
    }

    private function lockOpening(PeriodeAkuntansi $periode, string $kas, string $modal): void
    {
        $rows = [
            ['kode_akun' => '1-1010', 'unit_kode' => '', 'saldo_debit' => $kas, 'saldo_kredit' => '0', 'keterangan' => ''],
            ['kode_akun' => '3-3010', 'unit_kode' => '', 'saldo_debit' => '0', 'saldo_kredit' => $modal, 'keterangan' => ''],
        ];
        (new CommitImport)->handle($periode, (new PreviewImport)->handle($rows), $this->actor);
        app(LockOpeningBalance::class)->handle($periode, $this->actor);
    }

    private function postBeban(string $tanggal, string $nominal, string $key): Transaksi
    {
        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => $tanggal, 'unit_usaha_id' => $this->unit->id,
            'kategori_kode' => 'beban', 'akun_kas_id' => $this->akunId('cash'),
            'akun_lawan_id' => $this->akunId('operating_expense'), 'nominal' => $nominal,
        ], $this->actor);

        return app(PostingService::class)->post($draft, 1, $this->actor, $key, 'h');
    }

    public function test_fixture_b_reverse_lintas_periode_dan_juni_immutable(): void
    {
        $svc = app(ReportService::class);
        $beban = $this->postBeban('2026-06-30', '100000', 'beban-juni');

        // Tutup Juni (bersih: tanpa aset/opname).
        app(ClosePeriod::class)->handle($this->juni, $this->actor);
        $this->assertSame(PeriodeStatus::Ditutup, $this->juni->fresh()->status);

        $labaJuniSebelum = $svc->labaBersih('2026-06-01', '2026-06-30');
        $this->assertSame('-100000.00', $labaJuniSebelum);

        // Reverse 2 Juli → pembalik masuk ke Juli (terbuka, carry-forward), Juni tak berubah.
        app(ReverseTransaction::class)->reverse($beban, '2026-07-02', 'koreksi', $this->actor, 'rev-beban', 'h');

        $this->assertSame('-100000.00', $svc->labaBersih('2026-06-01', '2026-06-30'), 'Laba Juni (tertutup) tidak boleh berubah.');
        $this->assertSame('100000.00', $svc->labaBersih('2026-07-01', '2026-07-31'), 'Laba Juli naik 100.000 dari pembalik.');
        $this->assertSame(PeriodeStatus::Ditutup, $this->juni->fresh()->status);

        // Original tetap reversed + posted_at utuh; reverse kedua ditolak.
        $this->assertSame('reversed', $beban->fresh()->status->value);
        $this->assertNotNull($beban->fresh()->posted_at);
        $this->expectException(DomainException::class);
        app(ReverseTransaction::class)->reverse($beban->fresh(), '2026-07-03', 'lagi', $this->actor, 'rev-2', 'h');
    }

    public function test_posting_ke_periode_tertutup_ditolak_lalu_reopen_mengizinkan(): void
    {
        app(ClosePeriod::class)->handle($this->juni, $this->actor);

        try {
            $this->postBeban('2026-06-15', '50000', 'beban-closed');
            $this->fail('Seharusnya PERIOD_CLOSED.');
        } catch (DomainException $e) {
            $this->assertSame('PERIOD_CLOSED', $e->domainCode);
        }

        // Reopen wajib alasan; revision bertambah.
        $revSebelum = $this->juni->fresh()->revision;
        app(ReopenPeriod::class)->handle($this->juni, $this->actor, 'Koreksi beban terlewat');
        $this->assertSame(PeriodeStatus::Terbuka, $this->juni->fresh()->status);
        $this->assertSame($revSebelum + 1, $this->juni->fresh()->revision);

        // Setelah reopen, posting Juni boleh lagi.
        $trx = $this->postBeban('2026-06-15', '50000', 'beban-afterreopen');
        $this->assertSame('posted', $trx->status->value);
    }

    public function test_reopen_tanpa_alasan_ditolak(): void
    {
        app(ClosePeriod::class)->handle($this->juni, $this->actor);

        $this->expectException(DomainException::class);
        app(ReopenPeriod::class)->handle($this->juni, $this->actor, '   ');
    }

    public function test_tutup_diblokir_bila_penyusutan_belum_diposting(): void
    {
        app(RegisterAsset::class)->handle([
            'kode_aset' => 'AST-CLS-1', 'unit_usaha_id' => $this->unit->id, 'nama_aset' => 'Mesin',
            'tanggal_perolehan' => '2026-06-01', 'harga_perolehan' => '1200000', 'nilai_residu' => '0',
            'umur_manfaat' => 12, 'tanggal_mulai_susut' => '2026-06-30',
        ], $this->actor);

        $blockers = app(ClosePeriod::class)->blockers($this->juni);
        $this->assertNotEmpty($blockers);

        try {
            app(ClosePeriod::class)->handle($this->juni, $this->actor);
            $this->fail('Seharusnya CLOSE_BLOCKED.');
        } catch (DomainException $e) {
            $this->assertSame('CLOSE_BLOCKED', $e->domainCode);
        }

        $this->assertSame(PeriodeStatus::Terbuka, $this->juni->fresh()->status);
    }

    public function test_tutup_diblokir_bila_opname_disetujui_belum_diposting(): void
    {
        StokOpname::create([
            'nomor_opname' => 'OPN-TEST-1',
            'tanggal' => '2026-06-20', 'periode_id' => $this->juni->id, 'unit_usaha_id' => $this->unit->id,
            'status' => 'disetujui', 'created_by' => $this->actor->id, 'approved_by' => $this->actor->id,
        ]);

        try {
            app(ClosePeriod::class)->handle($this->juni, $this->actor);
            $this->fail('Seharusnya CLOSE_BLOCKED.');
        } catch (DomainException $e) {
            $this->assertSame('CLOSE_BLOCKED', $e->domainCode);
        }
    }
}
