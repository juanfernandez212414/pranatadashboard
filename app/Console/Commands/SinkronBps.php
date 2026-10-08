<?php

namespace App\Console\Commands;

use App\Models\Indicator;
use App\Services\Bps\BpsApiClient;
use App\Services\Bps\BpsApiException;
use App\Services\Bps\SinkronisasiBps;
use Illuminate\Console\Command;

/**
 * Sinkronisasi tabel dinamis WebAPI BPS dari terminal (juga dijalankan harian oleh penjadwal Laravel):
 * 1. tabel dinamis baru di BPS dibuatkan indikator (cermin katalog),
 * 2. data semua indikator tertaut diambil ulang dari API (seluruh tahun).
 *
 *   php artisan bps:sinkron                  cermin katalog + perbarui data semua indikator
 *   php artisan bps:sinkron --hanya-katalog  cermin katalog saja (data diambil saat indikator dibuka)
 */
class SinkronBps extends Command
{
    protected $signature = 'bps:sinkron {--hanya-katalog : Hanya buat indikator untuk tabel dinamis baru, tanpa mengambil datanya}';

    protected $description = 'Buat indikator untuk semua tabel dinamis WebAPI BPS dan perbarui datanya (seluruh tahun)';

    public function handle(BpsApiClient $bps, SinkronisasiBps $sinkron): int
    {
        if (!$bps->siap()) {
            $this->error('Kunci API BPS belum diisi. Tambahkan BPS_API_KEY di file .env, lalu jalankan "php artisan config:clear".');

            return self::FAILURE;
        }

        try {
            $katalog = $sinkron->cerminkanKatalog(paksa: true);
        } catch (BpsApiException $e) {
            $this->error("Daftar tabel dinamis gagal dimuat: {$e->getMessage()}");

            return self::FAILURE;
        }
        if ($katalog === null) {
            $this->warn('Cermin katalog sedang dijalankan proses lain; dilewati.');
        } else {
            $this->info("Katalog tabel dinamis: {$katalog['baru']} indikator baru, {$katalog['ditautkan']} indikator lama ditautkan, {$katalog['tetap']} sudah ada.");
        }

        if ($this->option('hanya-katalog')) {
            return self::SUCCESS;
        }

        $indikator = Indicator::where('bps_source', SinkronisasiBps::SUMBER)->whereNotNull('bps_table_id')->orderBy('id')->get();
        $gagal = 0;
        $mulai = now()->getTimestamp();
        foreach ($indikator as $n => $i) {
            $nomor = sprintf('[%d/%d]', $n + 1, $indikator->count());
            try {
                $sinkron->perbarui($i, $mulai);
                $this->line("{$nomor} <info>✓</info> {$i->name}");
            } catch (BpsApiException $e) {
                $gagal++;
                $this->line("{$nomor} <error>✗</error> {$i->name}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(($indikator->count() - $gagal) . " indikator diperbarui, {$gagal} gagal.");

        return $gagal === 0 ? self::SUCCESS : self::FAILURE;
    }
}
