<?php

namespace Tests\Feature\OpeningBalance;

use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\OpeningReconciliation;
use App\Domain\OpeningBalance\PreviewImport;
use App\Models\PeriodeAkuntansi;
use App\Models\SaldoAwalAkun;
use App\Models\SaldoAwalRincian;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LockTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private PeriodeAkuntansi $periode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->actor = User::factory()->create();
        $this->periode = PeriodeAkuntansi::factory()->create();
    }

    /** @param array<int, array<string,string>> $rows */
    private function commit(array $rows): void
    {
        $full = array_map(fn ($r) => array_merge(
            ['kode_akun' => '', 'unit_kode' => '', 'saldo_debit' => '0', 'saldo_kredit' => '0', 'keterangan' => ''],
            $r,
        ), $rows);

        (new CommitImport)->handle($this->periode, (new PreviewImport)->handle($full), $this->actor);
    }

    private function lock(): void
    {
        app(LockOpeningBalance::class)->handle($this->periode, $this->actor);
    }

    public function test_lock_succeeds_when_balanced_without_control_accounts(): void
    {
        $this->commit([
            ['kode_akun' => '1-1010', 'saldo_debit' => '1000000'],
            ['kode_akun' => '3-3010', 'saldo_kredit' => '1000000'],
        ]);

        $this->lock();

        $this->assertNotNull($this->periode->fresh()->opening_locked_at);
        $this->assertSame(0, SaldoAwalAkun::where('periode_id', $this->periode->id)->whereNull('locked_at')->count());
    }

    public function test_lock_rejected_when_unbalanced(): void
    {
        $this->commit([
            ['kode_akun' => '1-1010', 'saldo_debit' => '1000000'],
            ['kode_akun' => '3-3010', 'saldo_kredit' => '900000'],
        ]);

        $this->expectException(ValidationException::class);
        $this->lock();
    }

    public function test_lock_rejected_when_rincian_mismatch_control_account(): void
    {
        // Persediaan (kontrol) 500k tanpa rincian, diimbangi modal 500k.
        $this->commit([
            ['kode_akun' => '1-1030', 'saldo_debit' => '500000'],
            ['kode_akun' => '3-3010', 'saldo_kredit' => '500000'],
        ]);

        $recon = app(OpeningReconciliation::class)->check($this->periode);
        $this->assertTrue($recon['balanced']);
        $this->assertNotEmpty($recon['control_issues']); // inventory 500k vs rincian 0

        $this->expectException(ValidationException::class);
        $this->lock();
    }

    public function test_lock_succeeds_after_matching_rincian(): void
    {
        $this->commit([
            ['kode_akun' => '1-1030', 'saldo_debit' => '500000'],
            ['kode_akun' => '3-3010', 'saldo_kredit' => '500000'],
        ]);

        $inventory = SaldoAwalAkun::whereHas('akun', fn ($q) => $q->where('system_key', 'inventory'))
            ->where('periode_id', $this->periode->id)->firstOrFail();

        SaldoAwalRincian::create([
            'saldo_awal_akun_id' => $inventory->id,
            'jenis' => 'stok',
            'kode_referensi' => 'BRG-001',
            'nominal' => '500000',
        ]);

        $this->lock();

        $this->assertNotNull($this->periode->fresh()->opening_locked_at);
    }

    public function test_reimport_rejected_after_lock(): void
    {
        $this->commit([
            ['kode_akun' => '1-1010', 'saldo_debit' => '1000000'],
            ['kode_akun' => '3-3010', 'saldo_kredit' => '1000000'],
        ]);
        $this->lock();

        $this->expectException(ValidationException::class);
        $this->commit([
            ['kode_akun' => '1-1020', 'saldo_debit' => '1000000'],
            ['kode_akun' => '3-3010', 'saldo_kredit' => '1000000'],
        ]);
    }
}
