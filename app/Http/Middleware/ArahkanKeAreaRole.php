<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setiap role punya "area" rute sendiri, dibedakan dari awalan nama rutenya:
 * Admin = admin.*, Penanggung Jawab = penanggungjawab.*, Pimpinan & Pengguna Biasa = pengguna.*.
 *
 * Halaman bersama (dashboard, lihat data, unduh data, pengaturan, tentang kami) memilih tampilan
 * sesuai role di controller, sehingga tetap terbuka walaupun alamatnya milik area role lain. Contohnya
 * Pengguna Biasa di /penanggungjawab/dashboard, yang biasanya terjadi karena redirect()->intended()
 * setelah login atau riwayat browser. Middleware ini mengarahkan permintaan halaman seperti itu ke
 * rute yang sama di area milik role pengguna, lengkap dengan parameter dan query string.
 *
 * Middleware ini HANYA mengarahkan. Pembatasan hak akses tetap dilakukan di controller: rute yang
 * tidak punya padanan di area pengguna (misalnya Kelola Data bagi Pengguna Biasa) dibiarkan lewat
 * dan ditolak controller dengan 403.
 */
class ArahkanKeAreaRole
{
    private const AREA_ROLE = [
        1 => 'admin.',
        2 => 'pengguna.',
        3 => 'penanggungjawab.',
        4 => 'pengguna.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();
        $namaRute = $route?->getName();

        // Hanya halaman biasa (GET/HEAD). Form (POST/PATCH/DELETE) dan permintaan AJAX/JSON tidak diarahkan.
        if (!$user || !$namaRute || !in_array($request->getMethod(), ['GET', 'HEAD'])
            || $request->expectsJson() || $request->ajax()) {
            return $next($request);
        }

        $areaPengguna = self::AREA_ROLE[$user->role_id] ?? null;
        if (!$areaPengguna || str_starts_with($namaRute, $areaPengguna)) {
            return $next($request);
        }

        foreach (array_unique(self::AREA_ROLE) as $area) {
            if (!str_starts_with($namaRute, $area)) {
                continue;
            }
            $ruteTujuan = $areaPengguna . substr($namaRute, strlen($area));
            if (Route::has($ruteTujuan)) {
                $url = route($ruteTujuan, $route->parameters());
                $query = $request->getQueryString();
                return redirect($query ? "{$url}?{$query}" : $url);
            }
            break;
        }

        return $next($request);
    }
}
