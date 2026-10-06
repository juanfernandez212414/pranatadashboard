<?php

// Uji hak akses per role (matriks hak akses pada evaluasi Security).
//
// AMAN dijalankan kapan saja:
// - database memakai SQLite di memori (phpunit.xml); tes berhenti bila koneksinya bukan itu,
// - semua panggilan HTTP ke luar (layanan AI / Qdrant) dipalsukan,
// - folder storage dialihkan ke folder sementara, sehingga PDF, log, dan basis pengetahuan
//   asli tidak tersentuh walaupun ada pemeriksaan hak akses yang gagal.

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('Tes hak akses hanya boleh berjalan di SQLite :memory: (lihat phpunit.xml).');
    }
    $this->artisan('migrate');

    Http::preventStrayRequests();
    Http::fake();

    $storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pranata-uji-hak-akses';
    @mkdir($storage . '/app/public/dokumen_bps', 0777, true);
    $this->app->useStoragePath($storage);

    $this->withoutVite();

    $buat = fn (int $role, string $nama) => User::create([
        'name' => $nama,
        'email' => "{$nama}@uji.test",
        'password' => bcrypt('rahasia123'),
        'role_id' => $role,
    ]);
    $this->admin = $buat(1, 'admin');
    $this->pimpinan = $buat(2, 'pimpinan');
    $this->pj = $buat(3, 'pj');
    $this->biasa = $buat(4, 'biasa');
});

it('mengarahkan tamu yang belum login ke halaman login', function () {
    $this->get('/admin/pengguna')->assertRedirect('/login');
});

it('menolak selain Admin mengelola pengguna', function (string $role) {
    $user = $this->{$role};

    $this->actingAs($user)->get('/admin/pengguna')->assertForbidden();
    $this->actingAs($user)->post('/admin/pengguna', [
        'name' => 'Penyusup', 'email' => 'penyusup@uji.test', 'role_id' => 1,
        'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
    ])->assertForbidden();
    $this->actingAs($user)->patch("/admin/pengguna/{$this->admin->id}/role", ['role_id' => 4])->assertForbidden();
    $this->actingAs($user)->delete("/admin/pengguna/{$this->admin->id}")->assertForbidden();

    expect(User::where('email', 'penyusup@uji.test')->exists())->toBeFalse();
    expect((int) User::find($this->admin->id)->role_id)->toBe(1);
})->with(['pimpinan', 'pj', 'biasa']);

it('mengizinkan Admin membuka manajemen pengguna', function () {
    $this->actingAs($this->admin)->get('/admin/pengguna')->assertOk();
});

it('menolak Pimpinan dan Pengguna Biasa mengubah basis pengetahuan', function (string $role, string $prefix) {
    $user = $this->{$role};

    $this->actingAs($user)->get("{$prefix}/pengetahuan")->assertForbidden();
    $this->actingAs($user)->post("{$prefix}/pengetahuan/upload")->assertForbidden();
    $this->actingAs($user)->post("{$prefix}/pengetahuan/ingest")->assertForbidden();
    $this->actingAs($user)->delete("{$prefix}/pengetahuan/delete-by-file", ['filename' => 'contoh.pdf'])->assertForbidden();
    $this->actingAs($user)->delete("{$prefix}/pengetahuan/delete-all")->assertForbidden();

    Http::assertNothingSent();
})->with(['pimpinan', 'biasa'])->with(['/admin', '/penanggungjawab']);

it('mengizinkan Admin dan Penanggung Jawab sampai ke validasi unggah pengetahuan', function (string $role, string $prefix) {
    // Tanpa berkas: lolos pemeriksaan hak akses, lalu ditolak validasi (bukan 403).
    $this->actingAs($this->{$role})->post("{$prefix}/pengetahuan/upload")->assertSessionHasErrors('dokumen');
})->with([['admin', '/admin'], ['pj', '/penanggungjawab']]);

it('hanya mengizinkan Admin dan Penanggung Jawab membuat narasi', function () {
    foreach (['pimpinan', 'biasa'] as $role) {
        foreach (['/admin', '/penanggungjawab'] as $prefix) {
            $this->actingAs($this->{$role})->postJson("{$prefix}/dashboard/generate-narrative", [])->assertForbidden();
        }
    }
    // Admin & PJ lolos pemeriksaan hak akses; tanpa indicator_id dijawab 400 sebelum memanggil layanan AI.
    $this->actingAs($this->admin)->postJson('/admin/dashboard/generate-narrative', [])->assertStatus(400);
    $this->actingAs($this->pj)->postJson('/penanggungjawab/dashboard/generate-narrative', [])->assertStatus(400);
    Http::assertNothingSent();
});

it('menolak Pimpinan dan Pengguna Biasa membuka halaman model AI', function (string $role) {
    $user = $this->{$role};
    $this->actingAs($user)->get('/admin/model')->assertForbidden();
    $this->actingAs($user)->get('/penanggungjawab/model')->assertForbidden();
    $this->actingAs($user)->getJson('/admin/model/check-status')->assertForbidden();
    $this->actingAs($user)->patch('/admin/model', ['active_model' => 'gemini-3-flash-preview'])->assertForbidden();
    Http::assertNothingSent();
})->with(['pimpinan', 'biasa']);

it('mengizinkan Admin dan Penanggung Jawab membuka halaman model AI', function () {
    $this->actingAs($this->admin)->get('/admin/model')->assertOk();
    $this->actingAs($this->pj)->get('/penanggungjawab/model')->assertOk();
    $this->actingAs($this->pj)->getJson('/penanggungjawab/model/check-status')->assertOk();
});

it('menolak Pengguna Biasa membuka halaman kelola data', function () {
    $this->actingAs($this->biasa)->get('/penanggungjawab/data')->assertForbidden();
});

it('membuka Tentang Kami di halaman milik role masing-masing', function () {
    $this->actingAs($this->admin)->get('/admin/tentang-kami')->assertOk();
    $this->actingAs($this->pj)->get('/penanggungjawab/tentang-kami')->assertOk();
    $this->actingAs($this->pimpinan)->get('/pengguna/tentang-kami')->assertOk();
    $this->actingAs($this->biasa)->get('/pengguna/tentang-kami')->assertOk();

    // Halaman role lain diarahkan ke halaman milik role pengguna (middleware area.role).
    $this->actingAs($this->biasa)->get('/admin/tentang-kami')->assertRedirect('/pengguna/tentang-kami');
    $this->actingAs($this->biasa)->get('/penanggungjawab/tentang-kami')->assertRedirect('/pengguna/tentang-kami');
});

it('mengarahkan halaman bersama ke area milik role pengguna', function () {
    $this->actingAs($this->biasa)->get('/penanggungjawab/dashboard?category_id=1')->assertRedirect('/pengguna/dashboard?category_id=1');
    $this->actingAs($this->biasa)->get('/admin/dashboard')->assertRedirect('/pengguna/dashboard');
    $this->actingAs($this->pimpinan)->get('/penanggungjawab/lihatdata/5')->assertRedirect('/pengguna/lihatdata/5');
    $this->actingAs($this->pj)->get('/pengguna/dashboard')->assertRedirect('/penanggungjawab/dashboard');
    $this->actingAs($this->pj)->get('/admin/model')->assertRedirect('/penanggungjawab/model');
    $this->actingAs($this->admin)->get('/penanggungjawab/pengaturan')->assertRedirect('/admin/pengaturan');
});

it('meneruskan alamat lama Admin ke alamat baru berawalan /admin', function () {
    $this->actingAs($this->admin)->get('/dashboard?category_id=1')->assertRedirect('/admin/dashboard?category_id=1');
    $this->actingAs($this->admin)->get('/lihatdata')->assertRedirect('/admin/lihatdata');
    $this->actingAs($this->admin)->get('/admin/lihatdata')->assertOk();
});

it('menampilkan halaman Akses Ditolak dengan tampilan dan pesan yang sama', function () {
    // Semua halaman yang ditolak memakai tampilan DAN pesan 403 yang sama, dengan tombol ke dashboard
    // role pengguna. Pesan spesifik dari abort(403, '...') tidak ikut tampil di halaman.
    foreach (['/admin/pengguna', '/penanggungjawab/data', '/admin/model', '/admin/pengetahuan'] as $url) {
        $this->actingAs($this->biasa)->get($url)->assertForbidden()
            ->assertSee('Akses Ditolak')
            ->assertSee('Anda tidak memiliki hak akses untuk membuka halaman ini.')
            ->assertDontSee('Hanya Admin')
            ->assertSee(route('pengguna.dashboard'), false);
    }
    $this->actingAs($this->pj)->get('/admin/pengguna')->assertForbidden()
        ->assertSee('Anda tidak memiliki hak akses untuk membuka halaman ini.')
        ->assertSee(route('penanggungjawab.dashboard'), false);

    // Pesan pop-up (respons JSON) tidak memakai halaman ini dan tetap memakai pesan lamanya.
    $this->actingAs($this->biasa)->postJson('/admin/dashboard/generate-narrative', [])
        ->assertForbidden()->assertJson(['error' => 'Akses ditolak. Hanya Admin dan Penanggung Jawab yang dapat membuat narasi.']);
    $this->actingAs($this->biasa)->getJson('/admin/model/check-status')
        ->assertForbidden()->assertJson(['message' => 'Akses ditolak.']);
});

it('tetap menolak halaman yang tidak punya padanan di area pengguna', function () {
    // Tidak ada Kelola Data untuk Pengguna Biasa dan tidak ada manajemen pengguna untuk PJ:
    // middleware tidak mengarahkan, controller menolak dengan 403.
    $this->actingAs($this->biasa)->get('/penanggungjawab/data')->assertForbidden();
    $this->actingAs($this->pj)->get('/admin/pengguna')->assertForbidden();
});
