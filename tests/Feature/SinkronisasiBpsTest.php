<?php

// Uji tabel dinamis WebAPI BPS sebagai sumber data otomatis dashboard (SinkronisasiBps, bps:sinkron, bps:cek):
// semua tabel dinamis menjadi indikator tanpa impor manual, dan datanya diambil dari API saat dibuka.
//
// AMAN dijalankan kapan saja: tidak ada permintaan sungguhan ke WebAPI BPS (semua dibalas fixture lewat
// palsukanBps di tests/BantuanBps.php) dan database memakai SQLite di memori (phpunit.xml).

use App\Models\Category;
use App\Models\Indicator;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function judulKolom(Indicator $indikator): array
{
    return array_column($indikator->data['headers'] ?? [], 'value');
}

function jumlahPermintaanData(): int
{
    return collect(Http::recorded())->filter(fn ($r) => str_contains($r[0]->url(), 'model=data'))->count();
}

function urlDashboard(string $awalan, Indicator $indikator): string
{
    return "{$awalan}/dashboard?" . http_build_query([
        'category_id' => $indikator->subject->category_id,
        'subject_id' => $indikator->subject_id,
        'indicator_id' => $indikator->id,
    ]);
}

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('Tes sinkronisasi BPS hanya boleh berjalan di SQLite :memory: (lihat phpunit.xml).');
    }
    $this->artisan('migrate');

    Http::preventStrayRequests();
    config(['services.bps.key' => 'kunci-uji-rahasia-123', 'services.bps.domain' => '1273']);
    $this->withoutVite();

    $buat = fn (int $role, string $nama) => User::create([
        'name' => $nama,
        'email' => "{$nama}@uji.test",
        'password' => bcrypt('rahasia123'),
        'role_id' => $role,
    ]);
    $this->admin = $buat(1, 'admin');
    $this->pj = $buat(3, 'pj');
    $this->biasa = $buat(4, 'biasa');
});

// ===========================================
// --- SEMUA TABEL DINAMIS OTOMATIS MENJADI INDIKATOR ---
// ===========================================

it('membuat indikator untuk semua tabel dinamis BPS saat dashboard dibuka, tanpa impor manual', function () {
    palsukanBps();

    $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();

    $indikator = Indicator::with('subject.category')->orderBy('bps_table_id')->get();
    $penduduk = $indikator->firstWhere('bps_table_id', '31');
    expect($indikator->pluck('bps_table_id')->all())->toBe(['1', '106', '31', '32'])
        ->and($indikator->pluck('bps_source')->unique()->all())->toBe(['dinamis'])
        ->and($indikator->pluck('data')->filter()->all())->toBe([]) // data diambil saat dibuka
        ->and($penduduk->name)->toBe('Penduduk per kecamatan')
        ->and($penduduk->unit)->toBe('Jiwa')
        // Kategori & subjek mengikuti klasifikasi CSA di situs BPS.
        ->and($penduduk->subject->name)->toBe('Kependudukan dan Migrasi')
        ->and($penduduk->subject->category->name)->toBe('Statistik Demografi dan Sosial')
        ->and($indikator->firstWhere('bps_table_id', '1')->subject->category->name)->toBe('Statistik Ekonomi');

    // Daftar tabel hanya dicek ulang berkala, tidak setiap halaman dibuka.
    $jumlah = count(Http::recorded());
    $this->actingAs($this->pj)->get('/penanggungjawab/dashboard')->assertOk();
    expect(count(Http::recorded()))->toBe($jumlah)
        ->and(Indicator::count())->toBe(4);
});

it('tidak membuat Pengguna menunggu daftar tabel dari API saat membuka dashboard', function () {
    palsukanBps();

    $this->actingAs($this->biasa)->get('/pengguna/dashboard')->assertOk();

    Http::assertNothingSent();
});

it('membaca katalog lebih dari 20 halaman (ratusan tabel dinamis)', function () {
    $halaman = fixtureBps('var_daftar.json');
    palsukanBps(['model=var' => function (Request $r) use ($halaman) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
        $nomor = (int) ($q['page'] ?? 1);
        $isi = $halaman;
        $isi['data'][0] = ['page' => $nomor, 'pages' => 30, 'per_page' => 10, 'count' => 1, 'total' => 30];
        $isi['data'][1] = [array_merge($halaman['data'][1][2], ['var_id' => 1000 + $nomor, 'title' => "Tabel {$nomor}"])];

        return Http::response($isi);
    }]);

    $this->artisan('bps:sinkron', ['--hanya-katalog' => true])->assertSuccessful();

    expect(Indicator::count())->toBe(30);
});

it('mengecek tabel dinamis baru lagi setelah selang waktu katalog', function () {
    palsukanBps();
    $this->actingAs($this->admin)->get('/admin/dashboard');
    Indicator::where('bps_table_id', '32')->delete(); // seolah-olah tabel 32 baru muncul di BPS

    $this->travel(1441)->minutes(); // BPS_KATALOG_MENIT bawaan: sehari
    $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();

    expect(Indicator::where('bps_table_id', '32')->count())->toBe(1);
});

it('tidak menimpa indikator lama bernama sama, kecuali Admin/PJ memilih memakai data API', function () {
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'Sosial dan Kependudukan'])->id, 'name' => 'Penduduk']);
    $lama = Indicator::create(['subject_id' => $subjek->id, 'name' => 'PENDUDUK PER  KECAMATAN', 'unit' => 'Orang',
        'data' => ['headers' => [['value' => 'Lama']], 'rows' => [[['value' => '1']]]]]);

    // Cermin katalog tidak menyentuh indikator lama dan tidak membuat duplikatnya.
    $this->actingAs($this->admin)->get('/admin/data-bps')
        ->assertOk()
        ->assertSee('1 indikator lama bernama sama dengan tabel dinamis BPS')
        ->assertSee('Pakai data API');
    expect(Indicator::count())->toBe(4)
        ->and($lama->fresh()->bps_source)->toBeNull()
        ->and(Indicator::where('bps_table_id', '31')->exists())->toBeFalse();

    $this->actingAs($this->pj)->post("/penanggungjawab/data-bps/tautkan/{$lama->id}")->assertSessionHas('success');
    $lama->refresh();
    expect([$lama->bps_source, $lama->bps_table_id, $lama->subject_id, $lama->unit, $lama->name])
        ->toBe(['dinamis', '31', $subjek->id, 'Orang', 'PENDUDUK PER  KECAMATAN']);

    // Saat dibuka, data lama diganti data API.
    $this->actingAs($this->admin)->get(urlDashboard('/admin', $lama->load('subject')))->assertOk();
    expect(judulKolom($lama->fresh()))->toBe(['Kecamatan', '2020', '2023', '2024']);

    $this->actingAs($this->biasa)->post("/admin/data-bps/tautkan/{$lama->id}")->assertForbidden();
});

it('memakai subjek PRANATA yang namanya sama dengan subjek BPS', function () {
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'Sosial dan Kependudukan'])->id, 'name' => 'Kependudukan']);

    $this->actingAs($this->admin)->get('/admin/dashboard');

    // Var 31 & 32 bersubjek lama "Kependudukan".
    expect(Indicator::whereIn('bps_table_id', ['31', '32'])->pluck('subject_id')->unique()->all())->toBe([$subjek->id]);
});

it('tidak membuat ulang indikator tabel dinamis yang dihapus pengguna', function () {
    palsukanBps();
    $this->actingAs($this->admin)->get('/admin/dashboard');
    $gini = Indicator::where('bps_table_id', '106')->sole();

    $this->actingAs($this->admin)->delete("/admin/indicators/{$gini->id}")->assertRedirect();
    $this->actingAs($this->admin)->post('/admin/data-bps/perbarui-katalog')->assertSessionHas('success');

    expect(Indicator::where('bps_table_id', '106')->exists())->toBeFalse()
        ->and(DB::table('bps_tabel_diabaikan')->pluck('bps_table_id')->all())->toBe(['106'])
        ->and(Indicator::count())->toBe(3);
});

it('tidak membuat ulang indikator API bila subjeknya dihapus, dan bisa ditampilkan lagi', function () {
    palsukanBps();
    $this->actingAs($this->admin)->get('/admin/dashboard');
    $subjek = Indicator::where('bps_table_id', '31')->sole()->subject;

    $this->actingAs($this->admin)->delete("/admin/subjects/{$subjek->id}")->assertRedirect();
    $this->actingAs($this->admin)->post('/admin/data-bps/perbarui-katalog')->assertSessionHas('success');
    expect(Indicator::whereIn('bps_table_id', ['31', '32'])->exists())->toBeFalse();

    $this->actingAs($this->admin)->get('/admin/data-bps')->assertSee('2 tabel disembunyikan karena indikatornya dihapus');
    $this->actingAs($this->admin)->post('/admin/data-bps/tabel-diabaikan/31/pulihkan')->assertSessionHas('success');
    expect(Indicator::where('bps_table_id', '31')->exists())->toBeTrue()
        ->and(Indicator::where('bps_table_id', '32')->exists())->toBeFalse();
});

it('hanya menyimpan nama, subjek, dan satuan saat indikator API diedit di Kelola Data', function () {
    palsukanBps();
    $this->actingAs($this->admin)->get('/admin/dashboard');
    $penduduk = Indicator::where('bps_table_id', '31')->sole();

    $this->actingAs($this->pj)->patch("/penanggungjawab/indicators/{$penduduk->id}", [
        'subject_id' => $penduduk->subject_id, 'name' => 'Jumlah Penduduk Kecamatan', 'unit' => 'Orang',
        'matrix_data' => json_encode(['headers' => [['value' => 'Manual']], 'rows' => [[['value' => '1']]]]),
    ])->assertSessionHas('success', 'Indikator berhasil diupdate. Data tabelnya tetap diambil otomatis dari WebAPI BPS.');

    $penduduk->refresh();
    expect([$penduduk->name, $penduduk->unit, $penduduk->data, $penduduk->bps_table_id])
        ->toBe(['Jumlah Penduduk Kecamatan', 'Orang', null, '31']);
});

it('menyebut jenis grafik yang disarankan BPS tanpa mengubah susunan grafik dashboard', function () {
    palsukanBps();
    $this->actingAs($this->admin)->get('/admin/dashboard');
    $penduduk = Indicator::with('subject')->where('bps_table_id', '31')->sole();
    expect($penduduk->bps_chart)->toBe('bar'); // graph_name var 31 di fixture

    $this->actingAs($this->admin)->get(urlDashboard('/admin', $penduduk))
        ->assertSee('grafik yang disarankan BPS: Batang')
        ->assertViewHas('indicatorsWithVisualization', fn (array $vis) => $vis[0]['available_types'][0] === 'choropleth');
});

it('Lihat Data dan ekspor tetap terbuka bila data indikator API belum bisa diambil', function () {
    Http::fake(['*' => Http::response('Service Unavailable', 503)]);
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'K'])->id, 'name' => 'S']);
    $indikator = Indicator::create(['subject_id' => $subjek->id, 'name' => 'Penduduk', 'data' => null,
        'bps_source' => 'dinamis', 'bps_table_id' => '31']);

    $this->actingAs($this->biasa)->get("/pengguna/lihatdata/{$indikator->id}")
        ->assertOk()
        ->assertSee('Data terbaru dari WebAPI BPS gagal diambil')
        ->assertSee('Data indikator ini belum tersedia.');
    $this->actingAs($this->biasa)->get("/pengguna/indicators/{$indikator->id}/export/excel")->assertOk();
    $this->actingAs($this->biasa)->get("/pengguna/indicators/{$indikator->id}/export/pdf")->assertOk();
});

it('dashboard tetap tampil tanpa menghubungi API bila kunci API kosong', function () {
    Http::fake();
    config(['services.bps.key' => null]);

    $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();

    Http::assertNothingSent();
    expect(Indicator::count())->toBe(0);
});

it('dashboard tetap tampil bila WebAPI BPS sedang bermasalah', function () {
    Http::fake(['*' => Http::response('Service Unavailable', 503)]);

    $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();
    $jumlah = count(Http::recorded());
    $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();

    // Setelah gagal, tidak dicoba lagi pada setiap halaman.
    expect(count(Http::recorded()))->toBe($jumlah);
});

// ===========================================
// --- DATA DIAMBIL DARI API SAAT DIBUKA ---
// ===========================================

it('mengambil seluruh tahun dari API saat indikator dibuka di dashboard', function () {
    palsukanBps();
    $this->actingAs($this->admin)->get('/admin/dashboard');
    $penduduk = Indicator::with('subject')->where('bps_table_id', '31')->sole();

    $this->actingAs($this->admin)->get(urlDashboard('/admin', $penduduk))
        ->assertOk()
        ->assertSee('Sumber: Tabel Dinamis WebAPI BPS')
        ->assertViewHas('galatApiBps', null)
        ->assertViewHas('indicatorsWithVisualization', function (array $vis) {
            expect($vis)->toHaveCount(1)
                ->and($vis[0]['available_types'])->toContain('line')
                ->and(collect($vis[0]['parsed_data'])->pluck('Tahun')->unique()->sort()->values()->all())->toBe(['2020', '2023', '2024']);

            return true;
        });

    $penduduk->refresh();
    expect(judulKolom($penduduk))->toBe(['Kecamatan', '2020', '2023', '2024'])
        ->and($penduduk->bps_synced_at)->not->toBeNull();

    // 16 tahun tersedia diminta 2 tahun per permintaan.
    expect(jumlahPermintaanData())->toBe(8);
});

it('membaca data tersimpan tanpa menghubungi API bila datanya sudah diimpor', function () {
    palsukanBps();
    $this->actingAs($this->admin)->get('/admin/dashboard');
    $penduduk = Indicator::with('subject')->where('bps_table_id', '31')->sole();
    $this->actingAs($this->admin)->get(urlDashboard('/admin', $penduduk));
    $awal = jumlahPermintaanData();

    $this->travel(30)->days();
    $this->actingAs($this->biasa)->get(urlDashboard('/pengguna', $penduduk))->assertOk();
    $this->actingAs($this->biasa)->get("/pengguna/lihatdata/{$penduduk->id}")->assertOk();
    expect(jumlahPermintaanData())->toBe($awal);
});

it('mengambil ulang data yang lebih tua dari BPS_SEGAR_MENIT saat dibuka bila diatur', function () {
    config(['services.bps.segar_menit' => 360]);
    palsukanBps();
    $this->actingAs($this->admin)->get('/admin/dashboard');
    $penduduk = Indicator::with('subject')->where('bps_table_id', '31')->sole();
    $this->actingAs($this->admin)->get(urlDashboard('/admin', $penduduk));
    $awal = jumlahPermintaanData();

    $this->actingAs($this->biasa)->get(urlDashboard('/pengguna', $penduduk))->assertOk();
    expect(jumlahPermintaanData())->toBe($awal);

    $this->travel(361)->minutes();
    $this->actingAs($this->biasa)->get(urlDashboard('/pengguna', $penduduk))->assertOk();
    expect(jumlahPermintaanData())->toBe($awal * 2);
});

it('tidak menghubungi API lagi selama jeda setelah pengambilan data gagal', function () {
    Http::fake(['*' => Http::response('Service Unavailable', 503)]);
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'K'])->id, 'name' => 'S']);
    $indikator = Indicator::create(['subject_id' => $subjek->id, 'name' => 'Penduduk', 'data' => null,
        'bps_source' => 'dinamis', 'bps_table_id' => '31']);

    $this->actingAs($this->biasa)->get("/pengguna/lihatdata/{$indikator->id}")->assertOk();
    $jumlah = count(Http::recorded());
    $this->actingAs($this->biasa)->get("/pengguna/lihatdata/{$indikator->id}")
        ->assertOk()
        ->assertSee('Data terbaru dari WebAPI BPS gagal diambil')
        ->assertDontSee('HTTP 503'); // rincian teknis hanya untuk Admin/PJ
    expect(count(Http::recorded()))->toBe($jumlah);

    $this->travel(31)->minutes();
    $this->actingAs($this->biasa)->get("/pengguna/lihatdata/{$indikator->id}")->assertOk();
    expect(count(Http::recorded()))->toBeGreaterThan($jumlah);
});

it('menampilkan data terakhir yang tersimpan bila API gagal saat indikator dibuka', function () {
    config(['services.bps.segar_menit' => 360]);
    $gagal = false;
    palsukanBps(['model=data' => function (Request $r) use (&$gagal) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

        return $gagal ? Http::response('Server Error', 500) : Http::response(dataVar31(explode(';', $q['th'])) ?? 'null');
    }]);
    $this->actingAs($this->admin)->get('/admin/dashboard');
    $penduduk = Indicator::with('subject')->where('bps_table_id', '31')->sole();
    $this->actingAs($this->admin)->get(urlDashboard('/admin', $penduduk));

    $gagal = true;
    $this->travel(361)->minutes();
    $this->actingAs($this->admin)->get(urlDashboard('/admin', $penduduk))
        ->assertOk()
        ->assertSee('Data terbaru dari WebAPI BPS gagal diambil')
        ->assertViewHas('indicatorsWithVisualization', fn (array $vis) => count($vis) === 1);

    expect(judulKolom($penduduk->fresh()))->toBe(['Kecamatan', '2020', '2023', '2024']);
});

it('mengambil data dari API saat Lihat Data dibuka dan saat diekspor', function () {
    config(['services.bps.segar_menit' => 360]);
    palsukanBps();
    $this->actingAs($this->admin)->get('/admin/dashboard');
    $penduduk = Indicator::where('bps_table_id', '32')->sole();

    $this->actingAs($this->biasa)->get("/pengguna/lihatdata/{$penduduk->id}")
        ->assertOk()
        ->assertSee('SIANTAR MARIHAT');
    expect(judulKolom($penduduk->fresh()))->toBe(['Kecamatan', 'Laki-laki', 'Perempuan', 'Jumlah']);

    $this->travel(361)->minutes();
    $sebelum = jumlahPermintaanData();
    $this->actingAs($this->biasa)->get("/pengguna/indicators/{$penduduk->id}/export/excel")->assertOk();
    expect(jumlahPermintaanData())->toBeGreaterThan($sebelum);
});

it('mengirim data terbaru dari API ke layanan narasi AI', function () {
    $_SERVER['HUGGINGFACE_API_URL'] = $_ENV['HUGGINGFACE_API_URL'] = 'https://ai-uji.test';
    palsukanBps(['ai-uji.test' => fn () => Http::response(['narrative_result' => 'Narasi uji'])]);
    $this->actingAs($this->admin)->get('/admin/dashboard');
    $penduduk = Indicator::where('bps_table_id', '31')->sole();

    try {
        $this->actingAs($this->pj)->postJson('/penanggungjawab/dashboard/generate-narrative', ['indicator_id' => $penduduk->id])
            ->assertOk()
            ->assertJsonPath('narrative', 'Narasi uji');
    } finally {
        unset($_SERVER['HUGGINGFACE_API_URL'], $_ENV['HUGGINGFACE_API_URL']);
    }

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'ai-uji.test/generate-narrative')
        && array_column($r['data_json']['headers'], 'value') === ['Kecamatan', '2020', '2023', '2024']);
});

it('mencatat tautan API saat tabel dinamis disimpan dari tab Tabel Dinamis', function () {
    config(['services.bps.segar_menit' => 360]);
    palsukanBps();
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'K'])->id, 'name' => 'S']);

    $this->actingAs($this->admin)->post('/admin/data-bps/tabel', [
        'var' => 31, 'tahun' => ['2024'], 'baris' => [11, 10],
        'subject_id' => $subjek->id, 'name' => 'Penduduk Dua Kecamatan',
    ])->assertRedirect();

    $indikator = Indicator::where('name', 'Penduduk Dua Kecamatan')->sole();
    expect($indikator->only(['bps_source', 'bps_table_id', 'bps_options']))
        ->toBe(['bps_source' => 'dinamis', 'bps_table_id' => '31', 'bps_options' => ['baris' => [10, 11]]]);

    // Saat diperbarui dari API, saringan judul baris tetap dipakai dan seluruh tahun diambil.
    $this->travel(361)->minutes();
    $this->actingAs($this->admin)->get("/admin/lihatdata/{$indikator->id}")->assertOk();
    $indikator->refresh();
    expect(collect($indikator->data['rows'])->map(fn ($b) => $b[0]['value'])->all())->toBe(['SIANTAR MARIHAT', 'SIANTAR MARIMBUN'])
        ->and(judulKolom($indikator))->toBe(['Kecamatan', '2020', '2023', '2024']);
});

// ===========================================
// --- HALAMAN DATA API BPS & PERINTAH ARTISAN ---
// ===========================================

it('menampilkan ringkasan dan tombol Impor Semua Tabel Dinamis di halaman Data API BPS', function () {
    palsukanBps();

    $this->actingAs($this->admin)->get('/admin/data-bps')
        ->assertOk()
        ->assertSee('Tabel dinamis BPS untuk dashboard')
        ->assertSee('4 indikator tertaut ke tabel dinamis WebAPI BPS')
        ->assertSee('0 di antaranya sudah berisi data')
        ->assertSee('Impor Semua Tabel Dinamis')
        ->assertDontSee('Sinkronisasi Semua Tabel');

    // Tab Publikasi tidak memuat daftar tabel dinamis.
    $sebelum = count(Http::recorded());
    $this->travel(2)->days();
    $this->actingAs($this->admin)->get('/admin/data-bps?tab=publikasi')->assertOk();
    expect(collect(Http::recorded())->slice($sebelum)->filter(fn ($r) => str_contains($r[0]->url(), 'model=var'))->count())->toBe(0);
});

it('mengimpor semua tabel dinamis beserta datanya lewat tombol Impor Semua (satu indikator per permintaan)', function () {
    palsukanBps();

    $daftar = $this->actingAs($this->pj)->postJson('/penanggungjawab/data-bps/impor-semua')
        ->assertOk()
        ->assertJsonPath('katalog.baru', 4)
        ->json('indikator');
    expect($daftar)->toHaveCount(4);

    $penduduk = collect($daftar)->firstWhere('nama', 'Penduduk per kecamatan');
    $this->actingAs($this->pj)->postJson("/penanggungjawab/data-bps/impor-semua/{$penduduk['id']}")
        ->assertOk()
        ->assertJsonPath('pesan', 'Data "Penduduk per kecamatan" tersimpan.');
    expect(judulKolom(Indicator::find($penduduk['id'])))->toBe(['Kecamatan', '2020', '2023', '2024']);

    // Tabel yang belum berisi data di BPS dilaporkan gagal tanpa menghentikan proses.
    $inflasi = collect($daftar)->firstWhere('nama', 'Inflasi');
    $this->actingAs($this->pj)->postJson("/penanggungjawab/data-bps/impor-semua/{$inflasi['id']}")
        ->assertStatus(502)
        ->assertJsonPath('berhenti', false);

    $this->actingAs($this->biasa)->postJson('/admin/data-bps/impor-semua')->assertForbidden();
    $this->actingAs($this->biasa)->postJson("/admin/data-bps/impor-semua/{$penduduk['id']}")->assertForbidden();
});

it('meminta browser berhenti mengimpor bila WebAPI BPS memblokir (HTTP 403)', function () {
    $diblokir = false;
    palsukanBps(['model=th' => function (Request $r) use (&$diblokir) {
        return $diblokir ? Http::response('Forbidden', 403) : Http::response(fixtureBps('th_32.json'));
    }]);
    $this->actingAs($this->admin)->get('/admin/dashboard');
    $diblokir = true;

    $this->actingAs($this->admin)->postJson('/admin/data-bps/impor-semua/' . Indicator::where('bps_table_id', '32')->value('id'))
        ->assertStatus(502)
        ->assertJsonPath('berhenti', true);
});

it('tidak menimpa atau menggandakan indikator otomatis lewat tab Tabel Dinamis', function () {
    palsukanBps();
    $this->actingAs($this->admin)->get('/admin/dashboard');
    $cermin = Indicator::where('bps_table_id', '31')->sole();
    $subjek = $cermin->subject_id;

    // Form hasil tidak lagi menawarkan "perbarui" indikator otomatis sebagai bawaan.
    $this->actingAs($this->admin)->get('/admin/data-bps/dinamis/hasil?data[0][var]=31&data[0][baris][]=10&data[0][baris][]=11')
        ->assertOk()
        ->assertSee("mode: 'baru'", false);

    $this->actingAs($this->admin)->post('/admin/data-bps/tabel', [
        'var' => 31, 'baris' => [10, 11], 'subject_id' => $subjek, 'name' => 'Penduduk per kecamatan', 'indicator_id' => $cermin->id,
    ])->assertSessionHas('error');
    $this->actingAs($this->admin)->post('/admin/data-bps/tabel', [
        'var' => 31, 'tahun' => ['2024'], 'subject_id' => $subjek, 'name' => 'Penduduk 2024',
    ])->assertSessionHas('error');

    expect(Indicator::where('bps_table_id', '31')->count())->toBe(1)
        ->and($cermin->fresh()->bps_options)->toBeNull();

    // Potongan tabel (judul baris tertentu) tetap bisa disimpan sebagai indikator baru.
    $this->actingAs($this->admin)->post('/admin/data-bps/tabel', [
        'var' => 31, 'baris' => [10, 11], 'subject_id' => $subjek, 'name' => 'Penduduk Dua Kecamatan',
    ])->assertRedirect()->assertSessionHas('success');
    $this->actingAs($this->admin)->get('/admin/data-bps')->assertOk();
    expect(Indicator::where('bps_table_id', '31')->count())->toBe(2)
        ->and(Indicator::where('bps_table_id', '31')->whereNull('bps_options')->count())->toBe(1);
});

it('memberi tahu bila data indikator API belum bisa diambil, bukan menyebut format tidak didukung', function () {
    Http::fake(['*' => Http::response('Service Unavailable', 503)]);
    $subjek = Subject::create(['category_id' => Category::create(['name' => 'K'])->id, 'name' => 'S']);
    $indikator = Indicator::create(['subject_id' => $subjek->id, 'name' => 'Penduduk', 'data' => null,
        'bps_source' => 'dinamis', 'bps_table_id' => '31']);

    $this->actingAs($this->biasa)->get(urlDashboard('/pengguna', $indikator->load('subject')))
        ->assertOk()
        ->assertSee('Data indikator ini belum bisa diambil dari WebAPI BPS')
        ->assertSee('Data indikator ini belum tersedia dari WebAPI BPS.')
        ->assertDontSee('data terakhir yang tersimpan')
        ->assertDontSee('Format tabel data pada indikator ini');
});

it('membuat indikator semua tabel dinamis dan mengambil datanya lewat php artisan bps:sinkron', function () {
    palsukanBps();

    // Var 1 dan 106 tidak berisi data di fixture, jadi gagal diperbarui.
    $this->artisan('bps:sinkron')
        ->expectsOutputToContain('4 indikator baru, 0 sudah ada, 0 bernama sama')
        ->expectsOutputToContain('Penduduk per kecamatan')
        ->expectsOutputToContain('2 indikator diperbarui, 2 gagal.')
        ->assertFailed();

    expect(judulKolom(Indicator::where('bps_table_id', '31')->sole()))->toBe(['Kecamatan', '2020', '2023', '2024']);
});

it('menghentikan bps:sinkron bila WebAPI BPS menolak permintaan (HTTP 403)', function () {
    $diblokir = false;
    palsukanBps(['model=data' => function () use (&$diblokir) {
        return $diblokir ? Http::response('Forbidden', 403) : Http::response('null');
    }]);
    $this->artisan('bps:sinkron', ['--hanya-katalog' => true]);
    $diblokir = true;

    $this->artisan('bps:sinkron')
        ->expectsOutputToContain('Dihentikan: WebAPI BPS menolak permintaan')
        ->expectsOutputToContain('0 indikator diperbarui, 1 gagal, 3 belum diproses.')
        ->assertFailed();
});

it('hanya mencerminkan katalog lewat php artisan bps:sinkron --hanya-katalog', function () {
    palsukanBps();

    $this->artisan('bps:sinkron', ['--hanya-katalog' => true])->assertSuccessful();

    expect(Indicator::count())->toBe(4)->and(jumlahPermintaanData())->toBe(0);
});

it('menguji koneksi, daftar, dan isi tabel dinamis lewat php artisan bps:cek', function () {
    palsukanBps();

    $this->artisan('bps:cek')
        ->expectsOutputToContain('Koneksi berhasil')
        ->expectsOutputToContain('Tabel dinamis di BPS: 4')
        ->assertSuccessful();
    $this->artisan('bps:cek', ['--daftar' => true])->expectsOutputToContain('4 tabel dinamis.')->assertSuccessful();
    $this->artisan('bps:cek', ['var' => '31'])->expectsOutputToContain('Penduduk per kecamatan')->assertSuccessful();
    $this->artisan('bps:cek', ['var' => '31', '--mentah' => true, '--tahun' => '2024'])
        ->expectsOutputToContain('key=***')
        ->doesntExpectOutputToContain('kunci-uji-rahasia-123')
        ->assertSuccessful();

    config(['services.bps.key' => null]);
    app()->forgetInstance(\App\Services\Bps\BpsApiClient::class);
    $this->artisan('bps:cek')->expectsOutputToContain('BPS_API_KEY')->assertFailed();
});
