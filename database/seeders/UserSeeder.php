<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User; // <-- Pastikan ini diarahkan ke Model User Anda
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Buat User Admin
        User::create([
            'name' => 'Admin BPS Kota Pematangsiantar',
            'email' => 'adminbps1273@bps.go.id',
            'password' => Hash::make('1273admin'),
            'role_id' => 1, // Role 1 = Admin
        ]);

        // 2. Buat User Pimpinan
        User::create([
            'name' => 'Pimpinan BPS Kota Pematangsiantar',
            'email' => 'pimpinanbps1273@bps.go.id',
            'password' => Hash::make('pimpinnabps1273'), // Sesuai permintaan Anda
            'role_id' => 2, // Role 2 = Pimpinan
        ]);

        // 3. Buat User Penanggung Jawab
        User::create([
            'name' => 'Penanggung Jawab BPS',
            'email' => 'pjbps1273@bps.go.id',
            'password' => Hash::make('1273pj'), // Ganti password default ini
            'role_id' => 3, // Role 3 = Penanggung Jawab
        ]);

        // Kita tidak perlu membuat 'user' biasa, 
        // karena itu sudah di-handle oleh 'default(2)' di migrasi Anda
        // dan di fungsi register.
    }
}
