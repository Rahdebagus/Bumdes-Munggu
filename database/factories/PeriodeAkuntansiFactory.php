<?php

namespace Database\Factories;

use App\Enums\PeriodeStatus;
use App\Models\PeriodeAkuntansi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeriodeAkuntansi> */
class PeriodeAkuntansiFactory extends Factory
{
    protected $model = PeriodeAkuntansi::class;

    public function definition(): array
    {
        $bulan = fake()->numberBetween(1, 12);
        $tahun = 2026;
        $mulai = CarbonImmutable::create($tahun, $bulan, 1);

        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $mulai->endOfMonth()->toDateString(),
            'status' => PeriodeStatus::Terbuka->value,
            'revision' => 0,
        ];
    }

    public function bulan(int $bulan, int $tahun = 2026): static
    {
        $mulai = CarbonImmutable::create($tahun, $bulan, 1);

        return $this->state(fn () => [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $mulai->endOfMonth()->toDateString(),
        ]);
    }
}
