<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Dashboard\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_admin(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Saldo Kas & Bank');
    }

    public function test_dashboard_renders_for_pimpinan(): void
    {
        $pimpinan = User::factory()->pimpinan()->create();

        Livewire::actingAs($pimpinan)
            ->test(Index::class)
            ->assertOk();
    }

    public function test_admin_can_run_admin_only_mutation(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('clearReportCache')
            ->assertOk();
    }

    public function test_pimpinan_cannot_run_admin_only_mutation(): void
    {
        $pimpinan = User::factory()->pimpinan()->create();

        Livewire::actingAs($pimpinan)
            ->test(Index::class)
            ->call('clearReportCache')
            ->assertForbidden();
    }
}
