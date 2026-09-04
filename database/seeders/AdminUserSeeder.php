<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Identifiants pris dans le .env : jamais en dur dans le dépôt
        $email    = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command->warn('ADMIN_EMAIL ou ADMIN_PASSWORD absent du .env — compte admin non créé.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => env('ADMIN_NAME', 'Fred'),
                'password'          => Hash::make($password),
                'role'              => 'admin',
                'email_verified_at' => now(),
            ],
        );

        $this->command->info("Compte admin prêt : {$email}");
    }
}
