<?php

namespace Database\Factories;

use App\Enums\StatusUmum;
use App\Models\Produk;
use App\Models\UnitUsaha;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Produk> */
class ProdukFactory extends Factory
{
    protected $model = Produk::class;

    public function definition(): array
    {
        $beli = fake()->numberBetween(5_000, 100_000);

        return [
            'unit_usaha_id' => UnitUsaha::factory(),
            'kode_produk' => 'PRD-'.fake()->unique()->numerify('####'),
            'nama_produk' => 'Produk '.fake()->words(2, true),
            'satuan' => fake()->randomElement(['pcs', 'kg', 'liter', 'box']),
            'harga_beli' => $beli,
            'harga_jual' => $beli * 1.2,
            'stok_terkini' => 0,
            'stok_minimum' => fake()->numberBetween(0, 10),
            'harga_pokok_rata' => 0,
            'stock_version' => 0,
            'status' => StatusUmum::Aktif->value,
        ];
    }
}
