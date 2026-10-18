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
 * sudah ada, dan melaporkan konflik bila email dipakai role berbeda. Identitas
 * dan password diambil dari environment (config/seed.php) — tidak ada password
 * di repo.
 *
 * Jalankan khusus (tanpa DemoSeeder/transaksi):
 *   php artisan db:seed --class=UserSeeder
 *
 * Untuk server produksi baru, pakai ProductionSeeder (master data + akun ini
 * + periode go-live sekaligus).
 */
class UserSeeder extends Seeder
{
    /**
     * Definisi akun awal dari config/seed.php. Dipakai juga ProductionSeeder
     * untuk preflight sebelum menulis apa pun ke database.
     *
     * @return array<int, array{nama: string, email: string, role: UserRole, password: ?string, env: string}>
     */
    public static function accounts(): array
    {
        return [
            [
                'nama' => (string) config('seed.admin_nama'),
                'email' => (string) config('seed.admin_email'),
                'role' => UserRole::Admin,
                'password' => config('seed.admin_password'),
                'env' => 'SEED_ADMIN_PASSWORD',
            ],
            [
                'nama' => (string) config('seed.pimpinan_nama'),
                'email' => (string) config('seed.pimpinan_email'),
                'role' => UserRole::Pimpinan,
                'password' => config('seed.pimpinan_password'),
                'env' => 'SEED_PIMPINAN_PASSWORD',
            ],
        ];
    }

    /**
     * Masalah konfigurasi akun awal. Kosong = siap dijalankan. Dikumpulkan
     * lebih dulu agar seeder berhenti SEBELUM membuat akun apa pun.
     *
     * @return array<int, string>
     */
    public static function configIssues(): array
    {
        $issues = [];
        $emails = [];

        foreach (self::accounts() as $a) {
            if (blank($a['email'])) {
                $issues[] = "Email akun {$a['role']->value} belum diatur. Set SEED_".strtoupper($a['role']->value).'_EMAIL di .env.';
            } elseif (filter_var($a['email'], FILTER_VALIDATE_EMAIL) === false) {
                $issues[] = "Email '{$a['email']}' untuk akun {$a['role']->value} tidak valid.";
            } else {
                $emails[] = $a['email'];
            }

            if (blank($a['nama'])) {
                $issues[] = "Nama akun {$a['role']->value} belum diatur. Set SEED_".strtoupper($a['role']->value).'_NAMA di .env.';
            }

            if (blank($a['password'])) {
                $issues[] = "Password awal untuk {$a['email']} belum diatur. Set {$a['env']} di .env "
                    .'(lihat .env.example), jalankan `php artisan config:clear`, lalu ulangi.';
            } elseif (strlen((string) $a['password']) < 8) {
                $issues[] = "Password {$a['env']} minimal 8 karakter.";
            }
        }

        if (count($emails) !== count(array_unique($emails))) {
            $issues[] = 'Email admin dan pimpinan tidak boleh sama.';
        }

        return $issues;
    }

    public function run(): void
    {
        // Validasi SEMUA konfigurasi dulu — berhenti sebelum membuat akun apa pun.
        $issues = self::configIssues();

        if ($issues !== []) {
            throw new RuntimeException(implode(' ', $issues).' Tidak ada akun yang dibuat.');
        }

        foreach (self::accounts() as $a) {
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
