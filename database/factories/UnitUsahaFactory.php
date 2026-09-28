<?php

namespace Database\Factories;

use App\Enums\StatusUmum;
use App\Enums\UnitJenisUsaha;
use App\Models\UnitUsaha;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitUsaha> */
class UnitUsahaFactory extends Factory
{
    protected $model = UnitUsaha::class;

    public function definition(): array
    {
        return [
            'kode_unit' => 'UNIT-'.fake()->unique()->numerify('###'),
            'nama_unit' => 'Unit '.fake()->words(2, true),
            'jenis_usaha' => UnitJenisUsaha::Dagang->value,
            'penanggung_jawab' => fake()->name(),
            'status' => StatusUmum::Aktif->value,
        ];
    }

    public function kredit(): static
    {
        return $this->state(fn () => ['jenis_usaha' => UnitJenisUsaha::Kredit->value]);
    }
}
