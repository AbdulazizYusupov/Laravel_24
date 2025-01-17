<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        User::create([
            'name' => 'Admin',
            'email' => 'isabekoffacademy@gmail.com',
            'image' => 'uploads/67891180e0418.jpg',
            'chat_id' => 6784560209,
            'role' => 'admin',
            'email_verified_at' => now(),
            'password' => Hash::make('123456789'),
            'status' => 1,
        ]);

        
    }
}
