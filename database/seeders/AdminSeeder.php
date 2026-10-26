<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@spacerent.id')],
            ['name' => 'Administrator', 'password' => env('ADMIN_PASSWORD', 'admin12345')],
        );
    }
}
