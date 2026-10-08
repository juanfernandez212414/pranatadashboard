<?php

// Uji fitur Data API BPS (App\Services\Bps\*, DataBpsController).
//
// AMAN dijalankan kapan saja:
// - tidak ada permintaan sungguhan ke WebAPI BPS: semua dibalas fixture di tests/Fixtures/bps
//   (cuplikan respons asli domain 1273, Oktober 2026) lewat Http::fake,
// - database memakai SQLite di memori (phpunit.xml),
// - folder storage dialihkan ke folder sementara, jadi PDF basis pengetahuan asli tidak tersentuh.

use App\Http\Controllers\DashboardController;
use App\Models\Category;
use App\Models\Indicator;
use App\Models\Subject;
use App\Models\User;
use App\Services\Bps\BpsApiClient;
use App\Services\Bps\BpsApiException;
use App\Services\Bps\KonverterTabelBps;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

// fixtureBps(), dataVar31(), dan palsukanBps() ada di tests/BantuanBps.php (dipakai juga SinkronisasiBpsTest).

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('Tes Data API BPS hanya boleh berjalan di SQLite :memory: (lihat phpunit.xml).');
    }
    $this->artisan('migrate');

    Http::preventStrayRequests();
    config(['services.bps.key' => 'kunci-uji-rahasia-123', 'services.bps.domain' => '1273']);

    $this->storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pranata-uji-data-bps-' . uniqid();
    $this->folderPdf = $this->storage . '/app/public/dokumen_bps';
    mkdir($this->folderPdf, 0777, true);
    $this->app->useStoragePath($this->storage);

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

    $kategori = Category::create(['name' => 'Sosial dan Kependudukan']);
    $this->subjek = Subject::create(['category_id' => $kategori->id, 'name' => 'Kependudukan']);
});

afterEach(function () {
    File::deleteDirectory($this->storage);
});

// ===========================================
// --- KONVERSI TABEL ---
// ===========================================

it('mengubah tabel dinamis bertingkat (kecamatan × jenis kelamin) ke format indikator', function () {
    $m = KonverterTabelBps::dariDinamis(fixtureBps('data_32_2024.json'));

    expect(array_column($m['headers'], 'value'))->toBe(['Kecamatan', 'Laki-laki', 'Perempuan', 'Jumlah'])
        ->and($m['headers'][0]['rowspan'])->toBe(2)
        ->and($m['rows'][0][0]['hidden'])->toBeTrue()
        ->and(array_column(array_slice($m['rows'][0], 1), 'value'))->toBe(['2024', '2024', '2024'])
        ->and(array_column($m['rows'][1], 'value'))->toBe(['SIANTAR MARIHAT', '10668', '10942', '21610'])
        ->and(end($m['rows'])[0]['value'])->toBe('PEMATANGSIANTAR');

    // Grid persegi: ekspor Excel memakai indeks sel sebagai nomor kolom.
    expect(collect($m['rows'])->map(fn ($baris) => count($baris))->unique()->values()->all())->toBe([4]);
});

it('menaruh bulan di bawah tahun untuk tabel bulanan (inflasi)', function () {
    $m = KonverterTabelBps::dariDinamis(fixtureBps('data_1_2024.json'));

    expect($m['headers'][1])->toMatchArray(['value' => '2024', 'colspan' => 13])
        ->and($m['rows'][0][1]['value'])->toBe('Januari')
        ->and($m['rows'][0][13]['value'])->toBe('Tahunan')
        ->and($m['rows'][1][0]['value'])->toBe('Kota Pematangsiantar')
        ->and($m['rows'][1][1]['value'])->toBe('0.88');
});

it('menormalkan angka berformat BPS', function (string $masuk, string $keluar) {
    expect(KonverterTabelBps::normalisasiAngka($masuk))->toBe($keluar);
})->with([
    ['122 098', '122098'],
    ['1.234,56', '1234.56'],
    ['7,83', '7.83'],
    ['2010*)', '2010'],
    ['–', '-'],
    ['...', '-'],
    ['-1,25', '-1.25'],
    ['Siantar Barat', 'Siantar Barat'],
]);

it('menghasilkan data yang langsung bisa divisualisasikan dashboard', function () {
    $indikator = new Indicator([
        'name' => 'Penduduk per Kecamatan menurut Jenis Kelamin',
        'data' => KonverterTabelBps::dariDinamis(fixtureBps('data_32_2024.json')),
    ]);

    $hasil = (new ReflectionMethod(DashboardController::class, 'parseIndicatorData'))
        ->invoke(new DashboardController(), $indikator);

    expect($hasil)->not->toBeNull()
        ->and($hasil['available_types'])->not->toBeEmpty()
        ->and($hasil['long_form'][0])->toMatchArray(['Kecamatan' => 'SIANTAR MARIHAT', 'Tahun' => '2024', 'Kategori' => 'Laki-laki'])
        ->and((float) $hasil['long_form'][0]['Nilai'])->toBe(10668.0);
});

// ===========================================
// --- KLIEN WEBAPI BPS ---
// ===========================================

it('memecah permintaan tabel dinamis per 2 tahun lalu menggabungkan hasilnya', function () {
    palsukanBps();

    $data = app(BpsApiClient::class)->dataDinamis(31, [124, 123, 120]);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'th=124%3B123') && str_contains($r->url(), 'key=kunci-uji-rahasia-123'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'th=120'));
    expect(array_column(KonverterTabelBps::dariDinamis($data)['headers'], 'value'))->toBe(['Kecamatan', '2020', '2023', '2024'])
        ->and(KonverterTabelBps::dariDinamis($data)['rows'][0][1]['value'])->toBe('20933');
});

it('menyimpan respons API di cache sehingga permintaan yang sama tidak diulang', function () {
    palsukanBps();
    $bps = app(BpsApiClient::class);

    $bps->daftarPublikasi(1);
    $bps->daftarPublikasi(1);

    Http::assertSentCount(1);
});

it('tidak membocorkan kunci API pada pesan galat koneksi', function () {
    Sleep::fake();
    Http::fake(fn () => Http::failedConnection('cURL error 28: timeout for https://webapi.bps.go.id/v1/api/list?model=var&key=kunci-uji-rahasia-123'));

    expect(fn () => app(BpsApiClient::class)->daftar('var'))
        ->toThrow(function (BpsApiException $e) {
            expect($e->getMessage())->not->toContain('kunci-uji-rahasia-123')->toContain('***');
        });
});

it('menampilkan pesan jelas bila kunci API ditolak BPS', function () {
    Http::fake(['*' => Http::response(['status' => 'Error', 'message' => 'You are not Allowed to take this action. Please re-check your key'])]);

    $this->actingAs($this->admin)->get('/admin/data-bps')
        ->assertOk()
        ->assertSee('Kunci API BPS ditolak')
        ->assertDontSee('kunci-uji-rahasia-123');
});

it('meminta BPS_API_KEY diisi tanpa menghubungi API bila kuncinya kosong', function () {
    Http::fake();
    config(['services.bps.key' => null]);

    $this->actingAs($this->admin)->get('/admin/data-bps')->assertOk()->assertSee('BPS_API_KEY');

    Http::assertNothingSent();
});

// ===========================================
// --- HALAMAN, HAK AKSES & SIMPAN INDIKATOR ---
// ===========================================

it('hanya Admin dan Penanggung Jawab yang boleh memakai Data API BPS', function () {
    palsukanBps();

    $this->actingAs($this->admin)->get('/admin/data-bps')->assertOk()->assertSee('Penduduk per kecamatan');
    $this->actingAs($this->pj)->get('/penanggungjawab/data-bps')->assertOk();
    $this->actingAs($this->pj)->get('/admin/data-bps')->assertRedirect('/penanggungjawab/data-bps');

    foreach (['biasa', 'pimpinan'] as $role) {
        $this->actingAs($this->{$role})->get('/admin/data-bps')->assertForbidden();
        $this->actingAs($this->{$role})->get('/admin/data-bps/dinamis/31/pilihan')->assertForbidden();
        $this->actingAs($this->{$role})->get('/admin/data-bps/dinamis/hasil?data[0][var]=31')->assertForbidden();
        $this->actingAs($this->{$role})->get('/penanggungjawab/data-bps/dinamis/hasil?data[0][var]=31')->assertForbidden();
        $this->actingAs($this->{$role})->post('/admin/data-bps/tabel', [
            'var' => 31, 'subject_id' => $this->subjek->id, 'name' => 'Penyusup',
        ])->assertForbidden();
        $this->actingAs($this->{$role})->post('/admin/data-bps/publikasi/d16eaeb2fff0805a540b5047')->assertForbidden();
    }

    // Indikator yang ada hanya hasil cermin katalog saat Admin membuka halaman, bukan dari role lain.
    expect(Indicator::where('name', 'Penyusup')->exists())->toBeFalse()
        ->and(Indicator::whereNull('bps_source')->count())->toBe(0)
        ->and(glob($this->folderPdf . '/*'))->toBe([]);
});

it('menampilkan tab Tabel Dinamis seperti situs BPS: kategori subjek, subjek, dan tabel', function () {
    palsukanBps();

    $this->actingAs($this->admin)->get('/admin/data-bps')
        ->assertOk()
        ->assertSee('Hanya dapat memilih maksimal 2 data.')
        ->assertSee('Kategori Subjek')
        ->assertSee('Tabel / Indikator')
        ->assertSee('Data Terpilih')
        ->assertDontSee('SIMDASI & Tabel Statis')
        ->assertViewHas('katalog', function (array $katalog) {
            // Klasifikasi CSA, sama dengan dropdown di situs BPS: 3 kategori dan 37 subjek, urut per kategori lalu ID.
            expect(collect($katalog['kategori'])->pluck('nama')->all())
                ->toBe(['Statistik Demografi dan Sosial', 'Statistik Ekonomi', 'Statistik Lingkungan Hidup dan Multi-domain'])
                ->and($katalog['subjek'])->toHaveCount(37)
                ->and(collect($katalog['subjek'])->pluck('nama')->take(3)->all())->toBe(['Kependudukan dan Migrasi', 'Tenaga Kerja', 'Pendidikan'])
                // Kategori tabel diambil dari subjek CSA-nya (subcsa_id).
                ->and(collect($katalog['variabel'])->firstWhere('id', 32))->toMatchArray([
                    'judul' => 'Penduduk per Kecamatan menurut Jenis Kelamin', 'subjek' => 519, 'kategori' => 514,
                ])
                ->and(collect($katalog['variabel'])->firstWhere('id', 1))->toMatchArray(['subjek' => 536, 'kategori' => 515]);

            return true;
        });
});

it('mengirim isian tabel dinamis (tahun, turunan tahun, karakteristik, judul baris) dalam JSON', function () {
    palsukanBps();

    $this->actingAs($this->admin)->getJson('/admin/data-bps/dinamis/32/pilihan')
        ->assertOk()
        ->assertJsonPath('tahun.0', '2024')
        ->assertJsonPath('turtahun', [['id' => 0, 'label' => 'Tahun']])
        ->assertJsonPath('karakteristik.2', ['id' => 29, 'label' => 'Jumlah'])
        ->assertJsonPath('baris.0', ['id' => 10, 'label' => 'SIANTAR MARIHAT'])
        ->assertJsonPath('labelKarakteristik', 'Jenis Kelamin')
        ->assertJsonPath('labelBaris', 'Kecamatan');

    // Tabel tanpa karakteristik: API membalas "list-not-available".
    $this->actingAs($this->admin)->getJson('/admin/data-bps/dinamis/31/pilihan')
        ->assertOk()
        ->assertJsonPath('karakteristik', [])
        ->assertJsonCount(9, 'baris');
});

it('menampilkan hasil tabel dinamis untuk tahun yang dipilih', function () {
    palsukanBps();
    $url = '/admin/data-bps/dinamis/hasil?' . http_build_query(['data' => [['var' => 31, 'tahun' => ['2024', '2023', '2020']]]]);

    $this->actingAs($this->admin)->get($url)
        ->assertOk()
        ->assertSee('Penduduk per kecamatan')
        ->assertSee('SIANTAR MARIHAT')
        ->assertSee('20.933')
        ->assertSee('Simpan ke Kelola Data');
});

it('menampilkan 2 data terpilih sesuai karakteristik dan judul baris yang dipilih', function () {
    palsukanBps();
    $url = '/admin/data-bps/dinamis/hasil?' . http_build_query(['data' => [
        ['var' => 31, 'tahun' => ['2023', '2024'], 'baris' => [10, 11]],
        ['var' => 32, 'tahun' => ['2024'], 'karakteristik' => [29]],
    ]]);

    $this->actingAs($this->admin)->get($url)
        ->assertOk()
        ->assertSee('Penduduk per Kecamatan menurut Jenis Kelamin')
        ->assertViewHas('hasil', function (array $hasil) {
            $kecamatan = $hasil[0]['tabel']['matriks'];
            $jenisKelamin = $hasil[1]['tabel']['matriks'];

            expect($hasil[0]['saring'])->toBe(['baris' => [10, 11]])
                ->and(collect($kecamatan['rows'])->map(fn ($baris) => $baris[0]['value'])->all())->toBe(['SIANTAR MARIHAT', 'SIANTAR MARIMBUN'])
                ->and(array_column($jenisKelamin['headers'], 'value'))->toBe(['Kecamatan', 'Jumlah'])
                ->and(array_column($jenisKelamin['rows'][1], 'value'))->toBe(['SIANTAR MARIHAT', '21610']);

            return true;
        });
});

it('menolak lebih dari 2 data terpilih seperti situs BPS', function () {
    palsukanBps();
    $url = '/admin/data-bps/dinamis/hasil?' . http_build_query(['data' => [['var' => 1], ['var' => 31], ['var' => 32]]]);

    $this->actingAs($this->admin)->from('/admin/data-bps')->get($url)
        ->assertRedirect('/admin/data-bps')
        ->assertSessionHasErrors(['data' => 'Hanya dapat memilih maksimal 2 data.']);

    Http::assertNothingSent();
});

it('memakai 2 tahun terbaru bila tahun belum dipilih', function () {
    palsukanBps();

    // Tahun terbaru var 31 adalah 2025 (belum ada datanya) dan 2024.
    $this->actingAs($this->admin)->get('/admin/data-bps/dinamis/hasil?data[0][var]=31')
        ->assertOk()
        ->assertSee('Hasil Tabel Dinamis')
        ->assertSee('21.610');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'th=125%3B124'));
});

it('menampilkan judul tabel walau tahun yang dipilih belum berisi data', function () {
    palsukanBps();

    $this->actingAs($this->admin)->get('/admin/data-bps/dinamis/hasil?data[0][var]=31&data[0][tahun][]=2025')
        ->assertOk()
        ->assertSee('Penduduk per kecamatan')
        ->assertSee('Tidak ada data untuk pilihan ini')
        ->assertDontSee('Simpan ke Kelola Data');
});

it('menyimpan data terpilih sesuai judul baris yang dipilih', function () {
    palsukanBps();

    $this->actingAs($this->admin)->post('/admin/data-bps/tabel', [
        'var' => 31, 'tahun' => ['2024'], 'baris' => [10, 11],
        'subject_id' => $this->subjek->id, 'name' => 'Penduduk Dua Kecamatan', 'unit' => 'Jiwa',
    ])->assertRedirect(route('admin.keloladata', ['search' => 'Penduduk Dua Kecamatan']));

    expect(collect(Indicator::sole()->data['rows'])->map(fn ($baris) => $baris[0]['value'])->all())
        ->toBe(['SIANTAR MARIHAT', 'SIANTAR MARIMBUN']);
});

it('menyimpan tabel dinamis sebagai indikator baru lalu mengarah ke Kelola Data', function () {
    palsukanBps();

    $this->actingAs($this->admin)->post('/admin/data-bps/tabel', [
        'var' => 31, 'tahun' => ['2024', '2023'],
        'subject_id' => $this->subjek->id, 'name' => 'Penduduk per Kecamatan', 'unit' => 'Jiwa',
    ])->assertRedirect(route('admin.keloladata', ['search' => 'Penduduk per Kecamatan']));

    $indikator = Indicator::sole();
    expect($indikator->user_id)->toBe($this->admin->id)
        ->and($indikator->subject_id)->toBe($this->subjek->id)
        ->and($indikator->unit)->toBe('Jiwa')
        ->and(array_column($indikator->data['headers'], 'value'))->toBe(['Kecamatan', '2023', '2024'])
        ->and(array_column($indikator->data['rows'][0], 'value'))->toBe(['SIANTAR MARIHAT', '21484', '21610']);
});

it('memperbarui indikator yang sudah ada tanpa membuat duplikat', function () {
    palsukanBps();
    $lama = Indicator::create([
        'subject_id' => $this->subjek->id, 'name' => 'Penduduk per Kecamatan', 'unit' => 'Jiwa',
        'data' => ['headers' => [['value' => 'Lama']], 'rows' => [[['value' => '1']]]],
    ]);

    $this->actingAs($this->pj)->post('/penanggungjawab/data-bps/tabel', [
        'var' => 31, 'tahun' => ['2020'],
        'subject_id' => $this->subjek->id, 'name' => 'Penduduk per Kecamatan', 'unit' => 'Jiwa',
        'indicator_id' => $lama->id,
    ])->assertRedirect(route('penanggungjawab.keloladata', ['search' => 'Penduduk per Kecamatan']));

    expect(Indicator::count())->toBe(1)
        ->and(array_column($lama->fresh()->data['rows'][0], 'value'))->toBe(['SIANTAR MARIHAT', '20933']);
});

it('tidak menyimpan apa pun bila tahun yang dipilih tidak berisi data', function () {
    palsukanBps();
    $hasil = '/admin/data-bps/dinamis/hasil?data[0][var]=31';

    $this->actingAs($this->admin)->from($hasil)
        ->post('/admin/data-bps/tabel', [
            'var' => 31, 'tahun' => ['2025'],
            'subject_id' => $this->subjek->id, 'name' => 'Penduduk per Kecamatan',
        ])
        ->assertRedirect($hasil)
        ->assertSessionHas('error');

    expect(Indicator::count())->toBe(0);
});

it('menolak ID tabel dinamis yang tidak valid tanpa menghubungi API', function () {
    palsukanBps();

    $this->actingAs($this->admin)->get('/admin/data-bps/dinamis/31abc/pilihan')->assertNotFound();
    $this->actingAs($this->admin)->get('/admin/data-bps/dinamis/hasil?data[0][var]=31abc')->assertSessionHasErrors('data.0.var');
    $this->actingAs($this->admin)->post('/admin/data-bps/tabel', [
        'var' => '../../etc', 'subject_id' => $this->subjek->id, 'name' => 'Penduduk per Kecamatan',
    ])->assertSessionHasErrors('var');

    Http::assertNothingSent();
    expect(Indicator::count())->toBe(0);
});

// ===========================================
// --- PUBLIKASI ---
// ===========================================

it('menandai publikasi yang PDF-nya sudah ada di basis pengetahuan walau penamaannya berbeda', function () {
    palsukanBps();
    file_put_contents($this->folderPdf . '/KECAMATAN_SIANTAR_BARAT_DALAM_ANGKA_(2026).pdf', '%PDF-1.4');
    file_put_contents($this->storage . '/app/processed_log_bge_m3.txt', 'KECAMATAN_SIANTAR_BARAT_DALAM_ANGKA_(2026).pdf' . PHP_EOL);

    $this->actingAs($this->admin)->get('/admin/data-bps?tab=publikasi')
        ->assertOk()
        ->assertSee('Kecamatan Siantar Barat Dalam Angka 2026')
        ->assertSee('Kecamatan Siantar Martoba Dalam Angka 2026')
        ->assertSee('Sudah ada di basis pengetahuan AI')
        ->assertSee('Ambil &amp; Latih Publikasi Baru', false)
        ->assertSee(route('admin.databps.publikasi.simpan', '9b58feb2f32b167766d36655'))
        ->assertDontSee(route('admin.databps.publikasi.simpan', 'd16eaeb2fff0805a540b5047'))
        ->assertSee('Halaman 1 dari 32');
});

it('menawarkan melatih PDF publikasi yang sudah tersimpan tetapi belum dilatih', function () {
    palsukanBps();
    file_put_contents($this->folderPdf . '/Kecamatan_Siantar_Barat_Dalam_Angka_2026.pdf', '%PDF-1.4');

    $this->actingAs($this->admin)->get('/admin/data-bps?tab=publikasi')
        ->assertOk()
        ->assertSee('PDF tersimpan, belum dilatih')
        ->assertSee('Latih ke AI')
        ->assertSee(route('admin.databps.publikasi.simpan', 'd16eaeb2fff0805a540b5047'));
});

it('mengirim link PDF publikasi ke AI tanpa menyimpan PDF di server Laravel', function () {
    config(['services.huggingface.url' => 'https://ai-uji.test']);
    palsukanBps(['ai-uji.test' => fn () => Http::response(['status' => 'started'])]);

    $this->actingAs($this->admin)->from('/admin/data-bps?tab=publikasi')
        ->post('/admin/data-bps/publikasi/d16eaeb2fff0805a540b5047')
        ->assertRedirect('/admin/data-bps?tab=publikasi')
        ->assertSessionHas('success', 'PDF "Kecamatan Siantar Barat Dalam Angka 2026" diambil server AI langsung dari link WebAPI BPS (tanpa disimpan di server ini) dan sedang dilatihkan (ekstraksi berjalan di server AI, beberapa menit).');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://ai-uji.test/ingest-url'
        && $r['url'] === 'https://webapi.bps.go.id/download.php?f=uji'
        && $r['filename'] === 'Kecamatan_Siantar_Barat_Dalam_Angka_2026.pdf');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'download.php') || str_contains($r->url(), 'upload-ingest'));
    expect(glob($this->folderPdf . '/*'))->toBe([])
        ->and(file($this->storage . '/app/processed_log_bge_m3.txt', FILE_IGNORE_NEW_LINES))->toBe(['Kecamatan_Siantar_Barat_Dalam_Angka_2026.pdf']);

    // Sudah dilatih: tidak dikirim lagi, dan tab Publikasi menandainya.
    $this->actingAs($this->admin)->post('/admin/data-bps/publikasi/d16eaeb2fff0805a540b5047')
        ->assertSessionHas('success', 'Publikasi "Kecamatan Siantar Barat Dalam Angka 2026" sudah ada di basis pengetahuan AI (Kecamatan_Siantar_Barat_Dalam_Angka_2026.pdf).');
    $this->actingAs($this->admin)->get('/admin/data-bps?tab=publikasi')->assertSee('Sudah ada di basis pengetahuan AI');
    Http::assertSentCount(4); // detail + link ke AI, detail, daftar publikasi
});

it('mengunduh dan mengunggah PDF sendiri bila server AI belum mendukung atau tidak bisa mengunduh dari BPS', function (int $statusAi) {
    config(['services.huggingface.url' => 'https://ai-uji.test']);
    palsukanBps([
        'ai-uji.test/ingest-url' => fn () => Http::response(['detail' => 'Server BPS menolak unduhan dari server AI (HTTP 403).'], $statusAi),
        'ai-uji.test/upload-ingest' => fn () => Http::response(['status' => 'started']),
    ]);

    $this->actingAs($this->admin)->post('/admin/data-bps/publikasi/d16eaeb2fff0805a540b5047')
        ->assertSessionHas('success', 'PDF "Kecamatan Siantar Barat Dalam Angka 2026" diunduh dari WebAPI BPS dan sedang dilatihkan (ekstraksi berjalan di server AI, beberapa menit).');

    $berkas = $this->folderPdf . '/Kecamatan_Siantar_Barat_Dalam_Angka_2026.pdf';
    expect(file_get_contents($berkas))->toStartWith('%PDF-')
        ->and(glob($this->folderPdf . '/*.part'))->toBe([])
        ->and(file($this->storage . '/app/processed_log_bge_m3.txt', FILE_IGNORE_NEW_LINES))->toBe(['Kecamatan_Siantar_Barat_Dalam_Angka_2026.pdf']);
    Http::assertSent(fn (Request $r) => $r->url() === 'https://ai-uji.test/upload-ingest' && $r->isMultipart());
})->with(['endpoint belum ada (404)' => 404, 'server AI diblokir BPS (502)' => 502]);

it('tidak mengunduh apa pun bila layanan AI belum diatur atau sedang bermasalah', function (?string $urlAi, int $statusAi, string $pesan) {
    config(['services.huggingface.url' => $urlAi]);
    palsukanBps(['ai-uji.test' => fn () => Http::response(['detail' => 'Gagal'], $statusAi)]);

    $this->actingAs($this->pj)->post('/penanggungjawab/data-bps/publikasi/d16eaeb2fff0805a540b5047')
        ->assertSessionHas('error', "Publikasi \"Kecamatan Siantar Barat Dalam Angka 2026\" belum bisa dilatihkan ke AI: {$pesan} Ulangi nanti, atau pakai perintah php artisan bps:publikasi.");

    expect(glob($this->folderPdf . '/*'))->toBe([])
        ->and(file_exists($this->storage . '/app/processed_log_bge_m3.txt'))->toBeFalse();
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'download.php'));
})->with([
    'alamat AI kosong' => [null, 200, 'Alamat layanan AI (HUGGINGFACE_API_URL) belum diisi di .env.'],
    'server AI galat' => ['https://ai-uji.test', 500, 'Layanan AI menolak permintaan: Gagal'],
]);

it('mengambil dan melatih semua publikasi baru lewat tombol (satu publikasi per permintaan)', function () {
    config(['services.huggingface.url' => 'https://ai-uji.test']);
    palsukanBps(['ai-uji.test' => fn () => Http::response(['status' => 'started'])]);
    file_put_contents($this->folderPdf . '/Kecamatan_Siantar_Martoba_Dalam_Angka_2026.pdf', '%PDF-1.4');
    file_put_contents($this->storage . '/app/processed_log_bge_m3.txt', 'Kecamatan_Siantar_Martoba_Dalam_Angka_2026.pdf' . PHP_EOL);

    $daftar = $this->actingAs($this->admin)->postJson('/admin/data-bps/publikasi-otomatis', ['sejak' => now()->year, 'kata' => 'siantar barat, martoba'])
        ->assertOk()
        ->assertJsonPath('jumlah', 2)
        ->json('publikasi');
    expect(array_column($daftar, 'id'))->toBe(['d16eaeb2fff0805a540b5047']); // yang sudah dilatih dilewati
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'model=publication') && str_contains($r->url(), 'year=' . now()->year));

    $this->actingAs($this->admin)->postJson('/admin/data-bps/publikasi-otomatis/d16eaeb2fff0805a540b5047')
        ->assertOk()
        ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'sedang dilatihkan'));

    $this->actingAs($this->admin)->postJson('/admin/data-bps/publikasi-otomatis', ['sejak' => now()->year, 'kata' => 'tidak ada judul seperti ini'])
        ->assertOk()->assertJsonPath('jumlah', 0);
    $this->actingAs($this->biasa)->postJson('/admin/data-bps/publikasi-otomatis', ['sejak' => now()->year])->assertForbidden();
    $this->actingAs($this->biasa)->postJson('/admin/data-bps/publikasi-otomatis/d16eaeb2fff0805a540b5047')->assertForbidden();
});

it('meminta browser berhenti bila layanan AI tidak bisa dipakai saat melatih publikasi', function () {
    config(['services.huggingface.url' => 'https://ai-uji.test']);
    palsukanBps(['ai-uji.test' => fn () => Http::response('Service Unavailable', 503)]);

    $this->actingAs($this->admin)->postJson('/admin/data-bps/publikasi-otomatis/d16eaeb2fff0805a540b5047')
        ->assertStatus(502)
        ->assertJsonPath('berhenti', true);

    // Belum tercatat dilatih, jadi dicoba lagi pada proses berikutnya.
    expect(file_exists($this->storage . '/app/processed_log_bge_m3.txt'))->toBeFalse();
});

it('melatih publikasi baru lewat php artisan bps:publikasi', function () {
    config(['services.huggingface.url' => 'https://ai-uji.test']);
    $martoba = fixtureBps('publikasi_detail.json');
    $martoba['data'] = ['pub_id' => '9b58feb2f32b167766d36655', 'title' => 'Kecamatan Siantar Martoba Dalam Angka 2026'] + $martoba['data'];
    palsukanBps([
        'ai-uji.test' => fn () => Http::response(['status' => 'started']),
        'id=9b58feb2f32b167766d36655' => fn () => Http::response($martoba),
    ]);

    $this->artisan('bps:publikasi', ['--sejak' => now()->year, '--daftar' => true])
        ->expectsOutputToContain('belum dilatih')
        ->assertSuccessful();
    expect(glob($this->folderPdf . '/*.pdf'))->toBe([]);

    $this->artisan('bps:publikasi', ['--sejak' => now()->year])
        ->expectsOutputToContain('link dikirim, server AI mengunduh sendiri')
        ->expectsOutputToContain('2 publikasi dilatihkan ke AI, 0 gagal.')
        ->assertSuccessful();
    expect(glob($this->folderPdf . '/*.pdf'))->toBe([])
        ->and(file($this->storage . '/app/processed_log_bge_m3.txt', FILE_IGNORE_NEW_LINES))
        ->toBe(['Kecamatan_Siantar_Barat_Dalam_Angka_2026.pdf', 'Kecamatan_Siantar_Martoba_Dalam_Angka_2026.pdf']);
    $this->artisan('bps:publikasi', ['--sejak' => now()->year])
        ->expectsOutputToContain('Semua publikasi sudah ada di basis pengetahuan AI.')
        ->assertSuccessful();
});

it('menolak berkas unduhan yang bukan PDF', function () {
    // Server AI belum mendukung link, jadi PDF diunduh di sini (cara cadangan).
    config(['services.huggingface.url' => 'https://ai-uji.test']);
    palsukanBps([
        'download.php' => fn () => Http::response('<html>Perimeter WAF Block</html>', 200),
        'ai-uji.test' => fn () => Http::response('Not Found', 404),
    ]);

    $this->actingAs($this->admin)->post('/admin/data-bps/publikasi/d16eaeb2fff0805a540b5047')
        ->assertSessionHas('error', 'Berkas yang diterima dari BPS bukan PDF.');

    expect(glob($this->folderPdf . '/*'))->toBe([]);
});

it('tidak menampilkan tautan dari API yang bukan alamat http(s)', function () {
    $daftar = fixtureBps('publikasi_daftar.json');
    $daftar['data'][1][0]['pdf'] = 'javascript:alert(1)';
    palsukanBps(['model=publication' => fn () => Http::response($daftar)]);

    $this->actingAs($this->admin)->get('/admin/data-bps?tab=publikasi')
        ->assertOk()
        ->assertSee('Kecamatan Siantar Barat Dalam Angka 2026')
        ->assertDontSee('javascript:alert(1)', false);
});

it('hanya mengunduh PDF dari server BPS', function () {
    $detail = fixtureBps('publikasi_detail.json');
    $detail['data']['pdf'] = 'https://contoh-bukan-bps.test/berkas.pdf';
    palsukanBps(['id=d16eaeb2fff0805a540b5047' => fn () => Http::response($detail)]);

    $this->actingAs($this->admin)->post('/admin/data-bps/publikasi/d16eaeb2fff0805a540b5047')
        ->assertSessionHas('error', 'Alamat unduhan PDF bukan dari server BPS.');

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'contoh-bukan-bps.test') || str_contains($r->url(), 'ingest'));
});
