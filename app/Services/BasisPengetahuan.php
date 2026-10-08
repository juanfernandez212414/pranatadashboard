<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Basis pengetahuan AI (RAG): folder PDF publikasi di server Laravel dan layanan AI di Hugging Face
 * (scripts/Hugging Face/main.py) yang mengekstrak PDF menjadi vektor di Qdrant. Publikasi BPS dikirim
 * sebagai link (ingestUrl, server AI mengunduh sendiri); PDF di folder dikirim sebagai berkas (ingestBerkas). Dipakai untuk melatih
 * publikasi BPS secara otomatis tanpa unggah manual (App\Services\Bps\PublikasiBps). Halaman Manajemen
 * Pengetahuan (PengetahuanController) memakai folder dan berkas log yang sama.
 */
class BasisPengetahuan
{
    public static function folder(): string
    {
        return storage_path('app/public/dokumen_bps');
    }

    // Daftar PDF yang sudah dikirim ke layanan AI (sama dengan log PengetahuanController::ingest).
    private static function berkasLog(): string
    {
        return storage_path('app/processed_log_bge_m3.txt');
    }

    public static function urlAi(): ?string
    {
        $url = config('services.huggingface.url');

        return filled($url) ? rtrim($url, '/') : null;
    }

    /** Nama PDF yang sudah pernah dikirim ke layanan AI. */
    public static function sudahDiingest(): array
    {
        return is_file(self::berkasLog())
            ? array_map('trim', file(self::berkasLog(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            : [];
    }

    /**
     * Meminta layanan AI mengunduh PDF langsung dari link WebAPI BPS lalu meng-ingest-nya (/ingest-url),
     * tanpa PDF disimpan di server Laravel dan tanpa unggahan berkas besar dari server ini. Bila berhasil,
     * $nama (nama dokumen di basis pengetahuan) dicatat di log.
     *
     * @throws LayananAiException bisaCadangan = true bila Laravel sebaiknya mengunduh & mengunggah sendiri
     */
    public static function ingestUrl(string $urlPdf, string $nama, int $batasDetik = 300): void
    {
        $url = self::urlAi() ?? throw new LayananAiException('Alamat layanan AI (HUGGINGFACE_API_URL) belum diisi di .env.');

        try {
            $respons = Http::withoutVerifying()->acceptJson()->timeout($batasDetik)
                ->post($url . '/ingest-url', ['url' => $urlPdf, 'filename' => $nama]);
        } catch (ConnectionException $e) {
            // Batas waktu habis: server AI masih mengunduh PDF yang besar dari BPS dan biasanya tetap
            // menyelesaikannya. Tidak dicatat di log; percobaan berikutnya dijawab "sudah ada"/"sedang diproses".
            throw self::habisWaktu($e)
                ? new LayananAiException("Server AI belum selesai mengambil PDF dari BPS dalam {$batasDetik} detik (PDF besar atau server AI sedang memulai); bisa jadi tetap diproses di server AI.", hanyaDokumenIni: true, sebelumnya: $e)
                : new LayananAiException('Layanan AI tidak dapat dihubungi. Pastikan server Hugging Face aktif.', sebelumnya: $e);
        }

        if ($respons->successful()) {
            self::catatDiingest($nama);

            return;
        }

        $rincian = is_string($respons->json('detail')) ? $respons->json('detail') : "HTTP {$respons->status()}";
        throw match (true) {
            // Server AI belum memakai kode terbaru (endpoint belum ada).
            in_array($respons->status(), [404, 405], true) => new LayananAiException('Layanan AI belum mendukung pelatihan dari link (deploy ulang scripts/Hugging Face/main.py).', bisaCadangan: true),
            // Server AI gagal mengunduh dari BPS (mis. diblokir firewall BPS): Laravel bisa mengunduh sendiri.
            $respons->status() === 502 => new LayananAiException($rincian, bisaCadangan: true),
            // PDF terlalu besar atau data permintaan ditolak: hanya publikasi ini yang gagal.
            in_array($respons->status(), [413, 422], true) => new LayananAiException("Layanan AI menolak publikasi ini: {$rincian}", hanyaDokumenIni: true),
            default => new LayananAiException("Layanan AI menolak permintaan: {$rincian}"),
        };
    }

    /**
     * Mengirim satu PDF di folder basis pengetahuan ke layanan AI (/upload-ingest). Ekstraksi dan
     * embedding berjalan di latar belakang server AI; berkas dicatat di log agar tidak dikirim ulang.
     *
     * @throws LayananAiException bila layanan AI belum diatur, tidak bisa dihubungi, atau menolak berkas
     */
    public static function ingestBerkas(string $nama, int $batasDetik = 300): void
    {
        $url = self::urlAi() ?? throw new LayananAiException('Alamat layanan AI (HUGGINGFACE_API_URL) belum diisi di .env.');
        $jalur = self::folder() . DIRECTORY_SEPARATOR . $nama;
        if (!is_file($jalur)) {
            throw new LayananAiException("Berkas {$nama} tidak ditemukan di folder basis pengetahuan.", hanyaDokumenIni: true);
        }

        $berkas = fopen($jalur, 'r');
        try {
            $respons = Http::withoutVerifying()->timeout($batasDetik)->attach('files', $berkas, $nama)->post($url . '/upload-ingest');
        } catch (\Throwable $e) {
            throw $e instanceof ConnectionException && self::habisWaktu($e)
                ? new LayananAiException("Unggahan PDF ke layanan AI belum selesai dalam {$batasDetik} detik.", hanyaDokumenIni: true, sebelumnya: $e)
                : new LayananAiException('Layanan AI tidak dapat dihubungi. Pastikan server Hugging Face aktif.', sebelumnya: $e);
        } finally {
            if (is_resource($berkas)) {
                fclose($berkas);
            }
        }

        if (!$respons->successful()) {
            throw new LayananAiException("Layanan AI menolak berkas (HTTP {$respons->status()}).", hanyaDokumenIni: in_array($respons->status(), [413, 422], true));
        }
        self::catatDiingest($nama);
    }

    // Batas waktu permintaan habis (cURL error 28), bukan server yang tidak bisa dihubungi.
    private static function habisWaktu(ConnectionException $e): bool
    {
        return str_contains($e->getMessage(), 'cURL error 28') || stripos($e->getMessage(), 'timed out') !== false;
    }

    private static function catatDiingest(string $nama): void
    {
        if (!in_array($nama, self::sudahDiingest(), true)) {
            file_put_contents(self::berkasLog(), $nama . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}
