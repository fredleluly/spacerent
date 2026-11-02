<?php

namespace Database\Seeders;

use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@spacerent.id')],
            ['name' => 'Administrator', 'password' => env('ADMIN_PASSWORD', 'admin12345'), 'role' => 'admin'],
        );

        // Akun demo: penjual (pemilik ruangan seed) & pembeli.
        $seller = User::updateOrCreate(
            ['email' => 'penjual@spacerent.id'],
            ['name' => 'Penjual Demo', 'phone' => '081200000001', 'password' => 'penjual12345', 'role' => 'seller'],
        );
        User::updateOrCreate(
            ['email' => 'pembeli@spacerent.id'],
            ['name' => 'Pembeli Demo', 'phone' => '081200000002', 'password' => 'pembeli12345', 'role' => 'buyer'],
        );

        Space::whereNull('user_id')->update(['user_id' => $seller->id]);
    }
}
