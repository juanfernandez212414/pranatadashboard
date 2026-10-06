<?php

// Uji tampilan dashboard ketiga role: halaman tampil, elemen yang dipakai JavaScript dashboard
// (filter AJAX, kartu visualisasi, editor narasi) tetap ada, dan editor hanya untuk Admin & PJ.
// Database memakai SQLite di memori (phpunit.xml).

use App\Models\Category;
use App\Models\Indicator;
use App\Models\Narrative;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('Tes dashboard hanya boleh berjalan di SQLite :memory: (lihat phpunit.xml).');
    }
    $this->artisan('migrate');
    $this->withoutVite();

    $sel = fn ($nilai) => ['value' => $nilai, 'colspan' => 1, 'rowspan' => 1, 'hidden' => false];

    $this->kategori = Category::create(['name' => 'Kependudukan']);
    $this->subjek = Subject::create(['category_id' => $this->kategori->id, 'name' => 'Penduduk']);
    $this->indikator = Indicator::create([
        'subject_id' => $this->subjek->id,
        'name' => 'Jumlah Penduduk Menurut Kecamatan',
        'unit' => 'Jiwa',
        'data' => [
            'headers' => [$sel('Kecamatan'), $sel('2023'), $sel('2024')],
            'rows' => [
                [$sel('Siantar Barat'), $sel('100'), $sel('120')],
                [$sel('Siantar Utara'), $sel('200'), $sel('210')],
            ],
        ],
    ]);
    Narrative::create(['indicator_id' => $this->indikator->id, 'content' => 'Jumlah penduduk meningkat pada 2024.']);

    $buat = fn (int $role, string $nama) => User::create([
        'name' => $nama,
        'email' => "{$nama}@uji.test",
        'password' => bcrypt('rahasia123'),
        'role_id' => $role,
    ]);
    $this->pengguna = [1 => $buat(1, 'admin'), 3 => $buat(3, 'pj'), 4 => $buat(4, 'biasa')];
});

dataset('dashboard per role', [
    'Admin' => [1, '/admin/dashboard', true],
    'Penanggung Jawab' => [3, '/penanggungjawab/dashboard', true],
    'Pengguna Biasa' => [4, '/pengguna/dashboard', false],
]);

it('menampilkan visualisasi dan narasi indikator terpilih', function (int $role, string $url, bool $bolehKelola) {
    $id = $this->indikator->id;
    $respons = $this->actingAs($this->pengguna[$role])->get($url . '?' . http_build_query([
        'category_id' => $this->kategori->id,
        'subject_id' => $this->subjek->id,
        'indicator_id' => $id,
    ]));

    $respons->assertOk()
        ->assertSee('Kependudukan')
        ->assertSee('Jumlah Penduduk Menurut Kecamatan')
        ->assertSee('Subjek: Penduduk')
        ->assertSee('Jumlah penduduk meningkat pada 2024.');

    // Elemen yang dicari JavaScript dashboard lewat ID.
    foreach (['dashboard-ajax-container', 'filterForm', 'subject_id', 'indicator_id', "vis-card-{$id}",
        "narrative-view-{$id}", "narrative-text-{$id}", "vis-placeholder-{$id}"] as $idElemen) {
        $respons->assertSee('id="' . $idElemen . '"', false);
    }

    foreach (["narrative-editor-container-{$id}", "btn-generate-{$id}", "loading-msg-{$id}", "editor-{$id}", "btn-save-{$id}"] as $idEditor) {
        $bolehKelola
            ? $respons->assertSee('id="' . $idEditor . '"', false)
            : $respons->assertDontSee('id="' . $idEditor . '"', false);
    }
})->with('dashboard per role');

it('menampilkan ringkasan dan petunjuk memilih kategori di dashboard utama', function (int $role, string $url) {
    $this->actingAs($this->pengguna[$role])->get($url)
        ->assertOk()
        ->assertSee('Dashboard Utama')
        ->assertSee('Total Kategori')
        ->assertSee('Total Indikator')
        ->assertSee('Pilih Kategori Data');
})->with('dashboard per role');
