<?php

namespace Database\Factories;

use App\Enums\PihakJenis;
use App\Enums\StatusUmum;
use App\Models\Pihak;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pihak> */
class PihakFactory extends Factory
{
    protected $model = Pihak::class;

    public function definition(): array
    {
        return [
            'kode_pihak' => 'PIH-'.fake()->unique()->numerify('####'),
            'jenis_pihak' => PihakJenis::Pelanggan->value,
            'nama' => fake()->name(),
            'telepon' => fake()->numerify('08##########'),
            'status' => StatusUmum::Aktif->value,
        ];
    }

    public function pemasok(): static
    {
        return $this->state(fn () => ['jenis_pihak' => PihakJenis::Pemasok->value]);
    }

    public function peminjam(): static
    {
        return $this->state(fn () => ['jenis_pihak' => PihakJenis::Peminjam->value]);
    }
}
