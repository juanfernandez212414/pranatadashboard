<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Basis pengetahuan AI (RAG): folder PDF publikasi di server Laravel dan layanan AI di Hugging Face
 * (scripts/Hugging Face/main.py) yang mengekstrak PDF menjadi vektor di Qdrant. Dipakai untuk melatih
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
     * Mengirim satu PDF di folder basis pengetahuan ke layanan AI (/upload-ingest). Ekstraksi dan
     * embedding berjalan di latar belakang server AI; berkas dicatat di log agar tidak dikirim ulang.
     *
     * @throws RuntimeException bila layanan AI belum diatur atau menolak berkas
     */
    public static function ingestBerkas(string $nama, int $batasDetik = 300): void
    {
        $url = self::urlAi() ?? throw new RuntimeException('Alamat layanan AI (HUGGINGFACE_API_URL) belum diisi di .env.');
        $jalur = self::folder() . DIRECTORY_SEPARATOR . $nama;
        if (!is_file($jalur)) {
            throw new RuntimeException("Berkas {$nama} tidak ditemukan di folder basis pengetahuan.");
        }

        $berkas = fopen($jalur, 'r');
        try {
            $respons = Http::withoutVerifying()->timeout($batasDetik)->attach('files', $berkas, $nama)->post($url . '/upload-ingest');
        } catch (\Throwable $e) {
            throw new RuntimeException('Layanan AI tidak dapat dihubungi. Pastikan server Hugging Face aktif.', 0, $e);
        } finally {
            if (is_resource($berkas)) {
                fclose($berkas);
            }
        }

        if (!$respons->successful()) {
            throw new RuntimeException("Layanan AI menolak berkas (HTTP {$respons->status()}).");
        }
        if (!in_array($nama, self::sudahDiingest(), true)) {
            file_put_contents(self::berkasLog(), $nama . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}
