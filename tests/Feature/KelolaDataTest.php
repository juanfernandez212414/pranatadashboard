<?php

// Uji halaman Kelola Data dan nama rute di semua view.
// Latar belakang: view Kelola Data versi PJ pernah memanggil route('keloladata') dan
// route('indicators.destroy'), padahal rute PJ bernama penanggungjawab.*. Akibatnya halaman
// itu error 500 untuk semua Penanggung Jawab. Database memakai SQLite di memori (phpunit.xml).

use App\Models\Category;
use App\Models\Indicator;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('Tes kelola data hanya boleh berjalan di SQLite :memory: (lihat phpunit.xml).');
    }
    $this->artisan('migrate');
    $this->withoutVite();
});

it('hanya memakai nama rute yang terdaftar di semua view', function () {
    $tidakTerdaftar = [];

    foreach (File::allFiles(resource_path('views')) as $berkas) {
        preg_match_all("/route\\('([A-Za-z0-9_.-]+)'/", $berkas->getContents(), $cocok);
        foreach (array_unique($cocok[1]) as $nama) {
            if (!Route::has($nama)) {
                $tidakTerdaftar[] = $berkas->getRelativePathname() . ' -> ' . $nama;
            }
        }
    }

    expect($tidakTerdaftar)->toBe([]);
});

it('membuka halaman Kelola Data untuk Admin dan Penanggung Jawab', function (int $role, string $url, string $awalanRute) {
    $kategori = Category::create(['name' => 'Kependudukan']);
    $subjek = Subject::create(['category_id' => $kategori->id, 'name' => 'Penduduk']);
    $indikator = Indicator::create([
        'subject_id' => $subjek->id,
        'name' => 'Jumlah Penduduk',
        'unit' => 'Jiwa',
        'data' => ['headers' => [['value' => 'Kecamatan']], 'rows' => [[['value' => 'Siantar Barat']]]],
    ]);
    $pengguna = User::create([
        'name' => 'pengelola',
        'email' => 'pengelola@uji.test',
        'password' => bcrypt('rahasia123'),
        'role_id' => $role,
    ]);

    $this->actingAs($pengguna)->get($url)
        ->assertOk()
        ->assertSee('Jumlah Penduduk')
        // Pencarian/filter dan tombol hapus indikator mengarah ke rute milik role tersebut.
        ->assertSee(route("{$awalanRute}.keloladata"), false)
        ->assertSee(route("{$awalanRute}.indicators.destroy", $indikator), false);
})->with([
    'Admin' => [1, '/admin/data', 'admin'],
    'Penanggung Jawab' => [3, '/penanggungjawab/data', 'penanggungjawab'],
]);
