<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\AdminUserSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // This line is correct. It calls the seeder we made
        // which knows how to create an admin user with a username.
        $this->call(AdminUserSeeder::class);

        // We have removed the old, problematic User::factory()->create() call.
    }
}
