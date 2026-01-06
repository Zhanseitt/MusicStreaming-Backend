<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'name' => 'The Weeknd',
                'email' => 'weeknd@xo.com',
                'password' => Hash::make('password'),
                'role' => 'artist', 
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@soundwave.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
