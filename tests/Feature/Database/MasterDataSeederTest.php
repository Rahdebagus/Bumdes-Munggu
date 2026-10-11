<?php

namespace Tests\Feature\Database;

use App\Models\Akun;
use App\Models\UnitUsaha;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_units_and_full_chart_of_accounts(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertSame(2, UnitUsaha::count());
        $this->assertSame(32, Akun::count());
    }

    public function test_all_config_system_keys_are_present_and_active(): void
    {
        $this->seed(MasterDataSeeder::class);

        foreach (config('accounting.system_keys') as $key) {
            $this->assertDatabaseHas('akun', [
                'system_key' => $key,
                'is_active' => true,
            ]);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(MasterDataSeeder::class); // ulang tidak menggandakan

        $this->assertSame(32, Akun::count());
        $this->assertSame(2, UnitUsaha::count());
    }
}
