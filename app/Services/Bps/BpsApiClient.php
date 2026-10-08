<?php

namespace App\Services\Bps;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Penghubung ke WebAPI BPS (https://webapi.bps.go.id/documentation).
 *
 * Catatan perilaku API yang ditemukan saat uji coba langsung (Oktober 2026):
 * - Parameter boleh dikirim sebagai query string (?model=...&key=...), selain gaya path di dokumentasi.
 *   Query string dipakai agar nilai seperti kata kunci pencarian publikasi tetap aman walau berisi "/".
 * - model=data menolak lebih dari 2 tahun per permintaan, jadi tahun dipecah lalu hasilnya digabung.
 * - Bila tidak ada data, API kadang membalas teks "null", kadang datacontent kosong.
 * - Firewall BPS memblokir User-Agent "curl/..." (HTTP 403), jadi User-Agent diisi eksplisit.
 *
 * Respons yang berhasil disimpan di cache (config services.bps.cache_menit) agar halaman cepat dan
 * kuota API hemat. Kunci API tidak pernah ikut tampil di pesan galat maupun log.
 */
class BpsApiClient
{
    public const MAKS_TAHUN_PER_PERMINTAAN = 2;

    private ?string $kunci;
    private string $urlDasar;
    private string $domain;
    private int $cacheMenit;
    private int $timeout;
    private int $ulang = 2;
    private ?int $segarSejak = null;

    public function __construct()
    {
        $c = config('services.bps', []);
        $this->kunci = filled($c['key'] ?? null) ? trim($c['key']) : null;
        $this->urlDasar = rtrim($c['url'] ?? 'https://webapi.bps.go.id/v1/api', '/');
        $this->domain = (string) ($c['domain'] ?? '1273');
        $this->cacheMenit = (int) ($c['cache_menit'] ?? 360);
        $this->timeout = (int) ($c['timeout'] ?? 25);
    }

    public function siap(): bool
    {
        return $this->kunci !== null;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * Respons cache yang diambil sebelum $waktu (timestamp) tidak dipakai, jadi permintaannya dikirim ulang
     * ke API. Dipakai saat memperbarui indikator agar datanya (termasuk tahun baru) benar-benar terbaru;
     * permintaan yang sama berikutnya dalam satu proses tetap memakai cache yang baru diisi. null = normal.
     */
    public function segarSejak(?int $waktu): void
    {
        $this->segarSejak = $waktu;
    }

    /**
     * Menjalankan $ambil dengan batas waktu untuk halaman web: paling lama 10 detik per permintaan tanpa
     * percobaan ulang, agar halaman tidak menggantung melewati batas 60 detik nginx (Herd) saat API BPS
     * lambat. Perintah artisan tetap memakai batas waktu penuh dan percobaan ulang.
     */
    public function denganBatasHalaman(callable $ambil): mixed
    {
        [$timeout, $ulang] = [$this->timeout, $this->ulang];
        [$this->timeout, $this->ulang] = [min($timeout, 10), 0];
        try {
            return $ambil();
        } finally {
            [$this->timeout, $this->ulang] = [$timeout, $ulang];
        }
    }

    // ===========================================
    // --- PERMINTAAN DASAR ---
    // ===========================================

    /**
     * GET ke WebAPI BPS. $jalur relatif terhadap URL dasar, misalnya "list" atau "view". Mengembalikan
     * JSON terurai, atau null bila API membalas "null" (tidak ada data).
     */
    public function ambil(string $jalur, array $parameter = [], bool $pakaiCache = true): ?array
    {
        return $this->ambilBanyak([[$jalur, $parameter]], $pakaiCache)[0];
    }

    /**
     * Beberapa GET sekaligus. Yang belum ada di cache dikirim bersamaan (paling banyak 4 sekaligus),
     * sehingga tabel dengan banyak tahun tidak menunggu permintaan satu per satu. Satu permintaan BPS
     * butuh 1-2,5 detik, sedangkan nginx Herd memutus halaman yang lebih lama dari 60 detik.
     *
     * @param  array  $permintaan  [kunci => [jalur, parameter]]
     * @return array               [kunci => JSON terurai atau null], urutan sama dengan $permintaan
     */
    public function ambilBanyak(array $permintaan, bool $pakaiCache = true): array
    {
        if (!$this->siap()) {
            throw new BpsApiException('Kunci API BPS belum diisi. Tambahkan BPS_API_KEY di file .env, lalu jalankan "php artisan config:clear".');
        }

        $hasil = [];
        $tertunda = [];
        foreach ($permintaan as $kunci => [$jalur, $parameter]) {
            $urut = $parameter;
            ksort($urut);
            $kunciCache = 'bps-api:' . md5($jalur . '?' . http_build_query($urut));

            $tersimpan = $pakaiCache ? Cache::get($kunciCache) : null;
            if (is_array($tersimpan) && isset($tersimpan['waktu']) && array_key_exists('isi', $tersimpan)
                && ($this->segarSejak === null || $tersimpan['waktu'] >= $this->segarSejak)) {
                $hasil[$kunci] = $tersimpan['isi'];
            } else {
                $tertunda[$kunci] = [...$this->alamat($jalur, $parameter), $kunciCache];
            }
        }

        if ($tertunda) {
            $respons = Http::pool(fn (Pool $pool) => array_map(
                fn ($kunci) => $pool->as((string) $kunci)
                    ->acceptJson()
                    ->withUserAgent('PRANATA/1.0 (BPS Kota Pematangsiantar)')
                    ->timeout($this->timeout)
                    ->when($this->ulang > 0, fn ($r) => $r->retry($this->ulang, 500, fn ($e) => $e instanceof ConnectionException, throw: false))
                    ->get($tertunda[$kunci][0], $tertunda[$kunci][1]),
                array_keys($tertunda)
            ), 4);

            foreach ($tertunda as $kunci => [, , $kunciCache]) {
                $hasil[$kunci] = $this->olah($respons[(string) $kunci] ?? null);
                if ($pakaiCache && $hasil[$kunci] !== null) {
                    Cache::put($kunciCache, ['waktu' => now()->getTimestamp(), 'isi' => $hasil[$kunci]], now()->addMinutes($this->cacheMenit));
                }
            }
        }

        return array_map(fn ($kunci) => $hasil[$kunci], array_combine(array_keys($permintaan), array_keys($permintaan)));
    }

    /** [url, query] satu permintaan. */
    private function alamat(string $jalur, array $parameter): array
    {
        return [$this->urlDasar . '/' . trim($jalur, '/'), $parameter + ['key' => $this->kunci]];
    }

    /** Alamat lengkap tanpa kunci API (kunci disamarkan), untuk perintah pemeriksaan bps:cek. */
    public function alamatTersamar(string $jalur, array $parameter = []): string
    {
        [$url, $query] = $this->alamat($jalur, $parameter);

        return $this->samarkan($query ? $url . '?' . http_build_query($query) : $url);
    }

    // Respons dari pool berupa Response, atau objek exception bila koneksi gagal.
    private function olah(mixed $respons): ?array
    {
        if ($respons instanceof Throwable) {
            throw new BpsApiException('Server WebAPI BPS tidak dapat dihubungi: ' . $this->samarkan($respons->getMessage()));
        }
        if (!$respons instanceof Response) {
            throw new BpsApiException('Tidak ada balasan dari WebAPI BPS.');
        }

        if ($respons->status() === 403) {
            throw new BpsApiException('Permintaan ditolak firewall WebAPI BPS (HTTP 403). Coba beberapa saat lagi.');
        }
        if (!$respons->successful()) {
            throw new BpsApiException("WebAPI BPS sedang bermasalah (HTTP {$respons->status()}). Coba beberapa saat lagi.");
        }

        $isi = trim($respons->body());
        if ($isi === '' || $isi === 'null') {
            return null;
        }

        $json = json_decode($isi, true);
        if (!is_array($json)) {
            throw new BpsApiException('Balasan WebAPI BPS tidak dapat dibaca (bukan JSON).');
        }

        if (($json['status'] ?? null) === 'Error') {
            $pesan = is_string($json['message'] ?? null) ? $json['message'] : 'tanpa keterangan';
            if (stripos($pesan, 'key') !== false && stripos($pesan, 'not allowed') !== false) {
                throw new BpsApiException('Kunci API BPS ditolak. Periksa kembali BPS_API_KEY di file .env.');
            }
            throw new BpsApiException('WebAPI BPS menolak permintaan: ' . $this->samarkan($pesan));
        }

        return $json;
    }

    private function samarkan(string $teks): string
    {
        return $this->kunci === null ? $teks : str_replace([$this->kunci, rawurlencode($this->kunci)], '***', $teks);
    }

    /** Satu halaman model list: ['meta' => [page, pages, total, ...], 'item' => [...]]. */
    public function daftar(string $model, array $parameter = [], bool $pakaiCache = true): array
    {
        return $this->halaman($this->ambil('list', ['model' => $model, 'lang' => 'ind', 'domain' => $this->domain] + $parameter, $pakaiCache));
    }

    /** Semua halaman model list (untuk daftar yang kecil seperti daftar tabel dan tahun). */
    public function daftarSemua(string $model, array $parameter = [], int $maksHalaman = 20): array
    {
        return $this->daftarSemuaBanyak(['x' => [$model, $parameter]], $maksHalaman)['x'];
    }

    /**
     * Beberapa daftar sekaligus, masing-masing semua halamannya. Halaman pertama semua daftar
     * diambil bersamaan (halaman ini memberi tahu jumlah halaman), lalu halaman sisanya bersamaan.
     *
     * @param  array  $daftar  [kunci => [model, parameter]]
     * @return array           [kunci => [item, ...]]
     */
    public function daftarSemuaBanyak(array $daftar, int $maksHalaman = 20): array
    {
        $permintaan = fn (string $model, array $parameter, int $halaman) => ['list', [
            'model' => $model, 'lang' => 'ind', 'domain' => $this->domain, 'page' => $halaman,
        ] + $parameter];

        $hasil = [];
        $sisa = [];
        $pertama = $this->ambilBanyak(array_map(fn ($d) => $permintaan($d[0], $d[1], 1), $daftar));
        foreach ($pertama as $kunci => $json) {
            $halaman = $this->halaman($json);
            $hasil[$kunci] = $halaman['item'];
            for ($h = 2; $h <= min((int) ($halaman['meta']['pages'] ?? 0), $maksHalaman); $h++) {
                $sisa["{$kunci}#{$h}"] = [$kunci, $permintaan($daftar[$kunci][0], $daftar[$kunci][1], $h)];
            }
        }

        if ($sisa) {
            foreach ($this->ambilBanyak(array_map(fn ($s) => $s[1], $sisa)) as $k => $json) {
                array_push($hasil[$sisa[$k][0]], ...$this->halaman($json)['item']);
            }
        }

        return $hasil;
    }

    private function halaman(?array $json): array
    {
        if (!$json || ($json['data-availability'] ?? '') !== 'available' || !isset($json['data'][1])) {
            return ['meta' => ['page' => 1, 'pages' => 0, 'total' => 0], 'item' => []];
        }

        return ['meta' => $json['data'][0], 'item' => $json['data'][1]];
    }

    // ===========================================
    // --- TABEL DINAMIS ---
    // ===========================================

    /**
     * Kategori subjek dan subjek menurut CSA (Classification of Statistical Activities), ditambah daftar
     * tabel dinamis (var). CSA adalah klasifikasi yang dipakai situs BPS untuk penyaring "Kategori Subjek"
     * dan "Subjek" (Statistik Demografi dan Sosial, Statistik Ekonomi, Statistik Lingkungan Hidup dan
     * Multi-domain); klasifikasi lama (model subcat/subject) tidak dipakai lagi di situs BPS. Setiap
     * tabel punya subcsa_id, yaitu subjek CSA-nya.
     */
    public function katalogDinamis(): array
    {
        // Daftar var berisi 10 tabel per halaman; ratusan tabel = puluhan halaman (batas 100 halaman).
        return $this->daftarSemuaBanyak([
            'kategori' => ['subcatcsa', []],
            'subjek' => ['subjectcsa', []],
            'variabel' => ['var', []],
        ], 100);
    }

    /**
     * Pilihan untuk satu tabel dinamis, sama dengan isian di situs BPS: tahun (th), turunan tahun
     * (turth, misalnya bulan/triwulan), karakteristik (turvar), dan judul baris (vervar). Tabel tanpa
     * karakteristik membalas "list-not-available", sehingga daftarnya kosong.
     */
    public function pilihanVariabel(int $idVar): array
    {
        return $this->daftarSemuaBanyak([
            'tahun' => ['th', ['var' => $idVar]],
            'turtahun' => ['turth', ['var' => $idVar]],
            'karakteristik' => ['turvar', ['var' => $idVar]],
            'baris' => ['vervar', ['var' => $idVar]],
        ]);
    }

    /** Tahun yang tersedia untuk variabel tabel dinamis: [th_id => '2024', ...], terbaru dulu. */
    public function tahunVariabel(int $idVar): array
    {
        $tahun = [];
        foreach ($this->daftarSemua('th', ['var' => $idVar]) as $t) {
            $tahun[(int) $t['th_id']] = (string) $t['th'];
        }
        arsort($tahun);

        return $tahun;
    }

    /**
     * Data tabel dinamis (model=data) untuk beberapa periode tahun (th_id). Karena API membatasi
     * 2 tahun per permintaan, th dipecah per 2 lalu hasilnya digabung menjadi satu struktur respons
     * (var, vervar, turvar, tahun, turtahun, datacontent). Array kosong bila tidak ada data.
     */
    public function dataDinamis(int $idVar, array $thIds): array
    {
        $permintaan = array_map(fn (array $potongan) => ['list', [
            'model' => 'data', 'lang' => 'ind', 'domain' => $this->domain,
            'var' => $idVar, 'th' => implode(';', $potongan),
        ]], array_chunk(array_values(array_unique($thIds)), self::MAKS_TAHUN_PER_PERMINTAAN));

        $gabungan = [];
        foreach ($this->ambilBanyak($permintaan) as $json) {
            if (!$json || ($json['data-availability'] ?? '') !== 'available') {
                continue;
            }
            $gabungan = $gabungan === [] ? $json : $this->gabungDinamis($gabungan, $json);
        }

        return $gabungan;
    }

    private function gabungDinamis(array $a, array $b): array
    {
        foreach (['vervar', 'turvar', 'tahun', 'turtahun'] as $kunci) {
            $ada = [];
            foreach ($a[$kunci] ?? [] as $x) {
                $ada[(string) $x['val']] = true;
            }
            foreach ($b[$kunci] ?? [] as $x) {
                if (!isset($ada[(string) $x['val']])) {
                    $a[$kunci][] = $x;
                    $ada[(string) $x['val']] = true;
                }
            }
        }

        // Operator + (bukan array_merge) agar kunci datacontent yang berupa angka tidak diberi nomor ulang.
        $a['datacontent'] = ($a['datacontent'] ?? []) + ($b['datacontent'] ?? []);

        return $a;
    }

    // ===========================================
    // --- PUBLIKASI ---
    // ===========================================

    public function daftarPublikasi(int $halaman = 1, ?string $kataKunci = null): array
    {
        $parameter = ['page' => max(1, $halaman)];
        if (filled($kataKunci)) {
            $parameter['keyword'] = trim($kataKunci);
        }

        return $this->daftar('publication', $parameter);
    }

    public function publikasi(string $id, bool $pakaiCache = true): ?array
    {
        $json = $this->ambil('view', ['model' => 'publication', 'lang' => 'ind', 'domain' => $this->domain, 'id' => $id], $pakaiCache);

        return $json['data'] ?? null;
    }

    /**
     * Unduh PDF dari server BPS ke $tujuan. Hanya menerima alamat *.bps.go.id dan isi berkas yang
     * benar-benar PDF. Berkas ditulis ke "$tujuan.part" dulu, jadi unduhan gagal tidak meninggalkan
     * PDF rusak di folder tujuan.
     */
    /** Hanya alamat https di server BPS (*.bps.go.id) yang boleh diunduh, di sini maupun oleh server AI. */
    public static function alamatBps(string $url): bool
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https' && preg_match('/(^|\.)bps\.go\.id$/i', (string) parse_url($url, PHP_URL_HOST)) === 1;
    }

    public function unduhPdf(string $url, string $tujuan, int $maksByte, int $batasDetik = 240): void
    {
        if (!self::alamatBps($url)) {
            throw new BpsApiException('Alamat unduhan PDF bukan dari server BPS.');
        }

        $sementara = $tujuan . '.part';

        try {
            $respons = Http::withUserAgent('PRANATA/1.0 (BPS Kota Pematangsiantar)')
                ->timeout($batasDetik)
                ->withOptions(['sink' => $sementara])
                ->get($url);
        } catch (ConnectionException $e) {
            @unlink($sementara);
            throw new BpsApiException('Gagal mengunduh PDF: server BPS tidak dapat dihubungi.');
        }

        $gagal = match (true) {
            !$respons->successful() => "Gagal mengunduh PDF (HTTP {$respons->status()}).",
            !is_file($sementara) || filesize($sementara) === 0 => 'Gagal mengunduh PDF: berkas kosong.',
            filesize($sementara) > $maksByte => 'PDF melebihi batas ukuran ' . round($maksByte / 1048576) . ' MB.',
            file_get_contents($sementara, false, null, 0, 5) !== '%PDF-' => 'Berkas yang diterima dari BPS bukan PDF.',
            default => null,
        };

        if ($gagal !== null) {
            @unlink($sementara);
            throw new BpsApiException($gagal);
        }

        rename($sementara, $tujuan);
    }
}
