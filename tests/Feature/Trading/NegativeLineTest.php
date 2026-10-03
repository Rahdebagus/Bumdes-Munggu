<?php

namespace Tests\Feature\Trading;

use App\Domain\Shared\DomainException;
use App\Domain\Trading\SaveTradingDraft;
use App\Enums\TransaksiJenis;
use App\Livewire\Trading\Create;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi UAT: diskon melebihi (qty × harga) membuat subtotal baris negatif —
 * harus ditolak di domain dan di form, tidak boleh menghasilkan total minus.
 */
class NegativeLineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private UnitUsaha $unit;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->admin = User::factory()->create();
        PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();
        $this->produk = Produk::factory()->create(['unit_usaha_id' => $this->unit->id]);
    }

    public function test_domain_menolak_subtotal_negatif(): void
    {
        try {
            app(SaveTradingDraft::class)->handle([
                'tanggal' => '2026-01-10', 'unit_usaha_id' => $this->unit->id, 'pihak_id' => null, 'metode_pembayaran' => 'tunai',
                'items' => [['produk_id' => $this->produk->id, 'kuantitas' => '1', 'harga' => '1', 'diskon' => '5000', 'pajak' => '0']],
            ], TransaksiJenis::Penjualan, $this->admin);
            $this->fail('Seharusnya INVALID_STATE (subtotal negatif).');
        } catch (DomainException $e) {
            $this->assertSame('INVALID_STATE', $e->domainCode);
        }

        $this->assertSame(0, Transaksi::count());
    }

    public function test_form_menolak_subtotal_negatif(): void
    {
        Livewire::actingAs($this->admin)->test(Create::class, ['jenis' => 'penjualan'])
            ->set('unit_usaha_id', $this->unit->id)
            ->set('akun_kas_id', (int) Akun::where('system_key', 'cash')->value('id'))
            ->set('items.0.produk_id', $this->produk->id)
            ->set('items.0.kuantitas', '1')
            ->set('items.0.harga', '1')
            ->set('items.0.diskon', '5000')
            ->call('save')
            ->assertHasErrors('items.0.diskon');

        $this->assertSame(0, Transaksi::count());
    }

    public function test_baris_valid_tetap_bisa_disimpan(): void
    {
        Livewire::actingAs($this->admin)->test(Create::class, ['jenis' => 'penjualan'])
            ->set('tanggal', '2026-01-10')
            ->set('unit_usaha_id', $this->unit->id)
            ->set('akun_kas_id', (int) Akun::where('system_key', 'cash')->value('id'))
            ->set('items.0.produk_id', $this->produk->id)
            ->set('items.0.kuantitas', '2')
            ->set('items.0.harga', '5000')
            ->set('items.0.diskon', '0')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('10000.00', Transaksi::firstOrFail()->total);
    }
}
