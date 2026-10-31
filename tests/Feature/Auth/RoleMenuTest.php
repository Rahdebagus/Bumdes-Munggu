<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pimpinan = baca + persetujuan (dokumen01): menu input transaksi tidak tampil
 * dan rute form input ditolak 403. Admin tetap melihat semuanya.
 */
class RoleMenuTest extends TestCase
{
    use RefreshDatabase;

    private const FORM_ADMIN = [
        'sales.create', 'purchases.create', 'cash.create', 'settlements.payable', 'settlements.receivable',
        'payments.index', 'loans.create', 'stocktakes.create', 'assets.create', 'accounting.manual-journal',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
    }

    public function test_pimpinan_tidak_melihat_menu_input_dan_rute_input_ditolak(): void
    {
        $pimpinan = User::factory()->pimpinan()->create();

        $this->actingAs($pimpinan)->get(route('master.products'))
            ->assertOk()
            ->assertDontSee(route('sales.create'))
            ->assertDontSee('Pelunasan Hutang Usaha')
            ->assertDontSee('Jurnal Penyesuaian')
            ->assertSee('Jurnal Umum')
            ->assertSee('Laporan Omzet Desa')
            ->assertSee('Stok Opname');

        foreach (self::FORM_ADMIN as $name) {
            $this->actingAs($pimpinan)->get(route($name))->assertForbidden();
        }

        foreach (['dashboard', 'transactions.index', 'journal.index', 'reports.index', 'stocktakes.index', 'reports.laporan-desa', 'oversight.evidence'] as $name) {
            $this->actingAs($pimpinan)->get(route($name))->assertOk();
        }
    }

    public function test_admin_melihat_semua_menu_input(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('sales.create'))
            ->assertSee('Pelunasan Hutang Usaha')
            ->assertSee('Tambah Transaksi');

        foreach (self::FORM_ADMIN as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }
    }
}
