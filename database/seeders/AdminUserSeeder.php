<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@cakra.com'],
            [
                'name' => 'Admin Cakra Prima',
                'password' => Hash::make('cakra2026'),
                'email_verified_at' => now(),
            ]
        );
    }
}