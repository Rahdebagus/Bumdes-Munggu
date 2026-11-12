<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\SaveProduct;
use App\Livewire\MasterData\Products;
use App\Models\Produk;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Kode produk selalu otomatis & berawalan sama untuk satu kategori (KSM-001, KSM-002, …). */
class ProductCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
    }

    private function produk(string $nama, string $kategori, array $extra = []): Produk
    {
        return app(SaveProduct::class)->handle([
            'nama_produk' => $nama, 'kategori' => $kategori, 'satuan' => 'Pcs',
            'harga_beli' => 1000, 'harga_jual' => 1500, 'status' => 'aktif',
        ] + $extra);
    }

    public function test_kode_berurutan_per_kategori_dan_input_manual_diabaikan(): void
    {
        $this->assertSame('KSM-001', $this->produk('Nasi Bungkus', 'konsumsi')->kode_produk);
        $this->assertSame('KSM-002', $this->produk('Es Teh', 'konsumsi', ['kode_produk' => 'BEBAS-99'])->kode_produk);
        $this->assertSame('ATK-001', $this->produk('Pulpen', 'atk')->kode_produk);
    }

    public function test_ganti_kategori_mengganti_awalan_kode(): void
    {
        $this->produk('Pulpen', 'atk');
        $teh = $this->produk('Es Teh', 'konsumsi');

        // Edit tanpa ganti kategori: kode tetap.
        app(SaveProduct::class)->handle(['kategori' => 'konsumsi', 'harga_jual' => 2000], $teh);
        $this->assertSame('KSM-001', $teh->fresh()->kode_produk);

        // Ganti kategori: kode ikut awalan kategori baru.
        app(SaveProduct::class)->handle(['kategori' => 'atk'], $teh);
        $this->assertSame('ATK-002', $teh->fresh()->kode_produk);
    }

    public function test_form_menampilkan_pratinjau_kode_dari_kategori(): void
    {
        $admin = User::factory()->create();
        $teh = $this->produk('Es Teh', 'konsumsi');

        Livewire::actingAs($admin)->test(Products::class)
            ->call('create')
            ->set('form.kategori', 'konsumsi')
            ->assertSet('form.kode_produk', 'KSM-002')
            ->set('form.kategori', 'upakara')
            ->assertSet('form.kode_produk', 'UPK-001');

        Livewire::actingAs($admin)->test(Products::class)
            ->call('edit', $teh->id)
            ->assertSet('form.kode_produk', 'KSM-001')
            ->set('form.kategori', 'pupuk')
            ->assertSet('form.kode_produk', 'PPK-001')
            ->set('form.kategori', 'konsumsi')
            ->assertSet('form.kode_produk', 'KSM-001')
            ->set('form.kategori', 'pupuk')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('PPK-001', $teh->fresh()->kode_produk);
    }
}
