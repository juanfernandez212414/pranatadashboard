<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Bps\PublikasiBps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PengetahuanController extends Controller
{
    // Ukuran maksimal setiap PDF yang diunggah (dalam KB, sesuai aturan validasi "max").
    private const MAKS_UKURAN_KB = 102400;

    // Menampilkan halaman manajemen basis pengetahuan dokumen.
    public function index(Request $request)
    {
        $userRole = $request->user()->role_id;
        $batasUnggah = $this->batasUnggah();

        if ($userRole == 1) { // Jika Admin
            return view('admin.pengetahuan', compact('batasUnggah'));
        } elseif ($userRole == 3) { // Jika Penanggung Jawab
            return view('penanggungjawab.pengetahuan', compact('batasUnggah'));
        } else {
            abort(403, 'Akses ditolak.');
        }
    }

    // Batas unggahan (byte) untuk pemeriksaan di browser sebelum form dikirim. Berkas yang melebihi
    // batas server ditolak nginx/PHP sebelum sampai ke Laravel, sehingga yang tampil halaman "413"
    // polos, bukan pop-up. Per file: MAKS_UKURAN_KB, atau upload_max_filesize PHP bila lebih kecil.
    // Total sekali kirim: post_max_size PHP (0 = tanpa batas). client_max_body_size nginx harus
    // minimal sebesar post_max_size, karena batas nginx tidak bisa dibaca dari PHP.
    private function batasUnggah(): array
    {
        $keByte = function ($nilai): int {
            $nilai = trim((string) $nilai);
            $angka = (float) $nilai;

            return (int) match (strtoupper(substr($nilai, -1))) {
                'G' => $angka * 1024 ** 3,
                'M' => $angka * 1024 ** 2,
                'K' => $angka * 1024,
                default => $angka,
            };
        };

        $total = $keByte(ini_get('post_max_size'));

        return [
            'per_file' => min(self::MAKS_UKURAN_KB * 1024, $keByte(ini_get('upload_max_filesize'))),
            'total' => $total > 0 ? $total : PHP_INT_MAX,
        ];
    }

    // Unggah, ingest, dan hapus pengetahuan khusus Admin & Penanggung Jawab. Rute aksi ini hanya
    // mewajibkan login, jadi tanpa pemeriksaan ini role lain bisa memanggilnya secara langsung,
    // termasuk delete-all yang mengosongkan seluruh basis pengetahuan.
    private function checkManageAccess()
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role_id, [1, 3])) {
            abort(403, 'Akses Ditolak. Hanya Admin dan Penanggung Jawab yang dapat mengelola basis pengetahuan.');
        }
    }

    // Mengunggah dokumen PDF/TXT untuk diolah oleh AI.
    public function upload(Request $request)
    {
        $this->checkManageAccess();

        // 1. Validasi Input (Wajib PDF, Maksimal 100MB per file -> 102400 KB)
        $request->validate([
            'dokumen' => 'required|array',
            'dokumen.*' => 'required|mimes:pdf|max:' . self::MAKS_UKURAN_KB,
        ], [
            'dokumen.required' => 'Silakan pilih dokumen terlebih dahulu.',
            'dokumen.*.uploaded' => 'File gagal diunggah karena ukurannya melebihi batas server.',
            'dokumen.*.mimes' => 'Semua file yang diunggah harus berformat PDF.',
            'dokumen.*.max' => 'Ukuran maksimal setiap file adalah 100MB.',
        ]);

        $storagePath = storage_path('app/public/dokumen_bps');

        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0775, true);
        }

        $uploadedCount = 0;
        $skippedCount = 0;

        foreach ($request->file('dokumen') as $file) {
            $originalName = $file->getClientOriginalName();
            $safeFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', str_replace(' ', '_', $originalName));
            $destination = $storagePath . '/' . $safeFilename;

            if (!file_exists($destination)) {
                $file->move($storagePath, $safeFilename);
                $uploadedCount++;
            } else {
                $skippedCount++;
            }
        }

        if ($uploadedCount > 0) {
            $msg = "Berhasil mengunggah {$uploadedCount} dokumen publikasi baru.";
            if ($skippedCount > 0) {
                $msg .= " ({$skippedCount} dokumen dilewati karena sudah ada di server).";
            }
            return back()->with('success', $msg . " Silakan lanjutkan ke proses Ingest.");
        } else {
            return back()->with('error', "Semua dokumen yang Anda pilih sudah pernah diunggah sebelumnya ke server.");
        }
    }

    // Memproses (embedding) dokumen menjadi vektor untuk pencarian AI.
    public function ingest()
    {
        $this->checkManageAccess();

        // 0. BEBASKAN BATAS WAKTU DAN MEMORI PHP
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $apiUrl = env('HUGGINGFACE_API_URL');
        $storagePath = storage_path('app/public/dokumen_bps');
        $logPath = storage_path('app/processed_log_bge_m3.txt');

        if (!file_exists($storagePath)) {
            return back()->with('ingest_result', json_encode([
                'status' => 'error',
                'pesan' => 'Folder publikasi kosong.',
                'detail' => 'Silakan Unggah Publikasi PDF terlebih dahulu sebelum memulai proses penyimpanan pengetahuan.',
                'total' => 0, 'selesai' => 0, 'baru_diproses' => 0, 'sisa' => 0, 'gagal_files' => [],
            ]));
        }

        // 1. BACA LOG LOKAL
        $processedFiles = [];
        if (file_exists($logPath)) {
            $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $processedFiles = array_map('trim', $lines);
        } else {
            file_put_contents($logPath, '');
        }

        // Ambil semua daftar file PDF di folder server Laravel
        $files = array_diff(scandir($storagePath), array('.', '..'));
        $allPdfFiles = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'pdf') {
                $allPdfFiles[] = $file;
            }
        }

        $totalFiles = count($allPdfFiles);

        if ($totalFiles === 0) {
            return back()->with('ingest_result', json_encode([
                'status' => 'error',
                'pesan' => 'Tidak ada file PDF.',
                'detail' => 'Tidak ditemukan file PDF di folder publikasi. Silakan Unggah Publikasi terlebih dahulu.',
                'total' => 0, 'selesai' => 0, 'baru_diproses' => 0, 'sisa' => 0, 'gagal_files' => [],
            ]));
        }

        try {
            // 3. SELALU VALIDASI KE QDRANT CLOUD
            $checkResponse = Http::withoutVerifying()->timeout(300)->post($apiUrl . '/check-missing-files', [
                'filenames' => $allPdfFiles
            ]);

            if (!$checkResponse->successful()) {
                return back()->with('ingest_result', json_encode([
                    'status' => 'error',
                    'pesan' => 'Server AI tidak merespons.',
                    'detail' => 'Gagal melakukan sinkronisasi status dokumen dengan server AI. Pastikan server Hugging Face dalam keadaan aktif.',
                    'total' => $totalFiles, 'selesai' => 0, 'baru_diproses' => 0, 'sisa' => $totalFiles, 'gagal_files' => [],
                ]));
            }

            $missingFiles = $checkResponse->json()['missing_filenames'] ?? [];

            // FILTER: Jangan pedulikan apa kata Hugging Face jika file tersebut
            // sudah ada di dalam log lokal kita. Ini mencegah infinite loop.
            $missingFiles = array_filter($missingFiles, function($file) use ($processedFiles) {
                return !in_array($file, $processedFiles);
            });
            $missingFiles = array_values($missingFiles); // Re-index array

            // Koreksi log dinonaktifkan karena Hugging Face bersifat stateless
            // dan dapat merusak log lokal Laravel yang persisten.
            $logCorrected = false;

            $selesaiCount = $totalFiles - count($missingFiles);

            // Jika semua sudah ada di Qdrant
            if (empty($missingFiles)) {
                file_put_contents($logPath, implode(PHP_EOL, $allPdfFiles) . PHP_EOL);
                return back()->with('ingest_result', json_encode([
                    'status' => 'complete',
                    'pesan' => 'Semua dokumen sudah tersimpan!',
                    'detail' => 'Seluruh dokumen publikasi BPS telah berhasil diekstrak dan tersimpan di database pengetahuan AI.',
                    'total' => $totalFiles, 'selesai' => $totalFiles, 'baru_diproses' => 0, 'sisa' => 0, 'gagal_files' => [],
                ]));
            }

            // 4. BATCHING: Batasi maksimal 50 berkas per klik
            $limitPerClick = 50;
            $filesToProcessNow = array_slice($missingFiles, 0, $limitPerClick);

            $successCount = 0;
            $failedFiles = [];

            foreach ($filesToProcessNow as $file) {
                $pdfPath = $storagePath . '/' . $file;

                if (file_exists($pdfPath)) {
                    try {
                        $response = Http::withoutVerifying()
                            ->timeout(300)
                            ->attach('files', fopen($pdfPath, 'r'), $file)
                            ->post($apiUrl . '/upload-ingest');

                        if ($response->successful()) {
                            $successCount++;
                            file_put_contents($logPath, $file . PHP_EOL, FILE_APPEND);
                        } else {
                            $failedFiles[] = $file;
                        }
                    } catch (\Exception $e) {
                        $failedFiles[] = $file;
                    }
                }
            }

            // RE-KALKULASI
            $sisaAntrian = count($missingFiles) - $successCount;
            $updateSelesai = $selesaiCount + $successCount;

            if ($successCount > 0) {
                $status = ($sisaAntrian > 0) ? 'progress' : 'complete';
                $pesan = ($sisaAntrian > 0)
                    ? "Berhasil memproses {$successCount} dokumen"
                    : 'Semua dokumen berhasil diproses!';
                $detail = ($sisaAntrian > 0)
                    ? "Masih ada {$sisaAntrian} dokumen yang menunggu diproses. Silakan klik tombol Ingest sekali lagi untuk melanjutkan."
                    : 'Luar biasa! Seluruh antrean dokumen pengetahuan BPS telah tuntas diekstrak ke database AI.';

                return back()->with('ingest_result', json_encode([
                    'status' => $status,
                    'pesan' => $pesan,
                    'detail' => $detail,
                    'total' => $totalFiles,
                    'selesai' => $updateSelesai,
                    'baru_diproses' => $successCount,
                    'sisa' => $sisaAntrian,
                    'gagal_files' => $failedFiles,
                ]));
            } else {
                $detail = 'Gagal memproses dokumen pada batch ini.';
                if ($logCorrected) {
                    $detail .= ' Log lokal telah dikoreksi karena tidak sinkron dengan Qdrant. Silakan coba klik Ingest lagi.';
                }
                return back()->with('ingest_result', json_encode([
                    'status' => 'error',
                    'pesan' => 'Proses ingest gagal.',
                    'detail' => $detail,
                    'total' => $totalFiles, 'selesai' => $selesaiCount, 'baru_diproses' => 0, 'sisa' => count($missingFiles), 'gagal_files' => $failedFiles,
                ]));
            }
        } catch (\Exception $e) {
            return back()->with('ingest_result', json_encode([
                'status' => 'error',
                'pesan' => 'Kesalahan komunikasi server.',
                'detail' => 'Terjadi kesalahan saat berkomunikasi dengan server AI: ' . $e->getMessage(),
                'total' => $totalFiles, 'selesai' => 0, 'baru_diproses' => 0, 'sisa' => $totalFiles, 'gagal_files' => [],
            ]));
        }
    }

    // Menghapus dokumen spesifik beserta data vektornya.
    public function deleteByFile(Request $request)
    {
        $this->checkManageAccess();

        $request->validate([
            'filename' => 'required|string|max:500',
        ], [
            'filename.required' => 'Nama file tidak boleh kosong.',
        ]);

        $filename = trim($request->input('filename'));
        $apiUrl   = env('HUGGINGFACE_API_URL');
        $logPath  = storage_path('app/processed_log_bge_m3.txt');

        // Keamanan: pastikan filename tidak mengandung path traversal
        if (str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, '..')) {
            return back()->with('error', 'Nama file tidak valid.');
        }

        try {
            // 1. Panggil API AI untuk menghapus vektor berdasarkan nama file
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->delete($apiUrl . '/delete-by-file', [
                    'filename' => $filename,
                ]);

            if (!$response->successful()) {
                $errorMsg = $response->json()['detail'] ?? $response->body();
                return back()->with('error', "Gagal menghapus vektor dari Qdrant: {$errorMsg}");
            }

            // 2. Hapus entri dari log lokal (file processed_log_bge_m3.txt)
            if (file_exists($logPath)) {
                $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $lines = array_filter($lines, fn($line) => trim($line) !== $filename);
                file_put_contents($logPath, implode(PHP_EOL, $lines) . (count($lines) > 0 ? PHP_EOL : ''));
            }

            // 3. Hapus dokumen fisik (PDF)
            $pdfPath = storage_path('app/public/dokumen_bps/' . $filename);
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }

            // 4. Publikasi BPS yang dihapus tidak dilatihkan lagi otomatis (jadwal malam / tombol massal)
            $this->abaikanPublikasi([$filename]);

            return back()->with('success', "✅ Berhasil menghapus pengetahuan dan file fisik \"{$filename}\" secara permanen.");
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan komunikasi dengan server AI: ' . $e->getMessage());
        }
    }

    // Menghapus semua dokumen dan mengosongkan database vektor.
    public function deleteAll(Request $request)
    {
        $this->checkManageAccess();

        $apiUrl   = env('HUGGINGFACE_API_URL');
        $logPath  = storage_path('app/processed_log_bge_m3.txt');
        $storagePath = storage_path('app/public/dokumen_bps');

        try {
            // 1. Panggil API AI fast delete (mereset koleksi Qdrant)
            $response = Http::withoutVerifying()
                ->timeout(120)
                ->delete($apiUrl . '/delete-all');

            if ($response->successful()) {
                // Nama dokumen yang dihapus, dicatat di langkah 4
                $dihapus = file_exists($logPath) ? file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
                if (file_exists($storagePath)) {
                    $dihapus = array_merge($dihapus, array_diff(scandir($storagePath), array('.', '..')));
                }

                // 2. Hapus log lokal
                if (file_exists($logPath)) {
                    unlink($logPath);
                }

                // 3. Hapus semua dokumen PDF fisik
                if (file_exists($storagePath)) {
                    $files = array_diff(scandir($storagePath), array('.', '..'));
                    foreach ($files as $file) {
                        $pdfPath = $storagePath . '/' . $file;
                        if (is_file($pdfPath)) {
                            unlink($pdfPath);
                        }
                    }
                }

                // 4. Publikasi BPS yang dihapus tidak dilatihkan lagi otomatis (jadwal malam / tombol massal)
                $this->abaikanPublikasi(array_map('trim', $dihapus));

                return back()->with('success', "✅ Kilat! Berhasil menghapus seluruh data pengetahuan dari AI dan semua file PDF fisik.");
            } else {
                return back()->with('error', 'Gagal memicu penghapusan kilat di server AI.');
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menghapus data: ' . $e->getMessage());
        }
    }

    // Mencatat dokumen yang dihapus agar publikasi BPS-nya tidak dilatihkan lagi otomatis. Kegagalan mencatat
    // (mis. migrasi belum dijalankan) tidak membatalkan penghapusan yang sudah terjadi.
    private function abaikanPublikasi(array $namaDokumen): void
    {
        try {
            PublikasiBps::abaikan($namaDokumen);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
