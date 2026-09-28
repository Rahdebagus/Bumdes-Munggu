<?php

namespace Database\Factories;

use App\Enums\AkunJenis;
use App\Enums\SaldoNormal;
use App\Models\Akun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Akun> */
class AkunFactory extends Factory
{
    protected $model = Akun::class;

    public function definition(): array
    {
        return [
            'kode_akun' => fake()->unique()->numerify('#-####'),
            'nama_akun' => 'Akun '.fake()->words(2, true),
            'jenis_akun' => AkunJenis::Aset->value,
            'saldo_normal' => SaldoNormal::Debit->value,
            'is_active' => true,
            'is_cash' => false,
        ];
    }

    public function kas(): static
    {
        return $this->state(fn () => ['is_cash' => true, 'system_key' => 'cash']);
    }
}
