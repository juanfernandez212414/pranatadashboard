<?php

namespace App\Console\Commands;

use App\Models\Indicator;
use App\Services\Bps\BpsApiClient;
use App\Services\Bps\BpsApiException;
use App\Services\Bps\SinkronisasiBps;
use Illuminate\Console\Command;

/**
 * Memeriksa koneksi ke WebAPI BPS dan isi tabel dinamisnya dari terminal.
 *
 *   php artisan bps:cek                       uji kunci API & hitung tabel dinamis
 *   php artisan bps:cek --daftar              daftar tabel dinamis (ID var, judul, subjek)
 *   php artisan bps:cek 31                    pratinjau tabel dinamis var 31 (seluruh tahun)
 *   php artisan bps:cek 31 --mentah --tahun=2024   respons JSON mentah dari API
 */
class CekBps extends Command
{
    protected $signature = 'bps:cek
        {var? : ID var tabel dinamis untuk dipratinjau}
        {--daftar : Tampilkan daftar semua tabel dinamis}
        {--tahun= : Tahun data untuk --mentah (bawaan: 2 tahun terbaru)}
        {--mentah : Tampilkan respons JSON mentah dari API (kunci API disamarkan)}';

    protected $description = 'Uji koneksi dan kunci WebAPI BPS, lihat daftar tabel dinamis, atau pratinjau isi satu tabel';

    public function handle(BpsApiClient $bps, SinkronisasiBps $sinkron): int
    {
        if (!$bps->siap()) {
            $this->error('Kunci API BPS belum diisi.');
            $this->line('1. Buka file .env di folder proyek, isi BPS_API_KEY=<kunci dari webapi.bps.go.id> (dan BPS_DOMAIN=1273).');
            $this->line('2. Jalankan: php artisan config:clear');
            $this->line('3. Jalankan lagi: php artisan bps:cek');

            return self::FAILURE;
        }

        $var = $this->argument('var');
        if ($var !== null && !ctype_digit((string) $var)) {
            $this->error('ID var harus berupa angka, misalnya: php artisan bps:cek 31');

            return self::FAILURE;
        }

        try {
            return match (true) {
                $this->option('daftar') => $this->daftar($sinkron),
                $var === null => $this->cekKoneksi($bps, $sinkron),
                (bool) $this->option('mentah') => $this->mentah($bps, (int) $var),
                default => $this->pratinjau($sinkron, (int) $var),
            };
        } catch (BpsApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function cekKoneksi(BpsApiClient $bps, SinkronisasiBps $sinkron): int
    {
        $this->line("Domain BPS: {$bps->domain()}");
        $bps->ambil('list', ['model' => 'subcatcsa', 'lang' => 'ind', 'domain' => $bps->domain()], pakaiCache: false);
        $this->info('Koneksi berhasil: kunci API diterima WebAPI BPS.');

        $jumlahTabel = count($sinkron->katalog());
        $jumlahIndikator = Indicator::where('bps_source', SinkronisasiBps::SUMBER)->count();
        $this->line("Tabel dinamis di BPS: {$jumlahTabel}. Indikator PRANATA yang tertaut API: {$jumlahIndikator}.");
        $this->newLine();
        $this->line('Tabel dinamis otomatis muncul di dashboard. Untuk membuat semuanya sekarang: php artisan bps:sinkron');

        return self::SUCCESS;
    }

    private function daftar(SinkronisasiBps $sinkron): int
    {
        $katalog = $sinkron->katalog();
        $this->table(['ID var', 'Judul', 'Kategori', 'Subjek'], array_map(fn ($t) => [
            $t['id'],
            mb_strimwidth($t['judul'], 0, 70, '…'),
            mb_strimwidth($t['kategori'], 0, 30, '…'),
            mb_strimwidth($t['subjek'], 0, 30, '…'),
        ], $katalog));
        $this->info(count($katalog) . ' tabel dinamis.');

        return self::SUCCESS;
    }

    private function pratinjau(SinkronisasiBps $sinkron, int $var): int
    {
        $tabel = $sinkron->tabelDinamis($var);
        $this->info($tabel['judul']);
        $this->line('Satuan: ' . ($tabel['satuan'] ?: '-') . ' · Tahun tersedia: ' . (implode(', ', array_reverse($tabel['tahunDipilih'])) ?: '-'));

        $m = $tabel['matriks'];
        if (empty($m['rows'])) {
            $this->warn('Tabel ini belum berisi data. Lihat respons mentahnya dengan opsi --mentah.');

            return self::FAILURE;
        }

        $teks = fn (array $baris) => array_map(fn ($sel) => ($sel['hidden'] ?? false) ? '' : mb_strimwidth((string) $sel['value'], 0, 30, '…'), $baris);
        $this->table($teks($m['headers']), array_map($teks, array_slice($m['rows'], 0, 20)));
        if (count($m['rows']) > 20) {
            $this->line('... ' . (count($m['rows']) - 20) . ' baris lagi.');
        }

        return self::SUCCESS;
    }

    private function mentah(BpsApiClient $bps, int $var): int
    {
        $tahun = $bps->tahunVariabel($var); // [th_id => '2024'], terbaru dulu
        $dipilih = $this->option('tahun') ? array_intersect($tahun, [(string) $this->option('tahun')]) : array_slice($tahun, 0, 2, true);
        $parameter = ['model' => 'data', 'lang' => 'ind', 'domain' => $bps->domain(), 'var' => $var, 'th' => implode(';', array_keys($dipilih))];

        $this->line('GET ' . $bps->alamatTersamar('list', $parameter));
        $json = $bps->ambil('list', $parameter, pakaiCache: false);
        $this->line($json === null ? 'null (tidak ada data)' : json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
