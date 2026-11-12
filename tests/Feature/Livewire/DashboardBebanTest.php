<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Dashboard\Index;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * KPI "Beban Periode" = seluruh akun beban (rinci tanpa system_key ikut), sama
 * dengan yang mengurangi laba bersih — bukan hanya akun operating_expense.
 */
class DashboardBebanTest extends TestCase
{
    use RefreshDatabase;

    public function test_beban_periode_menjumlah_semua_akun_beban_demo_januari(): void
    {
        $this->seed(DemoSeeder::class);

        // Operasional 750.000 (pegawai, transport, kantor, penyusutan)
        // + non-operasional 15.000 (pajak bunga, administrasi bank) + pajak 50.000.
        Livewire::actingAs(User::where('role', 'admin')->firstOrFail())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Rp 815.000,00')
            ->assertSee('Rp 503.000,00');
    }
}
