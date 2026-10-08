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

function fixtureBps(string $nama): array
{
    return json_decode(file_get_contents(__DIR__ . '/../Fixtures/bps/' . $nama), true);
}

// Respons model=data var 31 untuk th tertentu, disusun dari fixture tahun 2020, 2023, dan 2024.
// Seperti API aslinya, tahun tanpa data tidak muncul dan permintaan tanpa data sama sekali dibalas "null".
function dataVar31(array $thIds): ?array
{
    $data = fixtureBps('data_31_2023_2024.json');
    $lama = fixtureBps('data_31_2020.json');
    $tahun = array_merge($lama['tahun'], $data['tahun']);
    $isi = $data['datacontent'] + $lama['datacontent'];

    // Kunci datacontent var 31: vervar . "31" . "0" . tahun(3 digit) . "0"
    $data['tahun'] = array_values(array_filter($tahun, fn ($t) => in_array((string) $t['val'], $thIds, true)));
    $data['datacontent'] = array_filter($isi, fn ($k) => in_array(substr((string) $k, -4, 3), $thIds, true), ARRAY_FILTER_USE_KEY);

    return $data['tahun'] ? $data : null;
}

// Membalas permintaan ke WebAPI BPS seperti server aslinya, berdasarkan jalur dan parameter query.
// $timpa: [potongan URL => fn (Request) => respons] untuk mengganti balasan tertentu.
function palsukanBps(array $timpa = []): void
{
    Http::fake(function (Request $request) use ($timpa) {
        $url = $request->url();
        foreach ($timpa as $potongan => $balasan) {
            if (str_contains($url, $potongan)) {
                return $balasan($request);
            }
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $jalur = (string) parse_url($url, PHP_URL_PATH);

        if (str_ends_with($jalur, '/download.php')) {
            return Http::response("%PDF-1.4\n% PDF uji\n", 200, ['Content-Type' => 'application/pdf']);
        }
        if (str_ends_with($jalur, '/list') && ($q['model'] ?? '') === 'data') {
            $th = explode(';', $q['th'] ?? '');
            $data = match ($q['var'] ?? '') {
                '31' => dataVar31($th),
                '32' => in_array('124', $th, true) ? fixtureBps('data_32_2024.json') : null, // var 32 hanya berisi 2024
                default => null,
            };

            return $data ? Http::response($data) : Http::response('null');
        }

        $var = $q['var'] ?? '';
        $berkas = match (true) {
            str_ends_with($jalur, '/list') => match ($q['model'] ?? '') {
                'th' => $var === '32' ? 'th_32.json' : (($q['page'] ?? '1') === '2' ? 'th_31_hal2.json' : 'th_31_hal1.json'),
                'var' => 'var_daftar.json',
                'subjectcsa' => 'subjek_csa.json',
                'subcatcsa' => 'kategori_csa.json',
                'turvar' => "turvar_{$var}.json",
                'vervar' => "vervar_{$var}.json",
                'turth' => 'turth_tahunan.json',
                'publication' => 'publikasi_daftar.json',
                default => null,
            },
            str_ends_with($jalur, '/view') && ($q['model'] ?? '') === 'publication' => 'publikasi_detail.json',
            default => null,
        };

        return $berkas && is_file(__DIR__ . '/../Fixtures/bps/' . $berkas)
            ? Http::response(fixtureBps($berkas))
            : Http::response('null');
    });
}

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

    expect(Indicator::count())->toBe(0)->and(glob($this->folderPdf . '/*'))->toBe([]);
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

    $this->actingAs($this->admin)->get('/admin/data-bps?tab=publikasi')
        ->assertOk()
        ->assertSee('Kecamatan Siantar Barat Dalam Angka 2026')
        ->assertSee('Kecamatan Siantar Martoba Dalam Angka 2026')
        ->assertSee('Sudah ada di basis pengetahuan')
        ->assertSee(route('admin.databps.publikasi.simpan', '9b58feb2f32b167766d36655'))
        ->assertDontSee(route('admin.databps.publikasi.simpan', 'd16eaeb2fff0805a540b5047'))
        ->assertSee('Halaman 1 dari 32');
});

it('menyimpan PDF publikasi ke folder basis pengetahuan tanpa menjalankan ingest', function () {
    palsukanBps();

    $this->actingAs($this->admin)->from('/admin/data-bps?tab=publikasi')
        ->post('/admin/data-bps/publikasi/d16eaeb2fff0805a540b5047')
        ->assertRedirect('/admin/data-bps?tab=publikasi')
        ->assertSessionHas('success');

    $berkas = $this->folderPdf . '/Kecamatan_Siantar_Barat_Dalam_Angka_2026.pdf';
    expect(file_exists($berkas))->toBeTrue()
        ->and(file_get_contents($berkas))->toStartWith('%PDF-')
        ->and(glob($this->folderPdf . '/*.part'))->toBe([]);

    // Ingest tetap dijalankan pengguna dari Manajemen Pengetahuan, bukan otomatis.
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'ingest'));

    // Klik kedua tidak mengunduh ulang.
    $this->actingAs($this->admin)->post('/admin/data-bps/publikasi/d16eaeb2fff0805a540b5047')->assertSessionHas('success');
    Http::assertSentCount(3); // detail + unduh, lalu detail saja
});

it('menolak berkas unduhan yang bukan PDF', function () {
    palsukanBps(['download.php' => fn () => Http::response('<html>Perimeter WAF Block</html>', 200)]);

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

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'contoh-bukan-bps.test'));
});
