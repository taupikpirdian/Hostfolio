<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'hostfolio:create-admin {--name=} {--email=}';

    protected $description = 'Create an active Hostfolio administrator with an interactive secret password';

    public function handle(): int
    {
        $data = ['name' => $this->option('name') ?: $this->ask('Nama'), 'email' => $this->option('email') ?: $this->ask('Email'), 'password' => $this->secret('Password (minimum 12 karakter)')];
        $validation = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12']);
        if ($validation->fails()) {
            $this->error($validation->errors()->first());

            return self::FAILURE;
        }
        User::create([...$data, 'role' => 'admin', 'is_active' => true]);
        $this->info('Admin berhasil dibuat.');

        return self::SUCCESS;
    }
}
