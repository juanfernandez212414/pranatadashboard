<?php

namespace App\Console\Commands;

use App\Models\Indicator;
use App\Models\Subject;
use App\Services\Bps\BpsApiClient;
use App\Services\Bps\BpsApiException;
use App\Services\Bps\SinkronisasiBps;
use Illuminate\Console\Command;

/**
 * Sinkronisasi indikator dengan WebAPI BPS dari terminal (juga dijalankan harian oleh penjadwal Laravel).
 *
 *   php artisan bps:sinkron                          perbarui semua indikator yang tertaut ke API
 *   php artisan bps:sinkron --impor=simdasi          impor semua tabel publikasi SIMDASI
 *   php artisan bps:sinkron --impor=simdasi,dinamis  impor beberapa sumber sekaligus
 *   php artisan bps:sinkron --impor=semua            impor semua sumber (termasuk tabel statis)
 */
class SinkronBps extends Command
{
    protected $signature = 'bps:sinkron
        {--impor= : Sumber tabel yang diimpor semuanya: simdasi, dinamis, statis, atau semua (pisahkan dengan koma)}
        {--subjek= : ID subjek tujuan indikator baru (bawaan: mengikuti kategori & subjek BPS)}';

    protected $description = 'Impor tabel WebAPI BPS (seluruh tahun) menjadi indikator, atau perbarui indikator yang tertaut ke API';

    public function handle(BpsApiClient $bps, SinkronisasiBps $sinkron): int
    {
        if (!$bps->siap()) {
            $this->error('Kunci API BPS belum diisi. Tambahkan BPS_API_KEY di file .env, lalu jalankan "php artisan config:clear".');

            return self::FAILURE;
        }

        $subjek = $this->option('subjek');
        if ($subjek !== null && !Subject::whereKey($subjek)->exists()) {
            $this->error("Subjek dengan ID {$subjek} tidak ditemukan.");

            return self::FAILURE;
        }

        return $this->option('impor') !== null
            ? $this->impor($sinkron, $subjek !== null ? (int) $subjek : null)
            : $this->perbaruiSemua($sinkron);
    }

    private function perbaruiSemua(SinkronisasiBps $sinkron): int
    {
        $indikator = Indicator::dariBps()->orderBy('id')->get();
        if ($indikator->isEmpty()) {
            $this->info('Belum ada indikator yang tertaut ke WebAPI BPS. Impor tabel dulu, misalnya: php artisan bps:sinkron --impor=simdasi');

            return self::SUCCESS;
        }

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

    private function impor(SinkronisasiBps $sinkron, ?int $subjek): int
    {
        $sumber = array_filter(array_map('trim', explode(',', strtolower((string) $this->option('impor')))));
        if (in_array('semua', $sumber, true)) {
            $sumber = array_keys(SinkronisasiBps::SUMBER);
        }
        if ($sumber === [] || array_diff($sumber, array_keys(SinkronisasiBps::SUMBER))) {
            $this->error('Pilihan --impor tidak dikenal. Gunakan: ' . implode(', ', array_keys(SinkronisasiBps::SUMBER)) . ', atau semua.');

            return self::FAILURE;
        }

        $jumlah = ['baru' => 0, 'ditautkan' => 0, 'diperbarui' => 0, 'gagal' => 0];
        foreach ($sumber as $s) {
            $this->newLine();
            $this->info('== ' . SinkronisasiBps::SUMBER[$s] . ' ==');
            try {
                $katalog = $sinkron->katalog($s);
            } catch (BpsApiException $e) {
                $this->error("Daftar tabel gagal dimuat: {$e->getMessage()}");
                $jumlah['gagal']++;

                continue;
            }

            foreach ($katalog as $n => $t) {
                $nomor = sprintf('[%d/%d]', $n + 1, count($katalog));
                try {
                    $hasil = $sinkron->impor($s, $t['id'], [], $subjek);
                    $jumlah[$hasil['status']]++;
                    $this->line("{$nomor} <info>✓</info> {$t['judul']} ({$hasil['status']})");
                } catch (BpsApiException $e) {
                    $jumlah['gagal']++;
                    $this->line("{$nomor} <error>✗</error> {$t['judul']}: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info("Selesai: {$jumlah['baru']} indikator baru, {$jumlah['ditautkan']} ditautkan, {$jumlah['diperbarui']} diperbarui, {$jumlah['gagal']} gagal.");

        return self::SUCCESS;
    }
}
