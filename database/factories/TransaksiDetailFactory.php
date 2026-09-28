<?php

namespace Database\Factories;

use App\Models\Akun;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TransaksiDetail> */
class TransaksiDetailFactory extends Factory
{
    protected $model = TransaksiDetail::class;

    public function definition(): array
    {
        return [
            'transaksi_id' => Transaksi::factory(),
            'akun_id' => Akun::factory(),
            'debit' => 100_000,
            'kredit' => 0,
            'nomor_baris' => 1,
        ];
    }

    public function kredit(float $nilai = 100_000): static
    {
        return $this->state(fn () => ['debit' => 0, 'kredit' => $nilai, 'nomor_baris' => 2]);
    }
}
