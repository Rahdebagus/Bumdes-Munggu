<?php

namespace Tests\Feature\MasterData;

use App\Domain\Reporting\ReportService;
use App\Livewire\MasterData\Products;
use App\Models\MutasiStok;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\ProdukBumdesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Tugas A: master produk BUMDes (seeder 159 item, importer, halaman Data Produk). */
class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_mengisi_159_produk_dengan_kode_kategori_dan_aman_diulang(): void
    {
        config(['seed.stok_awal_tanggal' => null]);

        $this->seed(ProdukBumdesSeeder::class);
        $this->assertSame(159, Produk::count());

        $nasi = Produk::where('nama_produk', 'Nasi Bungkus')->firstOrFail();
        $this->assertSame('konsumsi', $nasi->kategori->value);
        $this->assertStringStartsWith('KSM-', $nasi->kode_produk);
        $this->assertSame('17500.00', $nasi->harga_jual);
        $this->assertSame('Bks', $nasi->satuan);

        $this->assertFalse((bool) Produk::where('nama_produk', 'Fotocopy')->value('lacak_stok'), 'jasa tanpa stok');
        $this->assertSame('ATK-001', Produk::where('nama_produk', 'Hvs 70 gr F4 sidu')->value('kode_produk'));

        // Diulang: tidak menggandakan, harga dari file tetap berlaku.
        Produk::where('nama_produk', 'Nasi Bungkus')->update(['harga_jual' => 1]);
        $this->seed(ProdukBumdesSeeder::class);
        $this->assertSame(159, Produk::count());
        $this->assertSame('17500.00', Produk::where('nama_produk', 'Nasi Bungkus')->value('harga_jual'));
        $this->assertSame(0, MutasiStok::count(), 'tanpa tanggal stok awal, stok tidak diposting');
    }

    public function test_perintah_import_memposting_stok_awal_dan_neraca_tetap_rekonsiliasi(): void
    {
        $this->seed(MasterDataSeeder::class);
        User::factory()->create();
        PeriodeAkuntansi::factory()->bulan(6)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);

        $this->artisan('produk:import', ['file' => ProdukBumdesSeeder::FILE, '--stok-awal' => '2026-06-30'])
            ->assertSuccessful();

        $this->assertSame(159, Produk::count());
        $this->assertSame('8.0000', Produk::where('nama_produk', 'Hvs 70 gr F4 sidu')->value('stok_terkini'));
        $this->assertSame('235.0000', Produk::where('nama_produk', 'Stop Map Folio')->value('stok_terkini'));
        $this->assertGreaterThan(0, Transaksi::where('sumber_tipe', 'stok_awal')->count());

        $rekon = collect(app(ReportService::class)->reconciliation('2026-06-01', '2026-06-30', null, '2026-06-01'))->keyBy('key');
        foreach (['jurnal', 'neraca', 'stok'] as $k) {
            $this->assertTrue($rekon[$k]['match'], "rekonsiliasi {$k}");
        }

        // Import ulang (mis. bulan depan) tidak menumpuk stok awal.
        $this->artisan('produk:import', ['file' => ProdukBumdesSeeder::FILE, '--stok-awal' => '2026-06-30'])->assertSuccessful();
        $this->assertSame('8.0000', Produk::where('nama_produk', 'Hvs 70 gr F4 sidu')->value('stok_terkini'));
    }

    public function test_dry_run_tidak_menyimpan(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->artisan('produk:import', ['file' => ProdukBumdesSeeder::FILE, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Produk::count());
    }

    public function test_halaman_produk_filter_kategori_dan_edit_harga_inline(): void
    {
        config(['seed.stok_awal_tanggal' => null]);
        $this->seed(ProdukBumdesSeeder::class);
        $admin = User::factory()->create();
        $produk = Produk::where('nama_produk', 'HVS 80 gr A4 SIDU')->firstOrFail();

        Livewire::actingAs($admin)->test(Products::class)
            ->set('kategori', 'upakara')
            ->assertSee('Banten Dewa Yadnya')
            ->assertDontSee('Nasi Bungkus')
            ->set('kategori', '')
            ->assertDontSee('perlu verifikasi')
            ->call('updatePrice', $produk->id, 'harga_jual', '60000')
            ->assertDispatched('toast');

        $produk->refresh();
        $this->assertSame('60000.00', $produk->harga_jual);
    }

    public function test_tambah_produk_kode_otomatis(): void
    {
        $this->seed(MasterDataSeeder::class);
        $admin = User::factory()->create();

        Livewire::actingAs($admin)->test(Products::class)
            ->call('create')
            ->set('form.nama_produk', 'Canang Sari')
            ->set('form.kategori', 'upakara')
            ->set('form.satuan', 'Pcs')
            ->set('form.harga_beli', '2000')
            ->set('form.harga_jual', '3000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('UPK-001', Produk::where('nama_produk', 'Canang Sari')->value('kode_produk'));
    }
}
