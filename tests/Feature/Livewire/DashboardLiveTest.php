<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Dashboard\Index;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Reporting\Concerns\BuildsFixtureA;
use Tests\TestCase;

/**
 * Dashboard menampilkan KPI live dari ReportService setelah Fixture A, bukan
 * angka acak/hardcoded (dokumen07 §Dashboard).
 */
class DashboardLiveTest extends TestCase
{
    use BuildsFixtureA;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtureA();
    }

    public function test_kpi_menampilkan_angka_fixture_a(): void
    {
        $this->runFixture();

        Livewire::actingAs($this->actor)
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Rp 7.062.000,00')  // Saldo kas & bank
            ->assertSee('Rp 750.000,00')    // Omzet dagang
            ->assertSee('Rp 12.000,00')     // Pendapatan kredit
            ->assertSee('Rp 112.000,00');   // Laba bersih
    }
}
