<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Panggil seeder yang baru saja kita buat
        $this->call([
            UserSeeder::class,
            // Anda bisa tambahkan seeder lain di sini jika ada
        ]);
    }
}
