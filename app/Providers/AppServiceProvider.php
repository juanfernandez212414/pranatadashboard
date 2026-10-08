<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use App\Models\Category;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Satu klien WebAPI BPS per permintaan: controller dan SinkronisasiBps berbagi pengaturan yang sama
        // (mis. batas waktu halaman dari BpsApiClient::denganBatasHalaman).
        $this->app->scoped(\App\Services\Bps\BpsApiClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Batas permintaan per menit (per akun bila login, selain itu per alamat IP):
        // - publik: dashboard, Lihat Data, dan filter grafik yang bisa dibuka tanpa login;
        // - unduh: ekspor Excel/PDF (berat untuk server);
        // - narasi: generate narasi AI oleh Admin/PJ (satu permintaan bisa sampai 5 menit).
        $kunci = fn (Request $request) => $request->user()?->id ? 'akun:' . $request->user()->id : 'ip:' . $request->ip();
        RateLimiter::for('publik', fn (Request $request) => Limit::perMinute(120)->by($kunci($request)));
        RateLimiter::for('unduh', fn (Request $request) => Limit::perMinute(10)->by($kunci($request)));
        RateLimiter::for('narasi', fn (Request $request) => Limit::perMinute(5)->by($kunci($request)));

        // Menggunakan array untuk mengirim data ke 3 layout sekaligus
        View::composer(
            [
                'components.adminlayout',
                'components.penanggungjawablayout',
                'components.penggunalayout'
            ],
            function ($view) {
                $view->with('categories', Category::all());
            }
        );
    }
}
