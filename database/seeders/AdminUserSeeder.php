<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@hostfolio.my.id');
        $password = env('ADMIN_PASSWORD', 'bismillah');

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['ADMIN_EMAIL' => 'Set a valid ADMIN_EMAIL in the environment.']);
        }

        if (! is_string($password) || strlen($password) < 8) {
            throw ValidationException::withMessages(['ADMIN_PASSWORD' => 'Set an ADMIN_PASSWORD with at least 8 characters.']);
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
