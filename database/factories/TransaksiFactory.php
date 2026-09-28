<?php

namespace Database\Factories;

use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Transaksi> */
class TransaksiFactory extends Factory
{
    protected $model = Transaksi::class;

    public function definition(): array
    {
        return [
            'nomor_transaksi' => 'TRX-'.fake()->unique()->numerify('########'),
            'tanggal' => '2026-01-15',
            'periode_id' => PeriodeAkuntansi::factory(),
            'user_id' => User::factory(),
            'jenis_transaksi' => TransaksiJenis::Penjualan->value,
            'total' => fake()->numberBetween(10_000, 1_000_000),
            'status' => TransaksiStatus::Draft->value,
            'request_key' => (string) Str::uuid(),
            'version' => 1,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => ['status' => TransaksiStatus::Posted->value]);
    }
}
