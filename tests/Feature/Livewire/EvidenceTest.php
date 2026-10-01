<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Accounting\Transactions\Show;
use App\Models\BuktiTransaksi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function transaksi(): Transaksi
    {
        return Transaksi::factory()->create();
    }

    public function test_admin_uploads_evidence_to_private_disk(): void
    {
        $admin = User::factory()->create();
        $trx = $this->transaksi();

        Livewire::actingAs($admin)
            ->test(Show::class, ['transaksi' => $trx])
            ->set('buktiFile', UploadedFile::fake()->create('nota.pdf', 120, 'application/pdf'))
            ->set('buktiJenis', 'nota')
            ->call('uploadBukti')
            ->assertHasNoErrors();

        $bukti = BuktiTransaksi::where('transaksi_id', $trx->id)->firstOrFail();
        $this->assertSame('belum_diperiksa', $bukti->status_verifikasi->value);
        Storage::disk('local')->assertExists($bukti->file_bukti);
    }

    public function test_pimpinan_can_verify_evidence(): void
    {
        $admin = User::factory()->create();
        $pimpinan = User::factory()->pimpinan()->create();
        $trx = $this->transaksi();

        Livewire::actingAs($admin)->test(Show::class, ['transaksi' => $trx])
            ->set('buktiFile', UploadedFile::fake()->image('foto.jpg'))
            ->call('uploadBukti')->assertHasNoErrors();

        $bukti = BuktiTransaksi::where('transaksi_id', $trx->id)->firstOrFail();

        Livewire::actingAs($pimpinan)->test(Show::class, ['transaksi' => $trx])
            ->call('verifyBukti', $bukti->id)
            ->assertHasNoErrors();

        $this->assertSame('valid', $bukti->fresh()->status_verifikasi->value);
    }

    public function test_uploader_cannot_verify_own_evidence(): void
    {
        $admin = User::factory()->create();
        $trx = $this->transaksi();

        Livewire::actingAs($admin)->test(Show::class, ['transaksi' => $trx])
            ->set('buktiFile', UploadedFile::fake()->create('nota.pdf', 50, 'application/pdf'))
            ->call('uploadBukti')->assertHasNoErrors();

        $bukti = BuktiTransaksi::where('transaksi_id', $trx->id)->firstOrFail();

        Livewire::actingAs($admin)->test(Show::class, ['transaksi' => $trx])
            ->call('verifyBukti', $bukti->id)
            ->assertForbidden();

        $this->assertSame('belum_diperiksa', $bukti->fresh()->status_verifikasi->value);
    }

    public function test_reject_evidence_with_reason(): void
    {
        $admin = User::factory()->create();
        $pimpinan = User::factory()->pimpinan()->create();
        $trx = $this->transaksi();

        Livewire::actingAs($admin)->test(Show::class, ['transaksi' => $trx])
            ->set('buktiFile', UploadedFile::fake()->create('nota.pdf', 50, 'application/pdf'))
            ->call('uploadBukti')->assertHasNoErrors();

        $bukti = BuktiTransaksi::where('transaksi_id', $trx->id)->firstOrFail();

        Livewire::actingAs($pimpinan)->test(Show::class, ['transaksi' => $trx])
            ->set('rejectingBuktiId', $bukti->id)
            ->set('rejectReason', 'Tidak terbaca')
            ->call('rejectBukti')
            ->assertHasNoErrors();

        $this->assertSame('ditolak', $bukti->fresh()->status_verifikasi->value);
        $this->assertSame('Tidak terbaca', $bukti->fresh()->rejection_reason);
    }
}
