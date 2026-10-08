<?php

// Uji sinkronisasi tabel WebAPI BPS ke indikator (SinkronisasiBps, tab Sinkronisasi, bps:sinkron, bps:cek).
//
// AMAN dijalankan kapan saja: tidak ada permintaan sungguhan ke WebAPI BPS (semua dibalas fixture lewat
// palsukanBps di tests/BantuanBps.php) dan database memakai SQLite di memori (phpunit.xml).

use App\Http\Controllers\DashboardController;
use App\Models\Category;
use App\Models\Indicator;
use App\Models\Subject;
use App\Models\User;
use App\Services\Bps\KonverterTabelBps;
use App\Services\Bps\SinkronisasiBps;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const ID_LUAS = 'UFpWMmJZOVZlZTJnc1pXaHhDV1hPQT09';
const ID_PENDUDUK = 'c2ltZGFzaS9wZW5kdWR1aw=='; // berisi "/" seperti base64 pada umumnya

function nilaiBaris(array $baris): array
{
    return array_column($baris, 'value');
}

function parseDashboard(array $data): ?array
{
    return (new ReflectionMethod(DashboardController::class, 'parseIndicatorData'))
        ->invoke(new DashboardController(), new Indicator(['name' => 'Uji', 'data' => $data]));
}

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('Tes sinkronisasi BPS hanya boleh berjalan di SQLite :memory: (lihat phpunit.xml).');
    }
    $this->artisan('migrate');

    Http::preventStrayRequests();
    config(['services.bps.key' => 'kunci-uji-rahasia-123', 'services.bps.domain' => '1273', 'services.bps.wilayah_simdasi' => null]);
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

// ===========================================
// --- KONVERSI TABEL SIMDASI & STATIS ---
// ===========================================

it('menggabungkan semua tahun tabel SIMDASI menjadi satu matriks (kolom > tahun)', function () {
    $hasil = KonverterTabelBps::dariSimdasi([
        2025 => fixtureBps('simdasi_luas_2025.json'),
        2023 => fixtureBps('simdasi_tidak_ada.json'),
        2024 => fixtureBps('simdasi_luas_2024.json'),
    ]);
    $m = $hasil['matriks'];

    expect($hasil['judul'])->toBe('Luas Daerah dan Jumlah Kelurahan Menurut Kecamatan di Kota Pematangsiantar')
        ->and($hasil['satuan'])->toBe('') // satuan kolom berbeda, jadi ditulis di judul kolom
        ->and($hasil['catatan'])->toBe('Sumber: Bagian Tata Pemerintahan Setda Kota Pematangsiantar')
        ->and(nilaiBaris($m['headers']))->toBe(['Kecamatan', 'Luas Wilayah (km²)', '', 'Jumlah Kelurahan', ''])
        ->and($m['headers'][0]['rowspan'])->toBe(2)
        ->and($m['headers'][1]['colspan'])->toBe(2)
        ->and(nilaiBaris(array_slice($m['rows'][0], 1)))->toBe(['2024', '2025', '2024', '2025'])
        // Label baris yang beda huruf besar/kecil antartahun tetap satu baris.
        ->and(nilaiBaris($m['rows'][1]))->toBe(['Siantar Marihat', '7.825', '7.825', '7', '8'])
        ->and(nilaiBaris(end($m['rows'])))->toBe(['Pematangsiantar', '79.971', '79.971', '53', '53'])
        ->and($m['rows'])->toHaveCount(5);

    // Grid persegi seperti hasil impor Excel.
    expect(collect([$m['headers'], ...$m['rows']])->map(fn ($b) => count($b))->unique()->values()->all())->toBe([5]);
});

it('meratakan kolom SIMDASI bertingkat dan memakai satuan bersama sebagai satuan indikator', function () {
    $hasil = KonverterTabelBps::dariSimdasi([2024 => fixtureBps('simdasi_penduduk_2024.json'), 2025 => fixtureBps('simdasi_penduduk_2025.json')]);
    $m = $hasil['matriks'];

    expect($hasil['satuan'])->toBe('Jiwa')
        ->and($hasil['catatan'])->toContain('Angka proyeksi penduduk')
        ->and(nilaiBaris($m['headers']))->toBe(['Kecamatan', 'Jumlah Penduduk', '', '', '', '', ''])
        ->and($m['headers'][1]['colspan'])->toBe(6)
        ->and(nilaiBaris($m['rows'][0]))->toBe(['', 'Laki-laki', '', 'Perempuan', '', 'Jumlah', ''])
        ->and(nilaiBaris(array_slice($m['rows'][1], 1)))->toBe(['2024', '2025', '2024', '2025', '2024', '2025'])
        // "10 668" (format BPS) dibaca dari value_raw.
        ->and(nilaiBaris($m['rows'][2]))->toBe(['Siantar Marihat', '10668', '10701', '10942', '10990', '21610', '21691']);
});

it('mengubah tabel statis HTML (sel gabungan, judul, nomor kolom, sumber) ke format indikator', function () {
    $html = fixtureBps('statis_detail_512.json')['data']['table'];

    foreach ([$html, htmlspecialchars($html)] as $masukan) { // API kadang mengirim HTML yang di-escape
        $m = KonverterTabelBps::dariHtml($masukan);

        expect(nilaiBaris($m['headers']))->toBe(['Kecamatan', 'Sarana Kesehatan', '', ''])
            ->and($m['headers'][0]['rowspan'])->toBe(2)
            ->and($m['headers'][1]['colspan'])->toBe(3)
            ->and(nilaiBaris($m['rows'][0]))->toBe(['', 'Rumah Sakit', 'Puskesmas', 'Klinik'])
            ->and(nilaiBaris($m['rows'][1]))->toBe(['Siantar Marihat', '-', '1', '3'])
            ->and(nilaiBaris($m['rows'][2]))->toBe(['Siantar Barat', '2', '1', '1234'])
            ->and(nilaiBaris($m['rows'][3]))->toBe(['Pematangsiantar', '10', '19', '1250'])
            ->and($m['rows'])->toHaveCount(4);
    }
});

it('membuang kolom nomor urut pada tabel statis', function () {
    $m = KonverterTabelBps::dariHtml('<table><tr><th>No.</th><th>Jenis Pajak</th><th>2023</th><th>2024</th></tr>'
        . '<tr><td>1</td><td>Pajak Hotel</td><td>1.500,5</td><td>1.720,25</td></tr>'
        . '<tr><td>2</td><td>Pajak Restoran</td><td>2.100</td><td>2.480</td></tr></table>');

    expect(nilaiBaris($m['headers']))->toBe(['Jenis Pajak', '2023', '2024'])
        ->and(nilaiBaris($m['rows'][0]))->toBe(['Pajak Hotel', '1500.5', '1720.25']);
});

it('menghasilkan data SIMDASI dan tabel statis yang langsung bisa divisualisasikan dashboard', function () {
    $simdasi = parseDashboard(KonverterTabelBps::dariSimdasi([2024 => fixtureBps('simdasi_penduduk_2024.json'), 2025 => fixtureBps('simdasi_penduduk_2025.json')])['matriks']);
    $statis = parseDashboard(KonverterTabelBps::dariHtml(fixtureBps('statis_detail_512.json')['data']['table']));

    expect($simdasi['available_types'])->toContain('line')
        ->and($simdasi['long_form'][0])->toMatchArray(['Kecamatan' => 'Siantar Marihat', 'Tahun' => '2024'])
        ->and(collect($simdasi['long_form'])->pluck('Tahun')->unique()->values()->all())->toBe(['2024', '2025'])
        ->and($statis)->not->toBeNull()
        ->and($statis['available_types'])->toContain('bar');
});

// ===========================================
// --- KATALOG & IMPOR ---
// ===========================================

it('menampilkan tab Sinkronisasi beserta indikator yang sudah tertaut ke API', function () {
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'Kependudukan'])->id, 'name' => 'Penduduk']);
    Indicator::create(['subject_id' => $subjek->id, 'name' => 'Penduduk per kecamatan', 'data' => [],
        'bps_source' => 'dinamis', 'bps_table_id' => '31', 'bps_synced_at' => now()]);

    $this->actingAs($this->admin)->get('/admin/data-bps?tab=sinkron')
        ->assertOk()
        ->assertSee('Sinkronisasi Data dari WebAPI BPS')
        ->assertSee('Perbarui Semua dari API')
        ->assertSee('Penduduk per kecamatan')
        ->assertSee('Muat Ulang Daftar')
        ->assertDontSee('Cara menghubungkan PRANATA ke WebAPI BPS');

    $this->actingAs($this->pj)->get('/penanggungjawab/data-bps?tab=sinkron')->assertOk()->assertSee('Sinkronisasi Semua Tabel');

    // Halaman memuat katalog lewat browser; membuka tab ini tidak menghubungi API.
    Http::assertNothingSent();
});

it('menjelaskan cara menghubungkan bila kunci API belum diisi', function () {
    Http::fake();
    config(['services.bps.key' => null]);

    $this->actingAs($this->admin)->get('/admin/data-bps?tab=sinkron')
        ->assertOk()
        ->assertSee('Cara menghubungkan PRANATA ke WebAPI BPS')
        ->assertSee('BPS_API_KEY')
        ->assertSee('php artisan bps:cek');

    Http::assertNothingSent();
});

it('mengirim katalog tabel publikasi SIMDASI berurutan kode tabel beserta tahun dan statusnya', function () {
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'Geografi'])->id, 'name' => 'Keadaan Geografi']);
    Indicator::create(['subject_id' => $subjek->id, 'name' => 'Rata-rata Suhu dan Kelembaban Udara di Kota Pematangsiantar', 'data' => []]);

    $this->actingAs($this->admin)->getJson('/admin/data-bps/sinkron/katalog?sumber=simdasi')
        ->assertOk()
        ->assertJsonPath('tabel.0.kode', '1.1.2')
        ->assertJsonPath('tabel.0.tahun', [2023, 2024, 2025])
        ->assertJsonPath('tabel.0.kategori', 'Geografi dan Iklim')
        ->assertJsonPath('tabel.0.indikator', null)
        ->assertJsonPath('tabel.1.namaSama.name', 'Rata-rata Suhu dan Kelembaban Udara di Kota Pematangsiantar')
        ->assertJsonPath('tabel.2.kode', '3.1.10'); // 3.1.10 sesudah 1.2.1 (urutan angka, bukan huruf)

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/interoperabilitas/datasource/simdasi/id/23/wilayah/1273000/key/kunci-uji-rahasia-123/'));
});

it('mengirim katalog tabel statis dengan kategori dari subjek BPS', function () {
    palsukanBps();

    $this->actingAs($this->pj)->getJson('/penanggungjawab/data-bps/sinkron/katalog?sumber=statis')
        ->assertOk()
        ->assertJsonCount(2, 'tabel')
        ->assertJsonPath('tabel.0', [
            'sumber' => 'statis', 'id' => '512',
            'judul' => 'Jumlah Sarana Kesehatan Menurut Kecamatan di Kota Pematangsiantar, 2024',
            'kode' => '', 'kategori' => 'Sosial dan Kependudukan', 'subjek' => 'Kesehatan', 'tahun' => [],
            'indikator' => null, 'namaSama' => null,
        ]);
});

it('mengimpor tabel SIMDASI dengan seluruh tahunnya ke kategori dan subjek BPS', function () {
    palsukanBps();

    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'simdasi', 'id' => ID_LUAS])
        ->assertOk()
        ->assertJsonPath('status', 'baru');

    $indikator = Indicator::with('subject.category')->sole();
    expect($indikator->name)->toBe('Luas Daerah dan Jumlah Kelurahan Menurut Kecamatan di Kota Pematangsiantar')
        ->and($indikator->subject->name)->toBe('Keadaan Geografi')
        ->and($indikator->subject->category->name)->toBe('Geografi dan Iklim')
        ->and($indikator->user_id)->toBe($this->admin->id)
        ->and($indikator->bps_source)->toBe('simdasi')
        ->and($indikator->bps_table_id)->toBe(ID_LUAS)
        ->and($indikator->bps_synced_at)->not->toBeNull()
        ->and(nilaiBaris(array_slice($indikator->data['rows'][0], 1)))->toBe(['2024', '2025', '2024', '2025']);

    // Satu permintaan per tahun yang tersedia (2023 tidak berisi data dan dilewati).
    foreach ([2023, 2024, 2025] as $tahun) {
        Http::assertSent(fn (Request $r) => str_contains($r->url(), "/simdasi/id/25/tahun/{$tahun}/id_tabel/" . ID_LUAS . '/wilayah/1273000/key/'));
    }
});

it('mengimpor ulang tanpa membuat indikator ganda dan mempertahankan nama yang sudah diubah', function () {
    palsukanBps();
    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'simdasi', 'id' => ID_PENDUDUK])->assertOk();
    Indicator::sole()->update(['name' => 'Penduduk Kota (nama sendiri)', 'data' => []]);

    $this->actingAs($this->pj)->postJson('/penanggungjawab/data-bps/sinkron/impor', ['sumber' => 'simdasi', 'id' => ID_PENDUDUK])
        ->assertOk()
        ->assertJsonPath('status', 'diperbarui');

    $indikator = Indicator::sole();
    expect($indikator->name)->toBe('Penduduk Kota (nama sendiri)')
        ->and($indikator->unit)->toBe('Jiwa')
        ->and($indikator->data['rows'])->not->toBeEmpty();
});

it('menautkan indikator lama yang namanya sama dengan tabel BPS dan mengganti datanya', function () {
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'Kesehatan'])->id, 'name' => 'Sarana Kesehatan']);
    $lama = Indicator::create(['subject_id' => $subjek->id, 'name' => 'jumlah sarana kesehatan menurut kecamatan di kota pematangsiantar, 2024',
        'data' => ['headers' => [['value' => 'Lama']], 'rows' => [[['value' => '1']]]]]);

    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'statis', 'id' => '512'])
        ->assertOk()
        ->assertJsonPath('status', 'ditautkan')
        ->assertJsonPath('indikator.id', $lama->id);

    $lama->refresh();
    expect(Indicator::count())->toBe(1)
        ->and($lama->subject_id)->toBe($subjek->id)
        ->and([$lama->bps_source, $lama->bps_table_id])->toBe(['statis', '512'])
        ->and(nilaiBaris($lama->data['headers']))->toBe(['Kecamatan', 'Sarana Kesehatan', '', '']);
});

it('memasukkan indikator baru ke subjek tujuan yang dipilih', function () {
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'Sosial dan Kependudukan'])->id, 'name' => 'Kependudukan']);

    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'simdasi', 'id' => ID_LUAS, 'subject_id' => $subjek->id])
        ->assertOk();

    expect(Indicator::sole()->subject_id)->toBe($subjek->id)
        ->and(Category::count())->toBe(1);
});

it('memakai subjek PRANATA yang namanya sama dengan subjek BPS', function () {
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'Sosial dan Kependudukan'])->id, 'name' => 'Kependudukan']);

    // Var 31 bersubjek CSA "Kependudukan dan Migrasi" dan subjek lama "Kependudukan".
    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'dinamis', 'id' => '31'])->assertOk();

    expect(Indicator::sole()->subject_id)->toBe($subjek->id);
});

it('mengimpor tabel dinamis dengan seluruh tahun yang tersedia', function () {
    palsukanBps();

    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'dinamis', 'id' => '31'])
        ->assertOk()
        ->assertJsonPath('status', 'baru');

    $indikator = Indicator::sole();
    expect(nilaiBaris($indikator->data['headers']))->toBe(['Kecamatan', '2020', '2023', '2024'])
        ->and($indikator->unit)->toBe('Jiwa')
        ->and([$indikator->bps_source, $indikator->bps_table_id, $indikator->bps_options])->toBe(['dinamis', '31', null]);

    // 16 tahun tersedia (th_31_hal1 + hal2) diminta 2 tahun per permintaan.
    $permintaanData = collect(Http::recorded())->filter(fn ($r) => str_contains($r[0]->url(), 'model=data'));
    expect($permintaanData)->toHaveCount(8);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'model=data') && str_contains($r->url(), 'th=125%3B124'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'model=data') && str_contains($r->url(), 'th=111%3B110'));
});

it('tidak menyimpan tabel yang tidak berisi data', function () {
    palsukanBps();

    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'simdasi', 'id' => 'c2ltZGFzaS1pa2xpbQ=='])
        ->assertStatus(502)
        ->assertJsonPath('galat', 'Tabel "Rata-rata Suhu dan Kelembaban Udara di Kota Pematangsiantar" tidak berisi data yang bisa dibaca, jadi tidak disimpan.'
            . ' Lihat respons API-nya dengan: php artisan bps:cek simdasi c2ltZGFzaS1pa2xpbQ== --mentah');

    expect(Indicator::count())->toBe(0);
});

it('menolak sumber dan ID tabel yang tidak valid tanpa menghubungi API', function () {
    palsukanBps();

    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'dinamis', 'id' => '31abc'])->assertStatus(422);
    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'simdasi', 'id' => '../../etc'])->assertStatus(422);
    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'lain', 'id' => '1'])->assertStatus(422);
    $this->actingAs($this->admin)->getJson('/admin/data-bps/sinkron/katalog?sumber=lain')->assertStatus(422);

    Http::assertNothingSent();
});

it('hanya Admin dan Penanggung Jawab yang boleh menyinkronkan', function () {
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'K'])->id, 'name' => 'S']);
    $indikator = Indicator::create(['subject_id' => $subjek->id, 'name' => 'X', 'data' => [], 'bps_source' => 'dinamis', 'bps_table_id' => '31']);

    foreach (['biasa', 'pimpinan'] as $role) {
        $this->actingAs($this->{$role})->get('/admin/data-bps?tab=sinkron')->assertForbidden();
        $this->actingAs($this->{$role})->getJson('/admin/data-bps/sinkron/katalog?sumber=simdasi')->assertForbidden();
        $this->actingAs($this->{$role})->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'simdasi', 'id' => ID_LUAS])->assertForbidden();
        $this->actingAs($this->{$role})->postJson("/penanggungjawab/data-bps/sinkron/perbarui/{$indikator->id}")->assertForbidden();
    }

    Http::assertNothingSent();
    expect(Indicator::count())->toBe(1)->and($indikator->fresh()->data)->toBe([]);
});

// ===========================================
// --- PEMBARUAN ---
// ===========================================

it('memperbarui indikator yang tertaut dengan data terbaru dari API', function () {
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'K'])->id, 'name' => 'S']);
    $indikator = Indicator::create(['subject_id' => $subjek->id, 'name' => 'Penduduk 2 Kecamatan', 'data' => [],
        'bps_source' => 'dinamis', 'bps_table_id' => '31', 'bps_options' => ['baris' => [11, 10]]]);

    $this->actingAs($this->pj)->postJson("/penanggungjawab/data-bps/sinkron/perbarui/{$indikator->id}")
        ->assertOk()
        ->assertJsonPath('pesan', 'Indikator "Penduduk 2 Kecamatan" diperbarui.');

    $indikator->refresh();
    expect(collect($indikator->data['rows'])->map(fn ($b) => $b[0]['value'])->all())->toBe(['SIANTAR MARIHAT', 'SIANTAR MARIMBUN'])
        ->and(nilaiBaris($indikator->data['headers']))->toBe(['Kecamatan', '2020', '2023', '2024'])
        ->and($indikator->bps_synced_at)->not->toBeNull();
});

it('mengambil ulang data dari API saat memperbarui walau responsnya masih di cache', function () {
    palsukanBps();
    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'simdasi', 'id' => ID_LUAS])->assertOk();
    $permintaan2024 = fn () => collect(Http::recorded())->filter(fn ($r) => str_contains($r[0]->url(), '/simdasi/id/25/tahun/2024/'))->count();
    expect($permintaan2024())->toBe(1);

    // Impor ulang memakai cache; pembaruan tidak.
    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/impor', ['sumber' => 'simdasi', 'id' => ID_LUAS])->assertOk();
    expect($permintaan2024())->toBe(1);

    $this->travel(1)->seconds();
    $this->actingAs($this->admin)->postJson('/admin/data-bps/sinkron/perbarui/' . Indicator::sole()->id)->assertOk();
    expect($permintaan2024())->toBe(2);
});

it('menolak memperbarui indikator yang tidak tertaut ke API', function () {
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'K'])->id, 'name' => 'S']);
    $indikator = Indicator::create(['subject_id' => $subjek->id, 'name' => 'Manual', 'data' => ['headers' => [], 'rows' => []]]);

    $this->actingAs($this->admin)->postJson("/admin/data-bps/sinkron/perbarui/{$indikator->id}")
        ->assertStatus(502)
        ->assertJsonPath('galat', 'Indikator "Manual" tidak tertaut ke tabel WebAPI BPS.');

    Http::assertNothingSent();
});

it('mencatat tautan API saat tabel dinamis disimpan dari tab Tabel Dinamis', function () {
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'K'])->id, 'name' => 'S']);

    $this->actingAs($this->admin)->post('/admin/data-bps/tabel', [
        'var' => 31, 'tahun' => ['2024'], 'baris' => [11, 10],
        'subject_id' => $subjek->id, 'name' => 'Penduduk Dua Kecamatan',
    ])->assertRedirect();

    expect(Indicator::sole()->only(['bps_source', 'bps_table_id', 'bps_options']))
        ->toBe(['bps_source' => 'dinamis', 'bps_table_id' => '31', 'bps_options' => ['baris' => [10, 11]]]);
});

// ===========================================
// --- PERINTAH ARTISAN ---
// ===========================================

it('mengimpor semua tabel SIMDASI lewat php artisan bps:sinkron --impor=simdasi', function () {
    palsukanBps();

    $this->artisan('bps:sinkron', ['--impor' => 'simdasi'])
        ->expectsOutputToContain('Luas Daerah dan Jumlah Kelurahan')
        ->expectsOutputToContain('Selesai: 2 indikator baru, 0 ditautkan, 0 diperbarui, 1 gagal.')
        ->assertSuccessful();

    expect(Indicator::dariBps()->pluck('bps_table_id')->sort()->values()->all())->toBe([ID_LUAS, ID_PENDUDUK])
        ->and(Category::pluck('name')->sort()->values()->all())->toBe(['Geografi dan Iklim', 'Penduduk dan Ketenagakerjaan']);
});

it('memperbarui semua indikator tertaut lewat php artisan bps:sinkron', function () {
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'K'])->id, 'name' => 'S']);
    Indicator::create(['subject_id' => $subjek->id, 'name' => 'Luas', 'data' => [], 'bps_source' => 'simdasi', 'bps_table_id' => ID_LUAS]);
    Indicator::create(['subject_id' => $subjek->id, 'name' => 'Manual', 'data' => []]);

    $this->artisan('bps:sinkron')
        ->expectsOutputToContain('[1/1]')
        ->expectsOutputToContain('1 indikator diperbarui, 0 gagal.')
        ->assertSuccessful();

    expect(Indicator::where('name', 'Luas')->sole()->data['rows'])->not->toBeEmpty()
        ->and(Indicator::where('name', 'Manual')->sole()->data)->toBe([]);
});

it('menguji koneksi dan kunci API lewat php artisan bps:cek', function () {
    palsukanBps();

    $this->artisan('bps:cek')
        ->expectsOutputToContain('Koneksi berhasil')
        ->expectsOutputToContain('Tabel Publikasi (SIMDASI)')
        ->assertSuccessful();

    $this->artisan('bps:cek', ['sumber' => 'simdasi', 'id' => ID_LUAS])
        ->expectsOutputToContain('Tahun berisi data: 2024, 2025')
        ->assertSuccessful();

    config(['services.bps.key' => null]);
    app()->forgetInstance(\App\Services\Bps\BpsApiClient::class);
    $this->artisan('bps:cek')->expectsOutputToContain('BPS_API_KEY')->assertFailed();
});

it('tidak menampilkan kunci API pada respons mentah bps:cek', function () {
    palsukanBps();

    $this->artisan('bps:cek', ['sumber' => 'simdasi', 'id' => ID_LUAS, '--mentah' => true, '--tahun' => 2024])
        ->expectsOutputToContain('/key/***/')
        ->doesntExpectOutputToContain('kunci-uji-rahasia-123')
        ->assertSuccessful();
});
