<?php

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\LedgerQuery;
use App\Domain\Accounting\PostingService;
use App\Domain\Accounting\ReverseTransaction;
use App\Domain\Accounting\SaveManualJournal;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiStatus;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReverseTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private PeriodeAkuntansi $juni;

    private PeriodeAkuntansi $juli;

    private Akun $debitAkun;

    private Akun $kreditAkun;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
        $this->juni = PeriodeAkuntansi::factory()->bulan(6)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->juli = PeriodeAkuntansi::factory()->bulan(7)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->debitAkun = Akun::factory()->create(['saldo_normal' => 'debit']);
        $this->kreditAkun = Akun::factory()->create(['saldo_normal' => 'kredit']);
    }

    private function postedInJune(): Transaksi
    {
        $trx = (new SaveManualJournal)->handle([
            'tanggal' => '2026-06-15',
            'periode_id' => $this->juni->id,
            'lines' => [
                ['akun_id' => $this->debitAkun->id, 'debit' => '100000', 'kredit' => '0'],
                ['akun_id' => $this->kreditAkun->id, 'debit' => '0', 'kredit' => '100000'],
            ],
        ], $this->actor);

        return app(PostingService::class)->post($trx, 1, $this->actor, 'post-'.$trx->id, 'h');
    }

    public function test_reverse_creates_swapped_and_linked_reversal(): void
    {
        $orig = $this->postedInJune();

        $reversal = app(ReverseTransaction::class)
            ->reverse($orig, '2026-07-02', 'koreksi', $this->actor, 'rev-'.$orig->id, 'h');

        $this->assertSame($orig->id, $reversal->reversal_of_id);
        $this->assertSame(TransaksiStatus::Posted, $reversal->status);
        $this->assertSame(TransaksiStatus::Reversed, $orig->fresh()->status);
        $this->assertNotNull($orig->fresh()->posted_at); // posted_at original tetap

        // Baris tertukar: akun debit asli kini di sisi kredit pada pembalik.
        $line = $reversal->details()->where('akun_id', $this->debitAkun->id)->first();
        $this->assertSame('0.00', $line->debit);
        $this->assertSame('100000.00', $line->kredit);
    }

    public function test_reverse_across_period_keeps_original_on_its_date(): void
    {
        $orig = $this->postedInJune();
        app(ReverseTransaction::class)->reverse($orig, '2026-07-02', 'koreksi', $this->actor, 'rev-'.$orig->id, 'h');

        $ledger = app(LedgerQuery::class);
        // Sampai 30 Juni: original tetap terhitung penuh.
        $this->assertSame('100000.00', $ledger->accountBalance($this->debitAkun->id, '2026-06-30'));
        // Sampai 31 Juli: original + pembalik = neto nol.
        $this->assertSame('0.00', $ledger->accountBalance($this->debitAkun->id, '2026-07-31'));
    }

    public function test_reverse_requires_reason(): void
    {
        $orig = $this->postedInJune();

        $this->expectException(DomainException::class);
        app(ReverseTransaction::class)->reverse($orig, '2026-07-02', '   ', $this->actor, 'rev-x', 'h');
    }

    public function test_double_reverse_rejected(): void
    {
        $orig = $this->postedInJune();
        app(ReverseTransaction::class)->reverse($orig, '2026-07-02', 'koreksi', $this->actor, 'rev-1', 'h');

        // Setelah dibalik, original berstatus reversed → tidak bisa dibalik lagi.
        try {
            app(ReverseTransaction::class)->reverse($orig, '2026-07-03', 'lagi', $this->actor, 'rev-2', 'h2');
            $this->fail('Seharusnya ditolak karena bukan posted lagi');
        } catch (DomainException $e) {
            $this->assertSame('INVALID_STATE', $e->domainCode);
        }
    }

    public function test_cannot_reverse_draft(): void
    {
        $draft = (new SaveManualJournal)->handle([
            'tanggal' => '2026-06-15',
            'periode_id' => $this->juni->id,
            'lines' => [
                ['akun_id' => $this->debitAkun->id, 'debit' => '100000', 'kredit' => '0'],
                ['akun_id' => $this->kreditAkun->id, 'debit' => '0', 'kredit' => '100000'],
            ],
        ], $this->actor);

        $this->expectException(DomainException::class);
        app(ReverseTransaction::class)->reverse($draft, '2026-07-02', 'x', $this->actor, 'rev-d', 'h');
    }
}
