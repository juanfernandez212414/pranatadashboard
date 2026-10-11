<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tabel dinamis WebAPI BPS TIDAK dijadwalkan: data tabel hanya diperbarui ketika Admin/PJ menekan tombol
// "Impor Semua Tabel Dinamis" di menu Data API BPS (atau menjalankan "php artisan bps:sinkron" secara manual),
// agar data dan narasi yang tampil tidak berubah tanpa sepengetahuan petugas.

// Setiap malam pukul 03.00 WIB: publikasi BPS baru (rilis sejak BPS_PUBLIKASI_SEJAK, judul memuat
// BPS_PUBLIKASI_KATA bila diisi) diunduh dari WebAPI BPS dan dilatihkan ke basis pengetahuan AI.
// Berjalan bila penjadwal Laravel aktif: "php artisan schedule:work" saat pengembangan, atau cron / Task
// Scheduler yang menjalankan "php artisan schedule:run" setiap menit di server.
Schedule::command('bps:publikasi')->dailyAt('03:00')->timezone('Asia/Jakarta')->withoutOverlapping();
