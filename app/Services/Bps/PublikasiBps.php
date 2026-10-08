<?php

namespace App\Services\Bps;

use App\Services\BasisPengetahuan;
use App\Services\LayananAiException;
use RuntimeException;

/**
 * Publikasi BPS (PDF) dari WebAPI BPS ke basis pengetahuan AI, tanpa unggah manual. Utamanya hanya link PDF
 * dari API yang dikirim ke layanan AI, lalu server AI sendiri yang mengunduh dan mengekstrak (ingest) PDF itu,
 * jadi PDF tidak perlu disimpan di server Laravel. Bila server AI belum mendukungnya atau tidak bisa
 * mengunduh dari BPS, Laravel mengunduh PDF ke folder basis pengetahuan lalu mengunggahnya (cara cadangan).
 * Dipakai tombol di tab Publikasi (Data API BPS) dan perintah "php artisan bps:publikasi" (terjadwal).
 */
class PublikasiBps
{
    // Sama dengan batas unggah PDF manual di PengetahuanController (100 MB).
    public const MAKS_UKURAN_PDF = 100 * 1024 * 1024;

    // Batas waktu (detik) unduh PDF dan kirim ke AI pada halaman web, agar satu permintaan tidak melewati
    // batas 60 detik nginx (Herd). Perintah bps:publikasi memakai batas penuh untuk PDF yang besar.
    private const BATAS_HALAMAN_LINK = 40;   // server AI mengunduh dari BPS
    private const BATAS_HALAMAN_UNDUH = 30;  // cadangan: Laravel mengunduh dari BPS
    private const BATAS_HALAMAN_LATIH = 25;  // cadangan: Laravel mengunggah ke server AI

    public function __construct(private BpsApiClient $bps)
    {
    }

    /** Tahun rilis paling awal yang dilatihkan otomatis (BPS_PUBLIKASI_SEJAK, bawaan: tahun lalu). */
    public static function sejakBawaan(): int
    {
        $sejak = config('services.bps.publikasi_sejak');

        return filled($sejak) ? (int) $sejak : now()->year - 1;
    }

    /** Kata kunci judul publikasi yang dilatihkan otomatis (BPS_PUBLIKASI_KATA, kosong = semua). */
    public static function kataBawaan(): string
    {
        return trim((string) config('services.bps.publikasi_kata', ''));
    }

    /**
     * Publikasi yang dirilis sejak tahun $sejak sampai tahun ini dan (bila $kata diisi) judulnya memuat
     * salah satu kata kunci (dipisah koma): [['id', 'judul', 'rilis', 'berkas' => nama PDF di folder|null,
     * 'dilatih' => sudah ada di basis pengetahuan AI], ...], terbaru dulu.
     */
    public function kandidat(int $sejak, string $kata = ''): array
    {
        $tahunIni = now()->year;
        $sejak = max(1990, min($sejak, $tahunIni));
        $daftar = $this->bps->daftarSemuaBanyak(
            collect(range($sejak, $tahunIni))->mapWithKeys(fn ($t) => [(string) $t => ['publication', ['year' => $t]]])->all(),
            100
        );

        $kunci = array_values(array_filter(array_map(fn ($k) => mb_strtolower(trim($k)), explode(',', $kata))));
        $tersimpan = self::pdfTersimpan();
        $dilatih = self::kunciDilatih();

        return collect($daftar)->flatten(1)
            ->filter(fn ($p) => filled($p['pub_id'] ?? null))
            ->unique('pub_id')
            ->map(function ($p) use ($tersimpan, $dilatih) {
                $judul = KonverterTabelBps::bersihkanTeks($p['title'] ?? '', buangTerjemahan: false);
                $berkas = $tersimpan[self::kunciNama($judul)] ?? null;

                return [
                    'id' => (string) $p['pub_id'],
                    'judul' => $judul,
                    'rilis' => (string) ($p['rl_date'] ?? ''),
                    'berkas' => $berkas,
                    'dilatih' => isset($dilatih[self::kunciNama($judul)]),
                ];
            })
            ->filter(fn ($p) => $kunci === [] || collect($kunci)->contains(fn ($k) => str_contains(mb_strtolower($p['judul']), $k)))
            ->sortByDesc('rilis')->values()->all();
    }

    /**
     * Satu publikasi ke basis pengetahuan AI. Urutannya:
     * 1. sudah pernah dilatih -> tidak ada yang dikirim;
     * 2. PDF-nya sudah ada di folder -> berkas itu diunggah ke layanan AI;
     * 3. selain itu link PDF dari API dikirim ke layanan AI (server AI mengunduh sendiri, tanpa disimpan di
     *    server ini); bila layanan AI meminta cara cadangan, PDF diunduh ke folder lalu diunggah.
     *
     * @return array ['judul', 'berkas' (nama dokumen di basis pengetahuan), 'lewatLink' => bool,
     *               'diunduh' => bool (PDF diunduh ke folder), 'dilatih' => bool (baru dikirim),
     *               'sudahDilatih' => bool, 'galatLatih' => ?string]
     *
     * @throws BpsApiException bila detail atau PDF publikasi gagal diambil dari BPS
     */
    public function simpanDanLatih(string $id, bool $batasHalaman = false): array
    {
        // Tanpa cache: tautan unduhan PDF dari API bisa kedaluwarsa.
        $pub = $this->bps->publikasi($id, pakaiCache: false);
        if (!$pub || empty($pub['pdf'])) {
            throw new BpsApiException('Publikasi ini tidak memiliki berkas PDF.');
        }
        if (!BpsApiClient::alamatBps((string) $pub['pdf'])) {
            throw new BpsApiException('Alamat unduhan PDF bukan dari server BPS.');
        }

        $judul = KonverterTabelBps::bersihkanTeks($pub['title'] ?? $id, buangTerjemahan: false);
        $berkas = self::pdfTersimpan()[self::kunciNama($judul)] ?? null;
        $hasil = ['judul' => $judul, 'berkas' => $berkas ?? self::namaBerkas($judul), 'lewatLink' => false,
            'diunduh' => false, 'dilatih' => false, 'sudahDilatih' => false, 'galatLatih' => null];

        if (isset(self::kunciDilatih()[self::kunciNama($judul)])) {
            return ['sudahDilatih' => true] + $hasil;
        }

        if ($berkas === null) {
            try {
                BasisPengetahuan::ingestUrl($pub['pdf'], $hasil['berkas'], $batasHalaman ? self::BATAS_HALAMAN_LINK : 300);

                return ['lewatLink' => true, 'dilatih' => true] + $hasil;
            } catch (LayananAiException $e) {
                if (!$e->bisaCadangan) {
                    return ['galatLatih' => $e->getMessage()] + $hasil;
                }
            }

            // Cara cadangan: unduh PDF ke folder basis pengetahuan, lalu unggah ke layanan AI.
            if (!is_dir(BasisPengetahuan::folder())) {
                mkdir(BasisPengetahuan::folder(), 0775, true);
            }
            $this->bps->unduhPdf($pub['pdf'], BasisPengetahuan::folder() . DIRECTORY_SEPARATOR . $hasil['berkas'], self::MAKS_UKURAN_PDF,
                $batasHalaman ? self::BATAS_HALAMAN_UNDUH : 240);
            $hasil['diunduh'] = true;
        }

        try {
            BasisPengetahuan::ingestBerkas($hasil['berkas'], $batasHalaman ? self::BATAS_HALAMAN_LATIH : 300);
            $hasil['dilatih'] = true;
        } catch (RuntimeException $e) {
            $hasil['galatLatih'] = $e->getMessage();
        }

        return $hasil;
    }

    /** [kunciNama => true] untuk dokumen yang sudah dikirim ke layanan AI (lewat link maupun berkas). */
    public static function kunciDilatih(): array
    {
        return array_fill_keys(array_map(fn ($n) => self::kunciNama($n), BasisPengetahuan::sudahDiingest()), true);
    }

    // ===========================================
    // --- PEMBANTU NAMA BERKAS ---
    // ===========================================

    // Nama file aman, aturan sama dengan unggah manual di PengetahuanController::upload.
    public static function namaBerkas(string $judul): string
    {
        $nama = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', str_replace(' ', '_', $judul));
        $nama = trim(preg_replace('/_+/', '_', $nama), '_.');

        return substr($nama !== '' ? $nama : 'Publikasi_BPS', 0, 150) . '.pdf';
    }

    // Kunci pembanding nama: huruf kecil dan angka saja. PDF lama di basis pengetahuan dinamai dari
    // judul publikasi dengan gaya berbeda-beda ("..._(IHK)_...", "Pematang_Siantar"/"Pematangsiantar"),
    // jadi pembandingan tanpa tanda baca mencegah publikasi yang sama diunduh dan di-ingest dua kali.
    public static function kunciNama(string $nama): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(preg_replace('/\.pdf$/i', '', $nama)));
    }

    /** [kunciNama => nama file] untuk PDF yang sudah ada di folder basis pengetahuan. */
    public static function pdfTersimpan(): array
    {
        $folder = BasisPengetahuan::folder();
        $hasil = [];
        foreach (is_dir($folder) ? scandir($folder) : [] as $berkas) {
            if (strtolower(pathinfo($berkas, PATHINFO_EXTENSION)) === 'pdf') {
                $hasil[self::kunciNama($berkas)] = $berkas;
            }
        }

        return $hasil;
    }
}
