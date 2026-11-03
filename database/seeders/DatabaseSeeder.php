<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Periode go-live (cutover) YYYY-MM — saldo awal modal dikunci di periode ini. */
    public const PERIODE_AWAL = '2026-11';

    /** Modal kas: D 1.1.01.01 Kas Tunai / K 3.1.01.00 Modal Desa Munggu. */
    public const MODAL_KAS = '100000000';

    /** Unit usaha pemilik saldo kas awal (sama dengan database lokal). */
    public const UNIT_KAS = 'UNIT-KREDIT';

    /**
     * Modal barang: D 1.1.05.01 Persediaan / K 3.1.01.00 Modal Desa Munggu per
     * unit = Σ stok awal 90 produk di data/modal-awal-2026-11/persediaan.csv
     * (dicek sama persis sebelum disimpan).
     */
    public const MODAL_BARANG = '100000000';

    /**
     * Seeder default — seluruh isi database BUMDes Munggu dalam satu perintah:
     *
     *   php artisan migrate:fresh --seed                             (database lokal baru)
     *   php artisan migrate --force && php artisan db:seed --force   (server produksi)
     *
     * 1. Master data + akun login
     *    - Environment production → ProductionSeeder: validasi .env lebih dulu,
     *      master data, akun Admin/Pimpinan, periode SEED_PERIODE_AWAL (opsional).
     *    - Environment lain → MasterDataSeeder (unit + chart of accounts) dan
     *      UserSeeder (akun Admin/Pimpinan).
     * 2. ModalAwalBumdesSeeder: 159 produk + saldo awal November 2026 terkunci —
     *    modal barang Rp 100.000.000 + modal kas Rp 100.000.000 = total aktiva
     *    Rp 200.000.000 (sama dengan database lokal). Atur lewat konstanta di atas.
     *
     * Wajib: SEED_ADMIN_PASSWORD & SEED_PIMPINAN_PASSWORD di .env (min. 8 karakter,
     * lihat .env.example), SEED_PERIODE_AWAL kosong atau sama dengan PERIODE_AWAL,
     * dan database belum berisi saldo awal/transaksi lain.
     *
     * Seeder lain dapat dipanggil eksplisit:
     *   php artisan db:seed --class=MasterDataSeeder           (master data saja)
     *   php artisan db:seed --class=UserSeeder                 (akun login saja)
     *   php artisan db:seed --class=ProduksiBumdesSeeder       (alternatif: neraca Excel 30 Juni 2026, database baru)
     *   php artisan db:seed --class=DemoSeeder                 (data contoh, non-produksi)
     *   php artisan db:seed --class=ContohPenyesuaianSeeder    (draft contoh jurnal penyesuaian, non-produksi)
     *
     * User tambahan dibuat lewat `php artisan app:create-user`.
     */
    public function run(): void
    {
        $this->preflight();

        if (app()->environment('production')) {
            $this->command?->info('Environment production → ProductionSeeder (master data + akun login).');
            $this->callWith(ProductionSeeder::class, ['ringkasan' => false]);
        } else {
            $this->call([
                MasterDataSeeder::class,
                UserSeeder::class,
            ]);
        }

        $this->callWith(ModalAwalBumdesSeeder::class, [
            'periode' => self::PERIODE_AWAL,
            'modalKas' => self::MODAL_KAS,
            'unitKas' => self::UNIT_KAS,
            'modalBarang' => self::MODAL_BARANG,
        ]);
    }

    /** SEED_PERIODE_AWAL lain membuat periode kedua yang bukan cutover — tolak sebelum menulis apa pun. */
    private function preflight(): void
    {
        $env = trim((string) config('seed.periode_awal'));

        if ($env !== '' && $env !== self::PERIODE_AWAL) {
            throw new RuntimeException(
                "SEED_PERIODE_AWAL={$env} berbeda dengan periode modal awal ".self::PERIODE_AWAL
                .'. Kosongkan atau samakan di .env, lalu `php artisan config:clear`.'
            );
        }
    }
}
