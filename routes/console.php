<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Setiap hari: tabel dinamis baru di WebAPI BPS dibuatkan indikator. Datanya sendiri diambil dari API saat
// indikator dibuka. Berjalan bila penjadwal Laravel aktif: "php artisan schedule:work" saat pengembangan,
// atau cron / Task Scheduler yang menjalankan "php artisan schedule:run" setiap menit di server.
Schedule::command('bps:sinkron --hanya-katalog')->dailyAt('02:00')->withoutOverlapping();
