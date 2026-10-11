<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Reports\Index;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi: setiap jenis laporan (termasuk Buku Besar) harus merender isi, bukan
 * halaman kosong. Buku Besar sempat kosong karena tidak ada case di @switch.
 */
class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    public function test_buku_besar_menampilkan_akun_dan_mutasi(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class, ['type' => 'buku-besar', 'bulan' => 6, 'tahun' => 2026])
            ->assertOk()
            ->assertSee('Buku Besar')
            ->assertSee('1-1010')          // kode akun kas muncul di ledger
            ->assertSee('Saldo Akhir');
    }

    public function test_semua_jenis_laporan_merender(): void
    {
        foreach (['laba-rugi', 'neraca', 'perubahan-modal', 'arus-kas', 'kredit', 'omzet-desa', 'buku-besar'] as $type) {
            Livewire::actingAs($this->admin)
                ->test(Index::class, ['type' => $type, 'bulan' => 6, 'tahun' => 2026])
                ->assertOk();
        }
    }
}
