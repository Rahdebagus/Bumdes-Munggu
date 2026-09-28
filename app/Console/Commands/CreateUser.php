<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUser extends Command
{
    /**
     * Password dapat diberikan lewat opsi untuk skrip/CI, tetapi secara default
     * diminta interaktif dan tersembunyi (secret) agar tidak tampil di layar
     * maupun di history shell.
     */
    protected $signature = 'app:create-user
                            {--nama= : Nama lengkap user}
                            {--email= : Email (dipakai untuk login)}
                            {--role= : admin atau pimpinan}
                            {--password= : Password (hindari; utamakan prompt tersembunyi)}';

    protected $description = 'Buat akun pengguna (registrasi publik dinonaktifkan; password diminta tersembunyi)';

    public function handle(): int
    {
        $nama = $this->option('nama') ?: $this->ask('Nama lengkap');
        $email = $this->option('email') ?: $this->ask('Email');
        $role = $this->option('role') ?: $this->choice('Role', UserRole::values(), UserRole::Admin->value);

        $password = $this->option('password');
        if (! $password) {
            $password = $this->secret('Password (tersembunyi)');
            $confirm = $this->secret('Ulangi password');

            if ($password !== $confirm) {
                $this->components->error('Konfirmasi password tidak cocok.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            compact('nama', 'email', 'role', 'password'),
            [
                'nama' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'role' => ['required', 'string', 'in:'.implode(',', UserRole::values())],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'nama' => $nama,
            'email' => $email,
            'role' => $role,
            'status' => UserStatus::Aktif->value,
            'password' => Hash::make($password),
        ]);

        $this->components->info("User dibuat: {$user->email} (role: {$user->role->value}, status: {$user->status->value}).");

        return self::SUCCESS;
    }
}
