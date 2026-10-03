<?php

namespace Tests\Feature\Livewire;

use App\Domain\Accounting\PostingService;
use App\Domain\Inventory\StocktakeService;
use App\Domain\Trading\SaveTradingDraft;
use App\Enums\StokOpnameStatus;
use App\Enums\TransaksiJenis;
use App\Livewire\Inventory\Stocktakes\Show;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\StokOpname;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StocktakeUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pimpinan;

    private StokOpname $opname;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->admin = User::factory()->create();
        $this->pimpinan = User::factory()->pimpinan()->create();
        $periode = PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();
        $produk = Produk::factory()->create(['unit_usaha_id' => $unit->id, 'stok_terkini' => 0, 'harga_pokok_rata' => 0]);

        $beli = app(SaveTradingDraft::class)->handle([
            'tanggal' => '2026-01-05', 'unit_usaha_id' => $unit->id, 'pihak_id' => null, 'metode_pembayaran' => 'tunai',
            'items' => [['produk_id' => $produk->id, 'kuantitas' => '10', 'harga' => '10000', 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Pembelian, $this->admin);
        app(PostingService::class)->post($beli, 1, $this->admin, 'pur-seed', 'h');

        $svc = app(StocktakeService::class);
        $this->opname = $svc->start([
            'tanggal' => '2026-01-20', 'periode_id' => $periode->id, 'unit_usaha_id' => $unit->id,
            'items' => [['produk_id' => $produk->id, 'stok_fisik' => '8']],
        ], $this->admin);
        $svc->complete($this->opname);
        $svc->submit($this->opname);
    }

    public function test_admin_cannot_approve(): void
    {
        Livewire::actingAs($this->admin)->test(Show::class, ['opname' => $this->opname])
            ->call('approve')
            ->assertForbidden();
    }

    public function test_pimpinan_approves_then_admin_posts(): void
    {
        Livewire::actingAs($this->pimpinan)->test(Show::class, ['opname' => $this->opname])
            ->call('approve')
            ->assertHasNoErrors();
        $this->assertSame(StokOpnameStatus::Disetujui, $this->opname->fresh()->status);

        // Pimpinan tidak boleh posting opname.
        Livewire::actingAs($this->pimpinan)->test(Show::class, ['opname' => $this->opname])
            ->call('post')
            ->assertForbidden();

        // Admin posting.
        Livewire::actingAs($this->admin)->test(Show::class, ['opname' => $this->opname])
            ->call('post')
            ->assertHasNoErrors();
        $this->assertSame(StokOpnameStatus::Posted, $this->opname->fresh()->status);
    }
}
