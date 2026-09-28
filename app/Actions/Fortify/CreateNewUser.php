<?php

namespace App\Actions\Fortify;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        // Catatan: registrasi publik dinonaktifkan (ADR-18). Action ini tidak
        // terhubung ke route mana pun; pembuatan user memakai `app:create-user`.
        Validator::make($input, [
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'nama' => $input['nama'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'role' => UserRole::Admin->value,
            'status' => UserStatus::Aktif->value,
        ]);
    }
}
