<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // This seeder correctly provides all required fields, including 'username'.php artisan db:seed
        User::firstOrCreate(
            ['username' => 'admin'], // Find the user by username...
            [
                'name'     => 'Admin', // ...and if they don't exist, create them with this data.
                'password' => Hash::make('112016'), // Change this before deploying to production!
            ]
        );
    }
}