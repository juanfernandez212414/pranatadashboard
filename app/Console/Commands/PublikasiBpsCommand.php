<?php

namespace App\Console\Commands;

use App\Services\BasisPengetahuan;
use App\Services\Bps\BpsApiClient;
use App\Services\Bps\BpsApiException;
use App\Services\Bps\PublikasiBps;
use Illuminate\Console\Command;

/**
 * Publikasi BPS ke basis pengetahuan AI tanpa unggah manual (juga dijalankan harian oleh penjadwal):
 * PDF publikasi diunduh dari WebAPI BPS lalu dikirim ke layanan AI untuk diekstrak.
 *
 *   php artisan bps:publikasi                         rilis sejak BPS_PUBLIKASI_SEJAK (bawaan tahun lalu)
 *   php artisan bps:publikasi --sejak=2023 --kata="dalam angka,statistik daerah"
 *   php artisan bps:publikasi --daftar                hanya menampilkan daftar, tanpa mengunduh
 */
class PublikasiBpsCommand extends Command
{
    protected $signature = 'bps:publikasi
        {--sejak= : Tahun rilis paling awal (bawaan BPS_PUBLIKASI_SEJAK, atau tahun lalu)}
        {--kata= : Hanya publikasi yang judulnya memuat salah satu kata kunci ini (pisahkan dengan koma)}
        {--daftar : Hanya tampilkan daftar publikasi dan statusnya}';

    protected $description = 'Unduh PDF publikasi BPS dari WebAPI dan latihkan ke basis pengetahuan AI (RAG)';

    public function handle(BpsApiClient $bps, PublikasiBps $publikasi): int
    {
        if (!$bps->siap()) {
            $this->error('Kunci API BPS belum diisi. Tambahkan BPS_API_KEY di file .env, lalu jalankan "php artisan config:clear".');

            return self::FAILURE;
        }

        $sejak = (int) ($this->option('sejak') ?: PublikasiBps::sejakBawaan());
        $kata = (string) ($this->option('kata') ?? PublikasiBps::kataBawaan());

        try {
            $kandidat = $publikasi->kandidat($sejak, $kata);
        } catch (BpsApiException $e) {
            $this->error("Daftar publikasi gagal dimuat: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info(count($kandidat) . " publikasi rilis {$sejak}-" . now()->year . ($kata !== '' ? " dengan kata kunci \"{$kata}\"" : '') . '.');

        if ($this->option('daftar')) {
            $this->table(['ID', 'Judul', 'Rilis', 'Status'], array_map(fn ($p) => [
                $p['id'], mb_strimwidth($p['judul'], 0, 70, '…'), $p['rilis'],
                $p['dilatih'] ? 'sudah dilatih' : ($p['berkas'] ? 'tersimpan, belum dilatih' : 'belum diunduh'),
            ], $kandidat));

            return self::SUCCESS;
        }

        $antrian = array_values(array_filter($kandidat, fn ($p) => !$p['dilatih']));
        if ($antrian === []) {
            $this->info('Semua publikasi sudah ada di basis pengetahuan AI.');

            return self::SUCCESS;
        }
        if (BasisPengetahuan::urlAi() === null) {
            $this->error('Alamat layanan AI (HUGGINGFACE_API_URL) belum diisi di .env.');

            return self::FAILURE;
        }

        $berhasil = $gagal = 0;
        foreach ($antrian as $n => $p) {
            $nomor = sprintf('[%d/%d]', $n + 1, count($antrian));
            try {
                $hasil = $publikasi->simpanDanLatih($p['id']);
            } catch (BpsApiException $e) {
                $gagal++;
                $this->line("{$nomor} <error>✗</error> {$p['judul']}: {$e->getMessage()}");
                if (str_contains($e->getMessage(), 'HTTP 403') || str_contains($e->getMessage(), 'Kunci API BPS ditolak')) {
                    $this->error('Dihentikan: WebAPI BPS menolak permintaan. Coba lagi nanti.');
                    break;
                }

                continue;
            }

            if ($hasil['galatLatih']) {
                $gagal++;
                $this->line("{$nomor} <error>✗</error> {$p['judul']}: {$hasil['galatLatih']}");
                $this->error('Dihentikan: layanan AI tidak dapat dipakai. PDF yang sudah diunduh dilatihkan pada percobaan berikutnya.');
                break;
            }

            $berhasil++;
            $this->line("{$nomor} <info>✓</info> {$p['judul']}" . ($hasil['diunduh'] ? ' (diunduh)' : '') . ' → dilatihkan ke AI');
        }

        $this->newLine();
        $this->info("{$berhasil} publikasi dilatihkan ke AI, {$gagal} gagal.");

        return $gagal === 0 ? self::SUCCESS : self::FAILURE;
    }
}
