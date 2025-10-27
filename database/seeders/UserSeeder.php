<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::insert([
            [
                'name' => 'Admin',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('123456'),
                'role_id' => 1,
            ],
            [
                'name' => 'User 1',
                'email' => 'user1@gmail.com',
                'password' => Hash::make('123456'),
                'role_id' => 4,
            ],
            [
                'name' => 'User 2',
                'email' => 'user2@gmail.com',
                'password' => Hash::make('123456'),
                'role_id' => 4,
            ],
        ]);
    }
}
