<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@shantaplastics.com'],
            ['name' => 'Admin', 'password' => Hash::make('password123')]
        );

        $superAdmin = Role::where('name', 'Super Admin')->first();
        $user->roles()->syncWithoutDetaching([$superAdmin->id]);
    }
}
