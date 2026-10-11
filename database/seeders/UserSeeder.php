<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Akun awal pengelola (registrasi publik dinonaktifkan). Aman dijalankan
 * berulang: tidak menggandakan, tidak mengubah password/role/profil akun yang
 * sudah ada, dan melaporkan konflik bila email dipakai role berbeda. Password
 * diambil dari environment (config/seed.php) — tidak ada password di repo.
 *
 * Jalankan khusus (tanpa DemoSeeder/transaksi):
 *   php artisan db:seed --class=UserSeeder
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'nama' => 'Administrator BUMDes',
                'email' => 'admin@bumdes-munggu.test',
                'role' => UserRole::Admin,
                'password' => config('seed.admin_password'),
                'env' => 'SEED_ADMIN_PASSWORD',
            ],
            [
                'nama' => 'Pimpinan BUMDes',
                'email' => 'pimpinan@bumdes-munggu.test',
                'role' => UserRole::Pimpinan,
                'password' => config('seed.pimpinan_password'),
                'env' => 'SEED_PIMPINAN_PASSWORD',
            ],
        ];

        // Validasi SEMUA password dulu — berhenti sebelum membuat akun apa pun.
        foreach ($accounts as $a) {
            if (blank($a['password'])) {
                throw new RuntimeException(
                    "Password awal untuk {$a['email']} belum diatur. Set {$a['env']} di .env "
                    .'(lihat .env.example), jalankan `php artisan config:clear`, lalu ulangi. '
                    .'Tidak ada akun yang dibuat.'
                );
            }
            if (strlen((string) $a['password']) < 8) {
                throw new RuntimeException("Password {$a['env']} minimal 8 karakter. Tidak ada akun yang dibuat.");
            }
        }

        foreach ($accounts as $a) {
            $existing = User::where('email', $a['email'])->first();

            if ($existing !== null) {
                if ($existing->role !== $a['role']) {
                    $this->command?->warn(
                        "KONFLIK: {$a['email']} sudah ada dengan role '{$existing->role->value}', "
                        ."diharapkan '{$a['role']->value}'. Tidak diubah."
                    );
                } else {
                    $this->command?->info("Lewati {$a['email']} — sudah ada, tidak diubah.");
                }

                continue;
            }

            User::create([
                'nama' => $a['nama'],
                'email' => $a['email'],
                'role' => $a['role']->value,
                'status' => UserStatus::Aktif->value,
                'password' => Hash::make($a['password']),
            ]);

            $this->command?->info("Akun dibuat: {$a['email']} (role {$a['role']->value}, status aktif).");
        }
    }
}
