<?php

namespace Tests\Feature\Seeders;

use App\Domain\Accounting\PeriodGuard;
use App\Domain\Accounting\PostingService;
use App\Domain\Cash\SaveCashDraft;
use App\Enums\TransaksiStatus;
use App\Models\Akun;
use App\Models\LogAktivitas;
use App\Models\PeriodeAkuntansi;
use App\Models\SaldoAwalAkun;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SaldoAwalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class SaldoAwalSeederTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-23 10:00:00'));
        $this->seed(MasterDataSeeder::class);
        $this->actor = User::factory()->create();
    }

    private function currentPeriod(array $attributes = []): PeriodeAkuntansi
    {
        return PeriodeAkuntansi::create(array_merge([
            'tahun' => 2026,
            'bulan' => 10,
            'tanggal_mulai' => '2026-10-01',
            'tanggal_selesai' => '2026-10-31',
            'status' => 'terbuka',
        ], $attributes));
    }

    private function assertSeederRejected(string $message): void
    {
        try {
            $this->seed(SaldoAwalSeeder::class);
            $this->fail('Seeder seharusnya menolak tanpa mengubah data.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_seeds_current_month_balances_and_allows_a_transaction_to_be_posted(): void
    {
        $this->seed(SaldoAwalSeeder::class);

        $periode = PeriodeAkuntansi::sole();
        $this->assertSame(2026, $periode->tahun);
        $this->assertSame(10, $periode->bulan);
        $this->assertSame('2026-10-01', $periode->tanggal_mulai->toDateString());
        $this->assertSame('2026-10-31', $periode->tanggal_selesai->toDateString());
        $this->assertSame('terbuka', $periode->status->value);
        $this->assertNotNull($periode->opening_locked_at);
        $this->assertSame($this->actor->id, $periode->opening_locked_by);
        $this->assertDatabaseCount('saldo_awal_akun', 5);
        $this->assertSame('30000000.00', (string) SaldoAwalAkun::sum('saldo_debit'));
        $this->assertSame('30000000.00', (string) SaldoAwalAkun::sum('saldo_kredit'));
        $this->assertSame(0, SaldoAwalAkun::whereNull('locked_at')->count());
        $this->assertDatabaseHas('log_aktivitas', [
            'modul' => 'OpeningBalance', 'aksi' => 'lock', 'user_id' => $this->actor->id,
        ]);
        $this->assertDatabaseCount('transaksi', 0);

        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-10-23',
            'unit_usaha_id' => UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->value('id'),
            'kategori_kode' => 'jasa',
            'akun_kas_id' => Akun::where('system_key', 'cash')->value('id'),
            'akun_lawan_id' => Akun::where('system_key', 'sales')->value('id'),
            'nominal' => '100000',
        ], $this->actor);

        $posted = app(PostingService::class)->post($draft, 1, $this->actor, 'saldo-seeder-post', 'h');
        $this->assertSame(TransaksiStatus::Posted, $posted->status);
        $this->assertDatabaseCount('transaksi_detail', 2);
    }

    public function test_existing_empty_period_is_reused_and_locked(): void
    {
        $periode = $this->currentPeriod(['revision' => 7]);

        $this->seed(SaldoAwalSeeder::class);

        $this->assertDatabaseCount('periode_akuntansi', 1);
        $this->assertSame($periode->id, PeriodeAkuntansi::sole()->id);
        $this->assertSame(7, $periode->fresh()->revision);
        $this->assertSame('2026-10-01', $periode->fresh()->tanggal_mulai->toDateString());
        $this->assertSame('2026-10-31', $periode->fresh()->tanggal_selesai->toDateString());
        $this->assertNotNull($periode->fresh()->opening_locked_at);
        app(PeriodGuard::class)->assertPostable($periode->fresh());
    }

    public function test_rerun_preserves_existing_records_and_locks(): void
    {
        $this->seed(SaldoAwalSeeder::class);
        $periodBefore = PeriodeAkuntansi::sole()->getAttributes();
        $balancesBefore = SaldoAwalAkun::orderBy('id')->get()->toArray();
        $auditCount = LogAktivitas::count();
        $this->travel(1)->hours();

        $this->seed(SaldoAwalSeeder::class);

        $this->assertSame($periodBefore, PeriodeAkuntansi::sole()->getAttributes());
        $this->assertSame($balancesBefore, SaldoAwalAkun::orderBy('id')->get()->toArray());
        $this->assertSame($auditCount, LogAktivitas::count());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_later_month_uses_existing_cutover_without_adding_balances(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 10:00:00'));
        $this->seed(SaldoAwalSeeder::class);
        $balancesBefore = SaldoAwalAkun::orderBy('id')->get()->toArray();
        $this->travelTo(Carbon::parse('2026-10-23 10:00:00'));

        $this->seed(SaldoAwalSeeder::class);

        $periode = PeriodeAkuntansi::where('bulan', 10)->sole();
        $this->assertNull($periode->opening_locked_at);
        $this->assertSame($balancesBefore, SaldoAwalAkun::orderBy('id')->get()->toArray());
        app(PeriodGuard::class)->assertPostable($periode);
    }

    public function test_existing_draft_balance_is_preserved_without_locking(): void
    {
        $periode = $this->currentPeriod();
        $balance = SaldoAwalAkun::create([
            'periode_id' => $periode->id,
            'akun_id' => Akun::where('system_key', 'cash')->value('id'),
            'saldo_debit' => '12345',
            'saldo_kredit' => '0',
            'created_by' => $this->actor->id,
        ]);
        $before = $balance->fresh()->getAttributes();

        $this->assertSeederRejected('Sudah ada saldo awal');

        $this->assertSame($before, $balance->fresh()->getAttributes());
        $this->assertNull($periode->fresh()->opening_locked_at);
        $this->assertDatabaseCount('saldo_awal_akun', 1);
        $this->assertDatabaseCount('log_aktivitas', 0);
    }

    public function test_closed_period_is_preserved(): void
    {
        $periode = $this->currentPeriod(['status' => 'ditutup']);
        $before = $periode->getAttributes();

        $this->assertSeederRejected('sudah ditutup');

        $this->assertSame($before, $periode->fresh()->getAttributes());
        $this->assertDatabaseCount('saldo_awal_akun', 0);
    }

    public function test_missing_active_admin_rolls_back_the_new_period(): void
    {
        $this->actor->update(['status' => 'nonaktif']);

        $this->assertSeederRejected('Akun admin aktif belum ada');

        $this->assertDatabaseCount('periode_akuntansi', 0);
        $this->assertDatabaseCount('saldo_awal_akun', 0);
    }

    public function test_invalid_master_data_rolls_back_the_new_period(): void
    {
        Akun::where('system_key', 'cash')->update(['is_active' => false]);

        $this->assertSeederRejected('Saldo latihan belum valid');

        $this->assertDatabaseCount('periode_akuntansi', 0);
        $this->assertDatabaseCount('saldo_awal_akun', 0);
        $this->assertDatabaseCount('log_aktivitas', 0);
    }

    public function test_future_cutover_does_not_create_another_opening_or_period(): void
    {
        $this->travelTo(Carbon::parse('2026-11-23 10:00:00'));
        $this->seed(SaldoAwalSeeder::class);
        $balancesBefore = SaldoAwalAkun::orderBy('id')->get()->toArray();
        $this->travelTo(Carbon::parse('2026-10-23 10:00:00'));

        $this->assertSeederRejected('Sudah ada saldo awal');

        $this->assertDatabaseCount('periode_akuntansi', 1);
        $this->assertSame($balancesBefore, SaldoAwalAkun::orderBy('id')->get()->toArray());
    }

    public function test_posted_history_without_cutover_is_preserved(): void
    {
        $periode = $this->currentPeriod();
        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-10-23',
            'kategori_kode' => 'jasa',
            'akun_kas_id' => Akun::where('system_key', 'cash')->value('id'),
            'akun_lawan_id' => Akun::where('system_key', 'sales')->value('id'),
            'nominal' => '100000',
        ], $this->actor);
        $draft->update([
            'status' => 'posted', 'posted_at' => now(), 'posted_by' => $this->actor->id,
        ]);
        $before = $draft->fresh()->getAttributes();

        $this->assertSeederRejected('histori posting');

        $this->assertSame($before, $draft->fresh()->getAttributes());
        $this->assertNull($periode->fresh()->opening_locked_at);
        $this->assertDatabaseCount('saldo_awal_akun', 0);
    }
}
