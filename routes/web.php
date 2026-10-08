<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\AuthController;

// --- IMPORT CONTROLLER ---
use App\Http\Controllers\PengetahuanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\DataBpsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Rute Bantuan untuk Clear Cache di Server Live
Route::get('/clear-cache-server', function () {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    return 'Berhasil membersihkan cache server! Silakan coba fitur logout lagi.';
});

// ===================================================
// --- RUTE UNTUK TAMU (GUEST) ---
// ===================================================
Route::middleware(['guest'])->group(function () {
    Route::get('/login', function () {
        return view('login');
    })->name('login');
    Route::get('/register', function () {
        return view('register');
    })->name('register');
    Route::get('/forgot-password', function () {
        return view('forgotpassword');
    })->name('password.request');

    // Otentikasi Google
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');

    // Proses Form
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});


// ===================================================
// --- RUTE YANG MEMERLUKAN OTENTIKASI (LOGIN) ---
// ===================================================
// area.role: halaman bersama yang dibuka dari area role lain diarahkan ke area role pengguna
// (misalnya Pengguna Biasa di /penanggungjawab/dashboard -> /pengguna/dashboard).
Route::middleware(['auth', 'area.role'])->group(function () {

    // ---------------------------------------------------
    // 1. RUTE ADMIN (Role 1)
    // ------Route::get('/admin/pengetahuan', [PengetahuanController::class, 'index'])->name('admin.pengetahuan');
    Route::post('/admin/pengetahuan/upload', [PengetahuanController::class, 'upload'])->name('admin.pengetahuan.upload');
    Route::post('/admin/pengetahuan/ingest', [PengetahuanController::class, 'ingest'])->name('admin.pengetahuan.ingest');
    Route::delete('/admin/pengetahuan/delete-by-file', [PengetahuanController::class, 'deleteByFile'])->name('admin.pengetahuan.delete-by-file');
    Route::delete('/admin/pengetahuan/delete-all', [PengetahuanController::class, 'deleteAll'])->name('admin.pengetahuan.delete-all');
    Route::get('/admin/pengetahuan', [PengetahuanController::class, 'index'])->name('admin.pengetahuan');

    // Dashboard & AI Narasi
    Route::get('/dashboard/config', [DashboardController::class, 'getConfig'])->name('dashboard.config');
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/dashboard-peta', [DashboardController::class, 'map'])->name('admin.map');
    Route::get('/admin/dashboard/get-filtered-data', [DashboardController::class, 'getFilteredData'])->name('admin.dashboard.getFilteredData');
    Route::post('/admin/dashboard/generate-narrative', [DashboardController::class, 'generateNarrative'])->name('admin.dashboard.generateNarrative');
    Route::post('/admin/dashboard/save-narrative', [DashboardController::class, 'saveNarrative'])->name('admin.dashboard.saveNarrative');

    // Alamat lama Admin (sebelum diseragamkan ke /admin/...) tetap bisa dibuka dari bookmark atau riwayat
    // browser. Query string ikut dibawa; role lain diteruskan ke areanya oleh middleware area.role.
    Route::get('/dashboard', fn () => redirect()->route('admin.dashboard', request()->query()));
    Route::get('/lihatdata', fn () => redirect()->route('admin.lihatdata', request()->query()));

    // Manajemen Data (Kelola & Lihat)
    Route::get('/admin/data', [DataController::class, 'index'])->name('admin.keloladata');
    Route::get('/admin/lihatdata', [DataController::class, 'showDataView'])->name('admin.lihatdata');
    Route::get('/admin/lihatdata/{id}', [DataController::class, 'showDataDetail'])->name('admin.lihatdata.show');
    Route::get('/admin/indicators/{id}/export/excel', [DataController::class, 'exportExcel'])->name('admin.indicators.export.excel');
    Route::get('/admin/indicators/{id}/export/pdf', [DataController::class, 'exportPdf'])->name('admin.indicators.export.pdf');

    // Aksi CRUD Data
    Route::post('/admin/categories', [DataController::class, 'storeCategory'])->name('admin.categories.store');
    Route::patch('/admin/categories/{category}', [DataController::class, 'updateCategory'])->name('admin.categories.update');
    Route::delete('/admin/categories/{category}', [DataController::class, 'destroyCategory'])->name('admin.categories.destroy');

    Route::post('/admin/subjects', [DataController::class, 'storeSubject'])->name('admin.subjects.store');
    Route::patch('/admin/subjects/{subject}', [DataController::class, 'updateSubject'])->name('admin.subjects.update');
    Route::delete('/admin/subjects/{subject}', [DataController::class, 'destroySubject'])->name('admin.subjects.destroy');

    Route::post('/admin/indicators', [DataController::class, 'storeIndicator'])->name('admin.indicators.store');
    Route::patch('/admin/indicators/{indicator}', [DataController::class, 'updateIndicator'])->name('admin.indicators.update');
    Route::delete('/admin/indicators/{indicator}', [DataController::class, 'destroyIndicator'])->name('admin.indicators.destroy');
    Route::post('/admin/indicators/import', [DataController::class, 'importIndicator'])->name('admin.indicators.import');

    // Data API BPS (tabel dinamis & publikasi dari WebAPI BPS)
    Route::get('/admin/data-bps', [DataBpsController::class, 'index'])->name('admin.databps');
    Route::get('/admin/data-bps/dinamis/{var}/pilihan', [DataBpsController::class, 'pilihanDinamis'])->whereNumber('var')->name('admin.databps.dinamis.pilihan');
    Route::get('/admin/data-bps/dinamis/hasil', [DataBpsController::class, 'hasilDinamis'])->name('admin.databps.dinamis.hasil');
    Route::post('/admin/data-bps/tabel', [DataBpsController::class, 'simpanTabel'])->name('admin.databps.tabel.simpan');
    Route::post('/admin/data-bps/publikasi/{id}', [DataBpsController::class, 'simpanPublikasi'])->name('admin.databps.publikasi.simpan');
    Route::post('/admin/data-bps/publikasi-otomatis', [DataBpsController::class, 'publikasiOtomatisMulai'])->name('admin.databps.publikasi.otomatis');
    Route::post('/admin/data-bps/publikasi-otomatis/{id}', [DataBpsController::class, 'publikasiOtomatisSatu'])->name('admin.databps.publikasi.otomatis.satu');
    Route::post('/admin/data-bps/publikasi-diabaikan/izinkan', [DataBpsController::class, 'izinkanPublikasi'])->name('admin.databps.publikasi.izinkan');
    Route::post('/admin/data-bps/impor-semua', [DataBpsController::class, 'imporSemuaMulai'])->name('admin.databps.imporsemua');
    Route::post('/admin/data-bps/impor-semua/{indicator}', [DataBpsController::class, 'imporSemuaSatu'])->whereNumber('indicator')->name('admin.databps.imporsemua.satu');
    Route::post('/admin/data-bps/tabel-diabaikan/{var}/pulihkan', [DataBpsController::class, 'pulihkanTabel'])->whereNumber('var')->name('admin.databps.diabaikan.pulihkan');
    Route::post('/admin/data-bps/tautkan/{indicator}', [DataBpsController::class, 'tautkanIndikator'])->whereNumber('indicator')->name('admin.databps.tautkan');

    // Manajemen Pengguna
    Route::get('/admin/pengguna', [UserController::class, 'index'])->name('admin.pengguna');
    Route::post('/admin/pengguna', [UserController::class, 'store'])->name('admin.pengguna.store');
    Route::patch('/admin/pengguna/{user}/role', [UserController::class, 'updateRole'])->name('admin.pengguna.updateRole');
    Route::delete('/admin/pengguna/{user}', [UserController::class, 'destroy'])->name('admin.pengguna.destroy');

    // Konfigurasi Model AI
    Route::get('/admin/model/check-status', [ModelController::class, 'checkStatus'])->name('admin.model.checkStatus');
    Route::get('/admin/model', [ModelController::class, 'index'])->name('admin.model');
    Route::patch('/admin/model', [ModelController::class, 'update'])->name('admin.model.update');

    // Tentang Kami & Pengaturan
    Route::get('/admin/tentang-kami', function () {
        abort_unless(in_array(auth()->user()->role_id, [1]), 403, 'Akses ditolak.');
        return view('admin.tentangkami');
    })->name('admin.tentangkami');
    Route::get('/admin/pengaturan', [ProfileController::class, 'edit'])->name('admin.pengaturan');
    Route::patch('/admin/pengaturan/profil', [ProfileController::class, 'update'])->name('admin.profil.update');
    Route::put('/admin/pengaturan/password', [ProfileController::class, 'updatePassword'])->name('admin.password.update');


    // ---------------------------------------------------
    // 2. RUTE PENANGGUNG JAWAB (Role 3)
    // ---------------------------------------------------
    Route::prefix('penanggungjawab')->name('penanggungjawab.')->group(function () {

        Route::post('/pengetahuan/upload', [PengetahuanController::class, 'upload'])->name('pengetahuan.upload');
        Route::post('/pengetahuan/ingest', [PengetahuanController::class, 'ingest'])->name('pengetahuan.ingest');
        Route::delete('/pengetahuan/delete-by-file', [PengetahuanController::class, 'deleteByFile'])->name('pengetahuan.delete-by-file');
        Route::delete('/pengetahuan/delete-all', [PengetahuanController::class, 'deleteAll'])->name('pengetahuan.delete-all');
        Route::get('/pengetahuan', [PengetahuanController::class, 'index'])->name('pengetahuan');
        // Dashboard & AI Narasi (Disamakan dengan Admin)
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/get-filtered-data', [DashboardController::class, 'getFilteredData'])->name('dashboard.getFilteredData');
        Route::post('/dashboard/generate-narrative', [DashboardController::class, 'generateNarrative'])->name('dashboard.generateNarrative');
        Route::post('/dashboard/save-narrative', [DashboardController::class, 'saveNarrative'])->name('dashboard.saveNarrative');

        // Manajemen Data (Untuk menu sidebar Kelola Data & Lihat Data)
        Route::get('/data', [DataController::class, 'index'])->name('keloladata');
        Route::get('/lihatdata', [DataController::class, 'showDataView'])->name('lihatdata');
        Route::get('/lihatdata/{id}', [DataController::class, 'showDataDetail'])->name('lihatdata.show');
        Route::get('/indicators/{id}/export/excel', [DataController::class, 'exportExcel'])->name('indicators.export.excel');
        Route::get('/indicators/{id}/export/pdf', [DataController::class, 'exportPdf'])->name('indicators.export.pdf');

        // 👇 TAMBAHKAN SEMUA RUTE CRUD INI DI SINI 👇
        // Aksi Kategori
        Route::post('/categories', [DataController::class, 'storeCategory'])->name('categories.store');
        Route::patch('/categories/{category}', [DataController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [DataController::class, 'destroyCategory'])->name('categories.destroy');

        // Aksi Subjek
        Route::post('/subjects', [DataController::class, 'storeSubject'])->name('subjects.store');
        Route::patch('/subjects/{subject}', [DataController::class, 'updateSubject'])->name('subjects.update');
        Route::delete('/subjects/{subject}', [DataController::class, 'destroySubject'])->name('subjects.destroy');

        // Aksi Indikator & Import Excel
        Route::post('/indicators', [DataController::class, 'storeIndicator'])->name('indicators.store');
        Route::patch('/indicators/{indicator}', [DataController::class, 'updateIndicator'])->name('indicators.update');
        Route::delete('/indicators/{indicator}', [DataController::class, 'destroyIndicator'])->name('indicators.destroy');
        Route::post('/indicators/import', [DataController::class, 'importIndicator'])->name('indicators.import');

        // Data API BPS (tabel dinamis & publikasi dari WebAPI BPS)
        Route::get('/data-bps', [DataBpsController::class, 'index'])->name('databps');
        Route::get('/data-bps/dinamis/{var}/pilihan', [DataBpsController::class, 'pilihanDinamis'])->whereNumber('var')->name('databps.dinamis.pilihan');
        Route::get('/data-bps/dinamis/hasil', [DataBpsController::class, 'hasilDinamis'])->name('databps.dinamis.hasil');
        Route::post('/data-bps/tabel', [DataBpsController::class, 'simpanTabel'])->name('databps.tabel.simpan');
        Route::post('/data-bps/publikasi/{id}', [DataBpsController::class, 'simpanPublikasi'])->name('databps.publikasi.simpan');
        Route::post('/data-bps/publikasi-otomatis', [DataBpsController::class, 'publikasiOtomatisMulai'])->name('databps.publikasi.otomatis');
        Route::post('/data-bps/publikasi-otomatis/{id}', [DataBpsController::class, 'publikasiOtomatisSatu'])->name('databps.publikasi.otomatis.satu');
        Route::post('/data-bps/publikasi-diabaikan/izinkan', [DataBpsController::class, 'izinkanPublikasi'])->name('databps.publikasi.izinkan');
        Route::post('/data-bps/impor-semua', [DataBpsController::class, 'imporSemuaMulai'])->name('databps.imporsemua');
        Route::post('/data-bps/impor-semua/{indicator}', [DataBpsController::class, 'imporSemuaSatu'])->whereNumber('indicator')->name('databps.imporsemua.satu');
        Route::post('/data-bps/tabel-diabaikan/{var}/pulihkan', [DataBpsController::class, 'pulihkanTabel'])->whereNumber('var')->name('databps.diabaikan.pulihkan');
        Route::post('/data-bps/tautkan/{indicator}', [DataBpsController::class, 'tautkanIndikator'])->whereNumber('indicator')->name('databps.tautkan');

        // Konfigurasi Model AI (Sesuai Sidebar)
        Route::get('/model/check-status', [ModelController::class, 'checkStatus'])->name('model.checkStatus');
        Route::get('/model', [ModelController::class, 'index'])->name('model');
        Route::patch('/model', [ModelController::class, 'update'])->name('model.update');

        // Tentang Kami & Pengaturan
        Route::get('/tentang-kami', function () {
            abort_unless(in_array(auth()->user()->role_id, [3]), 403, 'Akses ditolak.');
            return view('penanggungjawab.tentangkami');
        })->name('tentangkami');
        Route::get('/pengaturan', [ProfileController::class, 'edit'])->name('pengaturan');
        Route::patch('/pengaturan/profil', [ProfileController::class, 'update'])->name('profil.update');
        Route::put('/pengaturan/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    });


    // ---------------------------------------------------
    // 3. RUTE PENGGUNA UMUM (Role 2 & 4)
    // ---------------------------------------------------
    Route::prefix('pengguna')->name('pengguna.')->group(function () {

        // Dashboard (Hanya View & Filter)
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/get-filtered-data', [DashboardController::class, 'getFilteredData'])->name('dashboard.getFilteredData');

        // Lihat Data (Tanpa akses Kelola/Edit)
        Route::get('/lihatdata', [DataController::class, 'showDataView'])->name('lihatdata');
        Route::get('/lihatdata/{id}', [DataController::class, 'showDataDetail'])->name('lihatdata.show');
        Route::get('/indicators/{id}/export/excel', [DataController::class, 'exportExcel'])->name('indicators.export.excel');
        Route::get('/indicators/{id}/export/pdf', [DataController::class, 'exportPdf'])->name('indicators.export.pdf');

        // Tentang Kami & Pengaturan
        Route::get('/tentang-kami', function () {
            abort_unless(in_array(auth()->user()->role_id, [2, 4]), 403, 'Akses ditolak.');
            return view('pengguna.tentangkami');
        })->name('tentangkami');
        Route::get('/pengaturan', [ProfileController::class, 'edit'])->name('pengaturan');
        Route::patch('/pengaturan/profil', [ProfileController::class, 'update'])->name('profil.update');
        Route::put('/pengaturan/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    });

    // ---------------------------------------------------
    // 4. RUTE GLOBAL (Semua Role Bisa Akses)
    // ---------------------------------------------------
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
