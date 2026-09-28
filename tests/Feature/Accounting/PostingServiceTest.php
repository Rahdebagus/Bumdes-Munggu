<?php

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\LedgerQuery;
use App\Domain\Accounting\PostingService;
use App\Domain\Accounting\SaveManualJournal;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiStatus;
use App\Models\Akun;
use App\Models\LogAktivitas;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostingServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private PeriodeAkuntansi $periode;

    private Akun $debitAkun;

    private Akun $kreditAkun;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
        $this->periode = PeriodeAkuntansi::factory()->bulan(1)->create([
            'status' => 'terbuka',
            'opening_locked_at' => now(),
        ]);
        $this->debitAkun = Akun::factory()->create(['saldo_normal' => 'debit']);
        $this->kreditAkun = Akun::factory()->create(['saldo_normal' => 'kredit']);
    }

    private function draft(string $debit = '100000', string $kredit = '100000'): Transaksi
    {
        return (new SaveManualJournal)->handle([
            'tanggal' => '2026-01-15',
            'periode_id' => $this->periode->id,
            'keterangan' => 'uji',
            'lines' => [
                ['akun_id' => $this->debitAkun->id, 'debit' => $debit, 'kredit' => '0'],
                ['akun_id' => $this->kreditAkun->id, 'debit' => '0', 'kredit' => $kredit],
            ],
        ], $this->actor);
    }

    private function service(): PostingService
    {
        return app(PostingService::class);
    }

    public function test_posts_balanced_journal_and_reaches_ledger(): void
    {
        $trx = $this->draft();

        $ledger = app(LedgerQuery::class);
        $this->assertSame('0.00', $ledger->accountBalance($this->debitAkun->id)); // draft belum di ledger

        $posted = $this->service()->post($trx, 1, $this->actor, 'k1', 'h1');

        $this->assertSame(TransaksiStatus::Posted, $posted->status);
        $this->assertNotNull($posted->posted_at);
        $this->assertSame(2, (int) $posted->version);
        $this->assertSame('100000.00', $posted->total);
        $this->assertSame(2, TransaksiDetail::where('transaksi_id', $trx->id)->count());
        $this->assertSame('100000.00', $ledger->accountBalance($this->debitAkun->id));
        $this->assertSame(1, (int) $this->periode->fresh()->revision);
    }

    public function test_double_post_same_key_is_idempotent(): void
    {
        $trx = $this->draft();

        $this->service()->post($trx, 1, $this->actor, 'same-key', 'same-hash');
        $this->service()->post($trx, 1, $this->actor, 'same-key', 'same-hash'); // replay

        $this->assertSame(2, TransaksiDetail::where('transaksi_id', $trx->id)->count());
        $this->assertSame(1, LogAktivitas::where('request_key', 'same-key')->count());
    }

    public function test_same_key_different_hash_conflicts(): void
    {
        $a = $this->draft();
        $b = $this->draft();

        $this->service()->post($a, 1, $this->actor, 'dup-key', 'hash-A');

        try {
            $this->service()->post($b, 1, $this->actor, 'dup-key', 'hash-B');
            $this->fail('Seharusnya IDEMPOTENCY_CONFLICT');
        } catch (DomainException $e) {
            $this->assertSame('IDEMPOTENCY_CONFLICT', $e->domainCode);
        }
    }

    public function test_unbalanced_is_rejected_and_rolled_back(): void
    {
        $trx = $this->draft('100000', '90000');

        try {
            $this->service()->post($trx, 1, $this->actor, 'k', 'h');
            $this->fail('Seharusnya UNBALANCED');
        } catch (DomainException $e) {
            $this->assertSame('UNBALANCED', $e->domainCode);
        }

        $trx->refresh();
        $this->assertSame(TransaksiStatus::Draft, $trx->status);
        $this->assertNull($trx->posted_at);
        $this->assertSame(0, LogAktivitas::count());
    }

    public function test_post_rejected_on_closed_period(): void
    {
        $this->periode->update(['status' => 'ditutup']);
        $trx = $this->draft();

        try {
            $this->service()->post($trx, 1, $this->actor, 'k', 'h');
            $this->fail('Seharusnya PERIOD_CLOSED');
        } catch (DomainException $e) {
            $this->assertSame('PERIOD_CLOSED', $e->domainCode);
        }
    }

    public function test_post_rejected_when_opening_not_locked(): void
    {
        $this->periode->update(['opening_locked_at' => null]);
        $trx = $this->draft();

        try {
            $this->service()->post($trx, 1, $this->actor, 'k', 'h');
            $this->fail('Seharusnya OPENING_NOT_LOCKED');
        } catch (DomainException $e) {
            $this->assertSame('OPENING_NOT_LOCKED', $e->domainCode);
        }
    }

    public function test_optimistic_version_mismatch(): void
    {
        $trx = $this->draft();

        try {
            $this->service()->post($trx, 99, $this->actor, 'k', 'h');
            $this->fail('Seharusnya STALE_VERSION');
        } catch (DomainException $e) {
            $this->assertSame('STALE_VERSION', $e->domainCode);
        }
    }
}
