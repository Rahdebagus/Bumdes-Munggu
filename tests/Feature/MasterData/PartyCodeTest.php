<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\SaveParty;
use App\Livewire\MasterData\Parties;
use App\Models\Pihak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Kode pihak selalu otomatis & berawalan sama untuk satu jenis (PLG/PMS/PMJ-NNN). */
class PartyCodeTest extends TestCase
{
    use RefreshDatabase;

    private function pihak(string $nama, string $jenis, array $extra = []): Pihak
    {
        return app(SaveParty::class)->handle(['nama' => $nama, 'jenis_pihak' => $jenis, 'status' => 'aktif'] + $extra);
    }

    public function test_kode_berurutan_per_jenis_dan_input_manual_diabaikan(): void
    {
        $this->assertSame('PLG-001', $this->pihak('Warung Bu Rai', 'pelanggan')->kode_pihak);
        $this->assertSame('PLG-002', $this->pihak('Toko Sari', 'pelanggan', ['kode_pihak' => '999'])->kode_pihak);
        $this->assertSame('PMS-001', $this->pihak('CV Sumber Tani', 'pemasok')->kode_pihak);
        $this->assertSame('PMJ-001', $this->pihak('I Ketut Alit', 'peminjam')->kode_pihak);
    }

    public function test_ganti_jenis_mengganti_awalan_kode(): void
    {
        $toko = $this->pihak('Toko Sari', 'pelanggan');

        app(SaveParty::class)->handle(['nama' => 'Toko Sari Jaya', 'jenis_pihak' => 'pelanggan'], $toko);
        $this->assertSame('PLG-001', $toko->fresh()->kode_pihak);

        app(SaveParty::class)->handle(['jenis_pihak' => 'pemasok'], $toko);
        $this->assertSame('PMS-001', $toko->fresh()->kode_pihak);
    }

    public function test_form_menampilkan_pratinjau_kode_dari_jenis(): void
    {
        $admin = User::factory()->create();
        $this->pihak('Warung Bu Rai', 'pelanggan');

        Livewire::actingAs($admin)->test(Parties::class)
            ->call('create')
            ->assertSet('form.kode_pihak', 'PLG-002')
            ->set('form.jenis_pihak', 'peminjam')
            ->assertSet('form.kode_pihak', 'PMJ-001')
            ->set('form.nama', 'Nyoman Merta')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('PMJ-001', Pihak::where('nama', 'Nyoman Merta')->value('kode_pihak'));
    }

    public function test_migrasi_menomori_ulang_kode_lama(): void
    {
        DB::table('pihak')->insert([
            ['kode_pihak' => '001', 'jenis_pihak' => 'pelanggan', 'nama' => 'A', 'status' => 'aktif'],
            ['kode_pihak' => '002', 'jenis_pihak' => 'pemasok', 'nama' => 'B', 'status' => 'aktif'],
            ['kode_pihak' => 'PLG-004', 'jenis_pihak' => 'pelanggan', 'nama' => 'C', 'status' => 'aktif'],
            ['kode_pihak' => 'CUST-9', 'jenis_pihak' => 'pelanggan', 'nama' => 'D', 'status' => 'aktif'],
        ]);

        (require database_path('migrations/2026_11_12_100001_normalize_kode_pihak.php'))->up();

        $this->assertSame(
            ['A' => 'PLG-005', 'B' => 'PMS-001', 'C' => 'PLG-004', 'D' => 'PLG-006'],
            Pihak::orderBy('nama')->pluck('kode_pihak', 'nama')->all(),
        );
    }
}
