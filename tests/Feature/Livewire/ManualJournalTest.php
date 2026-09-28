<?php

namespace Tests\Feature\Livewire;

use App\Domain\Accounting\SaveManualJournal;
use App\Enums\TransaksiStatus;
use App\Livewire\Accounting\ManualJournal;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManualJournalTest extends TestCase
{
    use RefreshDatabase;

    private PeriodeAkuntansi $periode;

    private Akun $debit;

    private Akun $kredit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->periode = PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->debit = Akun::factory()->create(['saldo_normal' => 'debit']);
        $this->kredit = Akun::factory()->create(['saldo_normal' => 'kredit']);
    }

    private function balancedLines(): array
    {
        return [
            ['akun_id' => $this->debit->id, 'debit' => '100000', 'kredit' => '0'],
            ['akun_id' => $this->kredit->id, 'debit' => '0', 'kredit' => '100000'],
        ];
    }

    public function test_admin_can_save_draft_then_post(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ManualJournal::class)
            ->set('tanggal', '2026-01-15')
            ->set('periodeId', $this->periode->id)
            ->set('lines', $this->balancedLines())
            ->call('saveDraft')
            ->assertHasNoErrors();

        $trx = Transaksi::where('jenis_transaksi', 'penyesuaian')->firstOrFail();
        $this->assertSame(TransaksiStatus::Draft, $trx->status);

        Livewire::actingAs($admin)
            ->test(ManualJournal::class)
            ->call('post', $trx->id)
            ->assertHasNoErrors();

        $this->assertSame(TransaksiStatus::Posted, $trx->fresh()->status);
    }

    public function test_pimpinan_cannot_save_draft(): void
    {
        $pimpinan = User::factory()->pimpinan()->create();

        Livewire::actingAs($pimpinan)
            ->test(ManualJournal::class)
            ->set('tanggal', '2026-01-15')
            ->set('periodeId', $this->periode->id)
            ->set('lines', $this->balancedLines())
            ->call('saveDraft')
            ->assertForbidden();
    }

    public function test_pimpinan_cannot_post(): void
    {
        $admin = User::factory()->create();
        $pimpinan = User::factory()->pimpinan()->create();

        $trx = (new SaveManualJournal)->handle([
            'tanggal' => '2026-01-15',
            'periode_id' => $this->periode->id,
            'lines' => $this->balancedLines(),
        ], $admin);

        Livewire::actingAs($pimpinan)
            ->test(ManualJournal::class)
            ->call('post', $trx->id)
            ->assertForbidden();

        $this->assertSame(TransaksiStatus::Draft, $trx->fresh()->status);
    }
}
