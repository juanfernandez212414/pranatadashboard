<?php

namespace App\Console\Commands;

use App\Services\Bps\BpsApiClient;
use App\Services\Bps\BpsApiException;
use App\Services\Bps\SinkronisasiBps;
use Illuminate\Console\Command;

/**
 * Memeriksa koneksi ke WebAPI BPS dan isi tabelnya dari terminal.
 *
 *   php artisan bps:cek                               uji kunci API & hitung tabel tiap sumber
 *   php artisan bps:cek simdasi                       daftar tabel SIMDASI (ID, judul, tahun)
 *   php artisan bps:cek simdasi <id_tabel>            pratinjau tabel seperti yang akan disimpan
 *   php artisan bps:cek simdasi <id_tabel> --mentah --tahun=2024   respons JSON mentah dari API
 */
class CekBps extends Command
{
    protected $signature = 'bps:cek
        {sumber? : simdasi, dinamis, atau statis}
        {id? : ID tabel (id_tabel SIMDASI, ID var tabel dinamis, atau ID tabel statis)}
        {--tahun= : Tahun data untuk --mentah (bawaan: tahun terbaru)}
        {--mentah : Tampilkan respons JSON mentah dari API (kunci API disamarkan)}';

    protected $description = 'Uji koneksi dan kunci WebAPI BPS, lihat daftar tabel, atau pratinjau isi satu tabel';

    public function handle(BpsApiClient $bps, SinkronisasiBps $sinkron): int
    {
        if (!$bps->siap()) {
            $this->error('Kunci API BPS belum diisi.');
            $this->line('1. Buka file .env di folder proyek, isi BPS_API_KEY=<kunci dari webapi.bps.go.id> (dan BPS_DOMAIN=1273).');
            $this->line('2. Jalankan: php artisan config:clear');
            $this->line('3. Jalankan lagi: php artisan bps:cek');

            return self::FAILURE;
        }

        $sumber = $this->argument('sumber');
        if ($sumber !== null && !isset(SinkronisasiBps::SUMBER[$sumber])) {
            $this->error('Sumber tidak dikenal. Gunakan: ' . implode(', ', array_keys(SinkronisasiBps::SUMBER)) . '.');

            return self::FAILURE;
        }

        try {
            return match (true) {
                $sumber === null => $this->cekKoneksi($bps, $sinkron),
                $this->argument('id') === null => $this->daftar($sinkron, $sumber),
                $this->option('mentah') => $this->mentah($bps, $sinkron, $sumber, (string) $this->argument('id')),
                default => $this->pratinjau($sinkron, $sumber, (string) $this->argument('id')),
            };
        } catch (BpsApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function cekKoneksi(BpsApiClient $bps, SinkronisasiBps $sinkron): int
    {
        $this->line("Domain BPS: {$bps->domain()} · wilayah SIMDASI: {$bps->wilayahSimdasi()}");
        $bps->ambil('list', ['model' => 'subcatcsa', 'lang' => 'ind', 'domain' => $bps->domain()], pakaiCache: false);
        $this->info('Koneksi berhasil: kunci API diterima WebAPI BPS.');
        $this->newLine();

        foreach (SinkronisasiBps::SUMBER as $kunci => $nama) {
            try {
                $this->line(sprintf('%-28s %d tabel', $nama, count($sinkron->katalog($kunci))));
            } catch (BpsApiException $e) {
                $this->line(sprintf('%-28s <error>gagal: %s</error>', $nama, $e->getMessage()));
            }
        }

        $this->newLine();
        $this->line('Impor semua tabel publikasi: php artisan bps:sinkron --impor=simdasi');
        $this->line('Atau buka menu Data API BPS > Sinkronisasi Semua Tabel.');

        return self::SUCCESS;
    }

    private function daftar(SinkronisasiBps $sinkron, string $sumber): int
    {
        $katalog = $sinkron->katalog($sumber);
        $this->table(['ID', 'Judul', 'Subjek', 'Tahun'], array_map(fn ($t) => [
            $t['id'],
            mb_strimwidth(trim($t['kode'] . ' ' . $t['judul']), 0, 80, '…'),
            mb_strimwidth($t['subjek'], 0, 30, '…'),
            $t['tahun'] ? reset($t['tahun']) . '–' . end($t['tahun']) : '',
        ], $katalog));
        $this->info(count($katalog) . ' tabel.');

        return self::SUCCESS;
    }

    private function pratinjau(SinkronisasiBps $sinkron, string $sumber, string $id): int
    {
        $tabel = $sinkron->ambilTabel($sumber, $id);
        $this->info($tabel['judul']);
        $this->line('Satuan: ' . ($tabel['satuan'] ?: '-') . ' · Tahun berisi data: ' . (implode(', ', $tabel['tahun']) ?: '-'));

        $m = $tabel['matriks'];
        if (empty($m['rows'])) {
            $this->warn('Tabel tidak berisi data yang bisa dibaca. Lihat respons mentahnya dengan opsi --mentah.');

            return self::FAILURE;
        }

        $teks = fn (array $baris) => array_map(fn ($sel) => ($sel['hidden'] ?? false) ? '' : mb_strimwidth((string) $sel['value'], 0, 30, '…'), $baris);
        $this->table($teks($m['headers']), array_map($teks, array_slice($m['rows'], 0, 20)));
        if (count($m['rows']) > 20) {
            $this->line('... ' . (count($m['rows']) - 20) . ' baris lagi.');
        }

        return self::SUCCESS;
    }

    private function mentah(BpsApiClient $bps, SinkronisasiBps $sinkron, string $sumber, string $id): int
    {
        [$jalur, $parameter] = match ($sumber) {
            'simdasi' => ['interoperabilitas/datasource/simdasi/id/25', [
                'tahun' => (int) ($this->option('tahun') ?: (collect($sinkron->cariDiKatalog('simdasi', $id)['tahun'] ?? [])->last() ?? date('Y'))),
                'id_tabel' => $id,
                'wilayah' => $bps->wilayahSimdasi(),
            ]],
            'statis' => ['view', ['model' => 'statictable', 'lang' => 'ind', 'domain' => $bps->domain(), 'id' => (int) $id]],
            'dinamis' => ['list', ['model' => 'data', 'lang' => 'ind', 'domain' => $bps->domain(), 'var' => (int) $id,
                'th' => implode(';', array_slice(array_keys($this->tahunDinamis($bps, (int) $id)), 0, 2))]],
        };

        $this->line('GET ' . $bps->alamatTersamar($jalur, $parameter));
        $json = $bps->ambil($jalur, $parameter, pakaiCache: false);
        $this->line($json === null ? 'null (tidak ada data)' : json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function tahunDinamis(BpsApiClient $bps, int $id): array
    {
        $tahun = $bps->tahunVariabel($id);
        $pilihan = $this->option('tahun') ? array_intersect($tahun, [(string) $this->option('tahun')]) : $tahun;

        return $pilihan ?: $tahun;
    }
}
