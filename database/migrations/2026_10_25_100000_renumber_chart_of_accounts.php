<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran ulang chart of accounts ke format BUMDes Munggu (1.1.01.01, 4.1.1,
 * dst). Hanya kode_akun yang diganti — id akun tetap sehingga jurnal, saldo
 * awal, dan transaksi lama tidak tersentuh. Akun baru (penjualan/HPP per
 * kategori, pendapatan lain, dll) dibuat oleh MasterDataSeeder (idempotent).
 * Aman dijalankan pada database kosong (tidak ada baris yang diubah).
 */
return new class extends Migration
{
    /** @var array<string, string> kode lama => kode baru */
    public const MAP = [
        '1-1010' => '1.1.01.01',
        '1-1020' => '1.1.01.02',
        '1-1030' => '1.1.05.01',
        '1-1040' => '1.1.03.01',
        '1-1050' => '1.1.03.03',
        '1-1060' => '1.1.06.01',
        '1-1500' => '1.3.03.01',
        '1-1600' => '1.3.07.01',
        '2-2010' => '2.1.00.01',
        '2-2020' => '2.1.00.03',
        '3-3010' => '3.1.01.00',
        '3-3020' => '3.3.01.00',
        '3-3030' => '3.1.02.00',
        '3-3040' => '3.2.01.00',
        '3-3050' => '3.2.02.00',
        '3-3060' => '3.2.10.00',
        '3-3070' => '3.2.03.00',
        '4-4010' => '4.1.2',
        '4-4020' => '4.4.4',
        '4-4030' => '4.4.5',
        '4-4040' => '4.4.9',
        '4-4050' => '4.4.1',
        '5-5010' => '5.1.2',
        '6-6010' => '6.1.9',
        '6-6020' => '6.1.4',
        '6-6030' => '6.2.9',
        '6-6040' => '6.1.1',
        '6-6050' => '6.1.2',
        '6-6060' => '6.1.3',
        '6-6070' => '6.2.2',
        '6-6080' => '6.2.1',
        '6-6090' => '8.0',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::MAP as $lama => $baru) {
                // Lewati bila kode baru sudah dipakai (mis. dibuat manual).
                if (DB::table('akun')->where('kode_akun', $baru)->exists()) {
                    continue;
                }
                DB::table('akun')->where('kode_akun', $lama)->update(['kode_akun' => $baru, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach (self::MAP as $lama => $baru) {
                if (DB::table('akun')->where('kode_akun', $lama)->exists()) {
                    continue;
                }
                DB::table('akun')->where('kode_akun', $baru)->update(['kode_akun' => $lama, 'updated_at' => now()]);
            }
        });
    }
};
