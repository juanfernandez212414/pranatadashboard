<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Setiap malam pukul 02.00 WIB: tabel dinamis baru di WebAPI BPS dibuatkan indikator dan data seluruh tahun
// semua indikator tabel dinamis diambil ulang dari API (sama dengan tombol "Impor Semua Tabel Dinamis").
// Berjalan bila penjadwal Laravel aktif: "php artisan schedule:work" saat pengembangan, atau cron / Task
// Scheduler yang menjalankan "php artisan schedule:run" setiap menit di server.
Schedule::command('bps:sinkron')->dailyAt('02:00')->timezone('Asia/Jakarta')->withoutOverlapping();
