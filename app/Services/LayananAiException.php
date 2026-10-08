<?php

namespace App\Services;

use RuntimeException;
use Throwable;

/**
 * Kegagalan layanan AI (Hugging Face) saat melatih dokumen.
 * - $bisaCadangan = true bila Laravel boleh mencoba cara lain, yaitu mengunduh PDF sendiri lalu mengunggahnya
 *   (mis. endpoint /ingest-url belum ada di server AI, atau server AI tidak bisa mengunduh dari BPS).
 * - $hanyaDokumenIni = true bila kegagalannya khusus dokumen ini (mis. batas waktu habis karena PDF besar,
 *   atau PDF ditolak karena terlalu besar), jadi dokumen berikutnya tetap boleh diproses. Selain itu layanan
 *   AI dianggap tidak bisa dipakai (belum diatur, mati, atau menolak semua permintaan).
 */
class LayananAiException extends RuntimeException
{
    public function __construct(
        string $pesan,
        public readonly bool $bisaCadangan = false,
        public readonly bool $hanyaDokumenIni = false,
        ?Throwable $sebelumnya = null,
    ) {
        parent::__construct($pesan, 0, $sebelumnya);
    }
}
