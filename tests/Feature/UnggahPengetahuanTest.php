<?php

// Uji unggah dokumen basis pengetahuan: hanya PDF yang diterima, dan penolakan tampil sebagai pop-up "Gagal!".
// Database memakai SQLite di memori (phpunit.xml), panggilan HTTP ke layanan AI dipalsukan, dan folder
// storage dialihkan ke folder sementara sehingga PDF asli tidak tersentuh.

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('Tes unggah pengetahuan hanya boleh berjalan di SQLite :memory: (lihat phpunit.xml).');
    }
    $this->artisan('migrate');

    Http::preventStrayRequests();
    Http::fake();

    $this->storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pranata-uji-unggah-' . uniqid();
    File::ensureDirectoryExists($this->storage . '/app/public/dokumen_bps');
    $this->app->useStoragePath($this->storage);

    $this->withoutVite();

    $this->admin = User::create([
        'name' => 'admin',
        'email' => 'admin@uji.test',
        'password' => bcrypt('rahasia123'),
        'role_id' => 1,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->storage);
});

it('menolak berkas selain PDF dan menampilkan pesannya', function (string $nama, string $mime) {
    $berkas = UploadedFile::fake()->create($nama, 20, $mime);

    $this->actingAs($this->admin)->from('/admin/pengetahuan')
        ->post('/admin/pengetahuan/upload', ['dokumen' => [$berkas]])
        ->assertRedirect('/admin/pengetahuan')
        ->assertSessionHasErrors(['dokumen.0' => 'Semua file yang diunggah harus berformat PDF.']);

    // Pesan tampil di pop-up "Gagal!" setelah halaman dimuat ulang.
    $this->actingAs($this->admin)->followingRedirects()->from('/admin/pengetahuan')
        ->post('/admin/pengetahuan/upload', ['dokumen' => [$berkas]])
        ->assertSee('Gagal!')
        ->assertSee('Semua file yang diunggah harus berformat PDF.')
        ->assertDontSee('Gagal: Semua');

    expect(File::files($this->storage . '/app/public/dokumen_bps'))->toBeEmpty();
})->with([
    ['laporan.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['foto.jpg', 'image/jpeg'],
]);

it('mengirim batas ukuran unggahan ke halaman untuk diperiksa di browser', function () {
    $this->actingAs($this->admin)->get('/admin/pengetahuan')
        ->assertOk()
        ->assertViewHas('batasUnggah', fn ($batas) => $batas['per_file'] > 0
            && $batas['per_file'] <= 102400 * 1024
            && $batas['total'] > 0)
        ->assertSee('periksaBerkasUnggahan', false);
});

it('menjelaskan penolakan berkas yang melebihi batas unggah PHP', function () {
    // Berkas yang melebihi upload_max_filesize sampai ke Laravel tanpa isi (UPLOAD_ERR_INI_SIZE).
    $sumber = tempnam(sys_get_temp_dir(), 'pdf');
    $berkas = new UploadedFile($sumber, 'besar.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);

    $this->actingAs($this->admin)->from('/admin/pengetahuan')
        ->post('/admin/pengetahuan/upload', ['dokumen' => [$berkas]])
        ->assertSessionHasErrors(['dokumen.0' => 'File gagal diunggah karena ukurannya melebihi batas server.']);
});

it('menerima berkas PDF', function () {
    $this->actingAs($this->admin)->from('/admin/pengetahuan')
        ->post('/admin/pengetahuan/upload', ['dokumen' => [UploadedFile::fake()->create('publikasi.pdf', 20, 'application/pdf')]])
        ->assertRedirect('/admin/pengetahuan')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(File::exists($this->storage . '/app/public/dokumen_bps/publikasi.pdf'))->toBeTrue();
});
