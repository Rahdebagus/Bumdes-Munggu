<?php

namespace Tests\Feature\Livewire;

use App\Domain\MasterData\SaveAccount;
use App\Domain\MasterData\SaveProduct;
use App\Enums\StatusUmum;
use App\Livewire\MasterData\Units;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\SaldoAwalAkun;
use App\Models\UnitUsaha;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_unit(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(Units::class)
            ->set('form.kode_unit', 'UNIT-X')
            ->set('form.nama_unit', 'Unit X')
            ->set('form.jenis_usaha', 'dagang')
            ->set('form.penanggung_jawab', 'Budi')
            ->set('form.status', 'aktif')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('unit_usaha', ['kode_unit' => 'UNIT-X', 'nama_unit' => 'Unit X']);
    }

    public function test_pimpinan_cannot_mutate_master(): void
    {
        $pimpinan = User::factory()->pimpinan()->create();

        Livewire::actingAs($pimpinan)
            ->test(Units::class)
            ->call('create')
            ->assertForbidden();
    }

    public function test_deactivate_sets_nonaktif_not_delete(): void
    {
        $admin = User::factory()->create();
        $unit = UnitUsaha::factory()->create(['status' => 'aktif']);

        Livewire::actingAs($admin)
            ->test(Units::class)
            ->call('toggleStatus', $unit->id)
            ->assertHasNoErrors();

        $unit->refresh();
        $this->assertSame(StatusUmum::Nonaktif, $unit->status);
        $this->assertDatabaseHas('unit_usaha', ['id' => $unit->id]); // masih ada (bukan hapus)
    }

    public function test_save_product_ignores_direct_stock_fields(): void
    {
        $unit = UnitUsaha::factory()->create();

        $produk = (new SaveProduct)->handle([
            'unit_usaha_id' => $unit->id,
            'kode_produk' => 'PRD-X',
            'nama_produk' => 'Produk X',
            'satuan' => 'pcs',
            'harga_beli' => 1000,
            'harga_jual' => 1500,
            'stok_minimum' => 5,
            'status' => 'aktif',
            'stok_terkini' => 999,        // harus diabaikan (cache)
            'harga_pokok_rata' => 777,    // harus diabaikan (cache)
        ]);

        $produk->refresh(); // baca nilai default DB (bukan yang dikirim)
        $this->assertSame('0.0000', $produk->stok_terkini);
        $this->assertSame('0.000000', $produk->harga_pokok_rata);
        $this->assertDatabaseHas('produk', ['kode_produk' => 'PRD-X', 'stok_terkini' => 0]);
    }

    public function test_used_account_cannot_change_meaning(): void
    {
        $akun = Akun::factory()->create([
            'kode_akun' => '1-9999',
            'jenis_akun' => 'aset',
            'saldo_normal' => 'debit',
        ]);

        // Jadikan "terpakai" dengan referensi saldo awal.
        $periode = PeriodeAkuntansi::factory()->create();
        SaldoAwalAkun::create([
            'periode_id' => $periode->id,
            'akun_id' => $akun->id,
            'created_by' => User::factory()->create()->id,
            'saldo_debit' => 100,
            'saldo_kredit' => 0,
        ]);

        $this->expectException(ValidationException::class);
        (new SaveAccount)->handle(['jenis_akun' => 'beban'], $akun);
    }

    public function test_unused_account_can_change_meaning(): void
    {
        $akun = Akun::factory()->create(['jenis_akun' => 'aset']);

        (new SaveAccount)->handle(['jenis_akun' => 'beban'], $akun);

        $this->assertSame('beban', $akun->fresh()->jenis_akun->value);
    }
}
