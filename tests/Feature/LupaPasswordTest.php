<?php

// Uji pesan halaman Lupa Password: email tidak terdaftar, permintaan ulang terlalu cepat, dan email gagal terkirim.
// Database memakai SQLite di memori (phpunit.xml); tidak ada email sungguhan yang dikirim.

use App\Models\User;
use App\Notifications\CustomResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('Tes lupa password hanya boleh berjalan di SQLite :memory: (lihat phpunit.xml).');
    }
    $this->artisan('migrate');

    $this->user = User::create([
        'name' => 'pengguna',
        'email' => 'pengguna@uji.test',
        'password' => bcrypt('rahasia123'),
        'role_id' => 4,
    ]);
});

it('menolak email yang tidak terdaftar', function () {
    Notification::fake();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'tidakada@uji.test'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHasErrors(['email' => 'Tidak dapat menemukan pengguna dengan alamat email tersebut.']);

    Notification::assertNothingSent();
});

it('mengirim link lalu meminta menunggu bila diminta ulang terlalu cepat', function () {
    Notification::fake();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'pengguna@uji.test'])
        ->assertSessionHas('status', 'Link reset password telah dikirim ke email Anda!');

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'pengguna@uji.test'])
        ->assertSessionHasErrors(['email' => 'Link reset password baru saja dikirim. Periksa kotak masuk atau folder spam, lalu tunggu 1 menit sebelum meminta lagi.']);

    Notification::assertSentToTimes($this->user, CustomResetPassword::class, 1);
});

it('memberi pesan jelas dan bisa langsung dicoba lagi bila email gagal terkirim', function () {
    // Server SMTP yang tidak ada: pengiriman pasti gagal.
    config([
        'mail.mailers.rusak' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 2],
        'mail.default' => 'rusak',
    ]);

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'pengguna@uji.test'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHasErrors(['email' => 'Email reset password gagal dikirim. Silakan coba lagi beberapa saat lagi atau hubungi Admin PRANATA.']);

    // Token dihapus, jadi percobaan berikutnya tidak tertahan jeda 1 menit.
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});
