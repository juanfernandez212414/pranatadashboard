<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Perbarui semua indikator yang tertaut ke WebAPI BPS setiap hari (seluruh tahun, data terbaru).
// Berjalan bila penjadwal Laravel aktif: "php artisan schedule:work" saat pengembangan, atau cron /
// Task Scheduler yang menjalankan "php artisan schedule:run" setiap menit di server.
Schedule::command('bps:sinkron')->dailyAt('02:00')->withoutOverlapping();
