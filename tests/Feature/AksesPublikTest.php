<?php

// Uji akses publik: masyarakat (tamu) melihat dashboard, narasi yang sudah diterbitkan, dan data tanpa login,
// sedangkan pembuatan narasi tetap khusus Admin/Penanggung Jawab yang login.
//
// AMAN dijalankan kapan saja: SQLite di memori (phpunit.xml) dan semua panggilan HTTP ke luar (WebAPI BPS,
// layanan AI) dipalsukan; tes gagal bila ada permintaan keluar yang tidak diharapkan.

use App\Models\Category;
use App\Models\Indicator;
use App\Models\Narrative;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('Tes akses publik hanya boleh berjalan di SQLite :memory: (lihat phpunit.xml).');
    }
    $this->artisan('migrate');
    $this->withoutVite();

    Http::preventStrayRequests();
    Http::fake();
    // Kunci API terisi agar indikator BPS yang belum berdata BENAR-BENAR akan mengambil dari API bila dipicu.
    config(['services.bps.key' => 'kunci-uji-rahasia-123', 'services.bps.domain' => '1273']);

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
    // Indikator tabel dinamis BPS yang belum berdata: bila dibuka pengguna login, datanya diambil dari API.
    $this->indikatorBps = Indicator::create([
        'subject_id' => $this->subjek->id,
        'name' => 'Penduduk Menurut Kelompok Umur',
        'bps_source' => 'dinamis',
        'bps_table_id' => '31',
    ]);

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

    $this->urlDashboard = fn (Indicator $i) => '/pengguna/dashboard?' . http_build_query([
        'category_id' => $this->kategori->id, 'subject_id' => $this->subjek->id, 'indicator_id' => $i->id,
    ]);
});

it('menampilkan dashboard dan narasi yang sudah diterbitkan kepada tamu tanpa login', function () {
    Narrative::create(['indicator_id' => $this->indikator->id, 'user_id' => $this->pj->id, 'content' => 'Jumlah penduduk meningkat pada 2024.']);
    $id = $this->indikator->id;

    $respons = $this->get(($this->urlDashboard)($this->indikator))->assertOk();
    $respons->assertSee('Jumlah Penduduk Menurut Kecamatan')
        ->assertSee('Jumlah penduduk meningkat pada 2024.')
        ->assertSee('Diterbitkan petugas BPS Kota Pematangsiantar')
        ->assertSee('tidak mengikuti filter grafik')
        ->assertSee('Login Petugas')
        ->assertSee(route('login'), false)
        // Tidak ada alat petugas: tombol Kelola Narasi, editor, rute generate/simpan, Logout, Pengaturan.
        ->assertDontSee('Kelola Narasi')
        ->assertDontSee('id="editor-' . $id . '"', false)
        ->assertDontSee('generate-narrative')
        ->assertDontSee('save-narrative')
        ->assertDontSee('Logout')
        ->assertDontSee(route('pengguna.pengaturan'), false);
    expect(auth()->check())->toBeFalse();

    // Ringkasan dashboard utama dan halaman Tentang Kami juga terbuka.
    $this->get('/pengguna/dashboard')->assertOk()->assertSee('Dashboard Utama')->assertSee('Pilih Kategori Data');
    $this->get('/pengguna/tentang-kami')->assertOk();
    Http::assertNothingSent();
});

it('melayani filter grafik dashboard untuk tamu dan menolak input yang tidak sesuai', function () {
    $this->getJson('/pengguna/dashboard/get-filtered-data?' . http_build_query([
        'indicator_id' => $this->indikator->id, 'filters' => ['Kecamatan' => 'Siantar Barat'],
    ]))->assertOk()->assertJsonPath('unit', 'Jiwa')->assertJsonStructure(['data', 'temporal_column']);

    $this->getJson('/pengguna/dashboard/get-filtered-data?indicator_id=' . $this->indikator->id . '&filters=teks')->assertStatus(422);
    // Nama kolom bertitik tetap dipakai sebagai filter; nilai bersarang diabaikan (bukan galat server).
    $this->getJson('/pengguna/dashboard/get-filtered-data?' . http_build_query([
        'indicator_id' => $this->indikator->id, 'filters' => ['Kab./Kota' => 'Siantar'],
    ]))->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('active_filters', ['Kab./Kota' => 'Siantar']);
    $this->getJson('/pengguna/dashboard/get-filtered-data?' . http_build_query([
        'indicator_id' => $this->indikator->id, 'filters' => ['Kecamatan' => ['x' => 'y']],
    ]))->assertOk()->assertJsonCount(4, 'data');
    $this->getJson('/pengguna/dashboard/get-filtered-data?indicator_id=' . $this->indikator->id . '&filters=')
        ->assertOk()->assertJsonCount(4, 'data');
    $this->getJson('/pengguna/dashboard/get-filtered-data')->assertStatus(422);
    $this->getJson('/pengguna/dashboard/get-filtered-data?indicator_id=999999')->assertNotFound();
});

it('memakai kolom Tahun sebagai tahun terpilih walau kolom pertama tabel berupa waktu lain', function () {
    $sel = fn ($nilai) => ['value' => $nilai, 'colspan' => 1, 'rowspan' => 1, 'hidden' => false];
    $bulanan = Indicator::create([
        'subject_id' => $this->subjek->id, 'name' => 'Penumpang Menurut Bulan', 'unit' => 'Orang',
        'data' => ['headers' => [$sel('Bulan'), $sel('2023'), $sel('2024')],
            'rows' => [[$sel('Januari'), $sel('10'), $sel('12')], [$sel('Februari'), $sel('11'), $sel('13')]]],
    ]);

    $this->getJson('/pengguna/dashboard/get-filtered-data?' . http_build_query([
        'indicator_id' => $bulanan->id, 'filters' => ['Bulan' => '', 'Tahun' => '2024'],
    ]))->assertOk()->assertJsonPath('temporal_column', 'Tahun')->assertJsonPath('selected_year', '2024')
        ->assertJsonCount(4, 'data'); // kolom waktu tidak difilter di backend (ditangani JS)
});

it('tidak galat bila parameter dashboard publik bukan angka', function () {
    $this->get('/pengguna/dashboard?category_id[]=1&subject_id=abc&indicator_id[]=' . $this->indikator->id)
        ->assertOk()->assertSee('Dashboard Utama');
    $this->get('/pengguna/dashboard?category_id=' . $this->kategori->id . '&subject_id=' . $this->subjek->id . '&indicator_id=-5')
        ->assertOk()->assertSee('Dashboard: Kependudukan');
});

it('membuka Lihat Data dan unduhan untuk tamu tanpa login', function () {
    $this->get('/pengguna/lihatdata')->assertOk()->assertSee('Jumlah Penduduk Menurut Kecamatan');
    $this->get("/pengguna/lihatdata/{$this->indikator->id}")->assertOk()->assertSee('Siantar Barat');
    $this->get("/pengguna/indicators/{$this->indikator->id}/export/excel")->assertOk()->assertDownload();
    $this->get("/pengguna/indicators/{$this->indikator->id}/export/pdf")->assertOk()->assertDownload();
    Http::assertNothingSent();
});

it('hanya memicu pengambilan pertama WebAPI BPS saat tamu membuka indikator yang belum berdata', function () {
    // Belum berdata: kunjungan tamu pertama mengambil datanya (di sini API palsu gagal, jadi tetap kosong).
    $this->get(($this->urlDashboard)($this->indikatorBps))->assertOk()->assertSee('Penduduk Menurut Kelompok Umur');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'webapi.bps.go.id'));

    // Setelah gagal ada jeda, jadi kunjungan berikutnya tidak memanggil API lagi.
    $jumlah = count(Http::recorded());
    $this->get("/pengguna/lihatdata/{$this->indikatorBps->id}")->assertOk()
        ->assertSee('Datanya sedang disiapkan dari WebAPI BPS');
    $this->get("/pengguna/indicators/{$this->indikatorBps->id}/export/excel")->assertOk();
    $this->get("/pengguna/indicators/{$this->indikatorBps->id}/export/pdf")->assertOk();
    expect(count(Http::recorded()))->toBe($jumlah);
});

it('tidak menyegarkan data BPS yang sudah ada untuk tamu walau BPS_SEGAR_MENIT terisi', function () {
    config(['services.bps.segar_menit' => 60]);
    $lama = Indicator::create([
        'subject_id' => $this->subjek->id, 'name' => 'Penduduk Menurut Agama', 'bps_source' => 'dinamis',
        'bps_table_id' => '32', 'data' => $this->indikator->data, 'bps_synced_at' => now()->subDay(),
    ]);

    $this->get(($this->urlDashboard)($lama))->assertOk()->assertSee('Siantar Barat');
    $this->get("/pengguna/lihatdata/{$lama->id}")->assertOk();
    $this->get("/pengguna/indicators/{$lama->id}/export/excel")->assertOk();
    Http::assertNothingSent();

    // Pengguna yang login tetap menyegarkan seperti sebelumnya.
    $this->actingAs($this->biasa)->get("/pengguna/lihatdata/{$lama->id}")->assertOk();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'webapi.bps.go.id'));
});

it('menolak tamu membuat atau menyimpan narasi', function () {
    $id = $this->indikator->id;
    foreach (['/admin', '/penanggungjawab'] as $prefix) {
        $this->postJson("{$prefix}/dashboard/generate-narrative", ['indicator_id' => $id])->assertUnauthorized();
        $this->postJson("{$prefix}/dashboard/save-narrative", ['indicator_id' => $id, 'narrative' => 'Narasi palsu'])->assertUnauthorized();
        $this->post("{$prefix}/dashboard/generate-narrative", ['indicator_id' => $id])->assertRedirect('/login');
    }
    expect(Narrative::count())->toBe(0);
    Http::assertNothingSent();
});

it('menolak role selain Admin/PJ menyimpan narasi sebelum memvalidasi isinya', function () {
    // Cek hak akses lebih dulu: ID indikator yang tidak ada pun dijawab 403, bukan 422.
    $this->actingAs($this->biasa)->postJson('/penanggungjawab/dashboard/save-narrative', ['indicator_id' => 999999])
        ->assertForbidden();
    $this->actingAs($this->pimpinan)->postJson('/admin/dashboard/save-narrative', [])->assertForbidden();
});

it('tetap meminta login untuk halaman akun dan area petugas', function () {
    $this->get('/pengguna/pengaturan')->assertRedirect('/login');
    $this->get('/penanggungjawab/dashboard')->assertRedirect('/login');
    $this->get('/admin/dashboard')->assertRedirect('/login');
    $this->get('/penanggungjawab/data')->assertRedirect('/login');
});

it('mengarahkan Admin dan PJ yang login ke dashboard miliknya, sedangkan Pimpinan memakai dashboard publik', function () {
    $this->actingAs($this->pj)->get('/pengguna/dashboard')->assertRedirect('/penanggungjawab/dashboard');
    $this->actingAs($this->admin)->get('/pengguna/lihatdata')->assertRedirect('/admin/lihatdata');
    $this->actingAs($this->pimpinan)->get('/pengguna/dashboard')->assertOk()->assertSee('Logout')->assertDontSee('Login Petugas');
});

it('menandai narasi yang datanya sudah berubah setelah narasi disimpan', function () {
    $id = $this->indikator->id;
    $urlPj = '/penanggungjawab/dashboard?' . http_build_query([
        'category_id' => $this->kategori->id, 'subject_id' => $this->subjek->id, 'indicator_id' => $id,
    ]);

    $this->actingAs($this->pj)->postJson('/penanggungjawab/dashboard/save-narrative', ['indicator_id' => $id, 'narrative' => 'Penduduk naik.'])
        ->assertOk()->assertJsonPath('status', 'success')->assertJsonStructure(['diperbarui']);
    expect(Narrative::sole()->data_hash)->toBe(Narrative::sidikData($this->indikator->fresh()->data));

    auth()->logout();
    $this->get(($this->urlDashboard)($this->indikator))->assertOk()->assertSee('Penduduk naik.')
        ->assertDontSee('id="narrative-basi-' . $id . '"', false);

    // Data diperbarui (mis. sinkron BPS malam): pembaca diberi tahu, PJ diminta memperbarui narasi.
    $data = $this->indikator->data;
    $data['rows'][0][2]['value'] = '125';
    $this->indikator->update(['data' => $data]);

    $this->get(($this->urlDashboard)($this->indikator))->assertOk()
        ->assertSee('id="narrative-basi-' . $id . '"', false)
        ->assertSee('sudah diperbarui setelah narasi ditulis');
    $this->actingAs($this->pj)->get($urlPj)->assertOk()->assertSee('sudah berubah sejak narasi disimpan');

    // Disimpan ulang: tanda hilang.
    $this->actingAs($this->pj)->postJson('/penanggungjawab/dashboard/save-narrative', ['indicator_id' => $id, 'narrative' => 'Penduduk naik lagi.'])->assertOk();
    $this->actingAs($this->pj)->get($urlPj)->assertOk()->assertDontSee('id="narrative-basi-' . $id . '"', false);

    // Mengubah nama/satuan saja tidak dianggap perubahan data.
    $this->indikator->update(['unit' => 'Orang']);
    $this->actingAs($this->pj)->get($urlPj)->assertOk()->assertDontSee('id="narrative-basi-' . $id . '"', false);
});

it('tidak menandai narasi lama yang disimpan sebelum ada sidik data', function () {
    Narrative::create(['indicator_id' => $this->indikator->id, 'content' => 'Narasi lama.']);

    $this->get(($this->urlDashboard)($this->indikator))->assertOk()->assertSee('Narasi lama.')
        ->assertDontSee('id="narrative-basi-' . $this->indikator->id . '"', false);
});

it('menutup pendaftaran akun mandiri dan mengarahkan masyarakat ke dashboard publik', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'Warga', 'email' => 'warga@uji.test', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
        ->assertNotFound();
    expect(User::where('email', 'warga@uji.test')->exists())->toBeFalse();

    $this->get('/login')->assertOk()->assertDontSee('Daftar gratis')->assertSee('Lihat dashboard tanpa login')
        ->assertSee(route('pengguna.dashboard'), false);
    $this->get('/')->assertOk()->assertSee('Lihat Dashboard')->assertSee('Login Petugas')
        ->assertSee(route('pengguna.dashboard'), false)->assertSee('tanpa perlu login atau mendaftar');
});

it('hanya mengizinkan login Google untuk email yang sudah terdaftar sebagai petugas', function () {
    $akunGoogle = fn (string $email) => (new \Laravel\Socialite\Two\User())->map(['id' => 'google-123', 'name' => 'Nama Google', 'email' => $email]);
    $penyedia = Mockery::mock();
    $penyedia->shouldReceive('setHttpClient')->andReturnSelf();
    $penyedia->shouldReceive('user')->andReturn($akunGoogle('warga@gmail.com'), $akunGoogle('pj@uji.test'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($penyedia);

    $this->get('/auth/google/callback')->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Email Google ini belum terdaftar sebagai akun petugas. Masyarakat dapat langsung melihat dashboard tanpa login.']);
    expect(User::where('email', 'warga@gmail.com')->exists())->toBeFalse();
    $this->assertGuest();

    $this->get('/auth/google/callback')->assertRedirect('/penanggungjawab/dashboard');
    $this->assertAuthenticatedAs($this->pj);
    expect($this->pj->fresh()->google_id)->toBe('google-123');
});

it('membatasi unduhan dan pembuatan narasi per menit', function () {
    $url = "/pengguna/indicators/{$this->indikator->id}/export/excel";
    foreach (range(1, 10) as $_) {
        $this->get($url)->assertOk();
    }
    $this->get($url)->assertStatus(429);

    // Tanpa indicator_id dijawab 400 sebelum memanggil layanan AI, jadi aman dicoba berulang di sini.
    foreach (range(1, 5) as $_) {
        $this->actingAs($this->pj)->postJson('/penanggungjawab/dashboard/generate-narrative', [])->assertStatus(400);
    }
    $this->actingAs($this->pj)->postJson('/penanggungjawab/dashboard/generate-narrative', [])->assertStatus(429)
        ->assertJsonPath('error', 'Batas 5 permintaan generate narasi per menit tercapai. Tunggu sekitar 1 menit, lalu coba lagi.');
    Http::assertNothingSent();
});

it('hanya mengizinkan Admin membersihkan cache server', function () {
    $this->get('/clear-cache-server')->assertRedirect('/login');
    $this->actingAs($this->pj)->get('/clear-cache-server')->assertForbidden();

    Artisan::shouldReceive('call')->once()->with('optimize:clear')->andReturn(0);
    $this->actingAs($this->admin)->get('/clear-cache-server')->assertOk()->assertSee('Berhasil membersihkan cache server');
});
