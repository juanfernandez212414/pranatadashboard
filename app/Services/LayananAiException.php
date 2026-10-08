<?php

namespace App\Services;

use RuntimeException;
use Throwable;

/**
 * Kegagalan layanan AI (Hugging Face) saat melatih dokumen. $bisaCadangan = true bila Laravel boleh mencoba
 * cara lain, yaitu mengunduh PDF sendiri lalu mengunggahnya (mis. endpoint /ingest-url belum ada di server AI,
 * atau server AI tidak bisa mengunduh dari BPS).
 */
class LayananAiException extends RuntimeException
{
    public function __construct(string $pesan, public readonly bool $bisaCadangan = false, ?Throwable $sebelumnya = null)
    {
        parent::__construct($pesan, 0, $sebelumnya);
    }
}
