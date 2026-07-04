<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'Mr Shelby'], // Identifiant de connexion unique
            [
                'name' => 'Shelby Dev',
                'email' => 'lionel@gmail.com',
                'password' => Hash::make('lioneltala'), // À modifier plus tard
                'role' => 'super-admin',
                'is_active' => true,
            ]
        );
    }
}
