<?php

namespace App\Services\Bps;

use RuntimeException;

/**
 * Kegagalan saat berkomunikasi dengan WebAPI BPS. Pesannya sudah dalam bahasa Indonesia dan aman
 * ditampilkan ke pengguna (kunci API selalu disamarkan oleh BpsApiClient).
 */
class BpsApiException extends RuntimeException
{
}
