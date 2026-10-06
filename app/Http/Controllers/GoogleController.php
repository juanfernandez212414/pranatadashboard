<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Exception;

class GoogleController extends Controller
{
    // Mengarahkan pengguna ke halaman login Google (OAuth).
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    // Menangani respon dari Google setelah login berhasil.
    public function handleGoogleCallback()
    {
        try {
            // 1. Dapatkan data user dari Google (Gunakan Guzzle kustom untuk menghindari timeout IPv6 di server)
            $guzzleClient = new \GuzzleHttp\Client([
                'timeout' => 15,
                'connect_timeout' => 5,
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ]);
            $googleUser = Socialite::driver('google')->setHttpClient($guzzleClient)->user();

            // 2. Cari apakah user dengan email ini sudah ada di database
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // JIKA USER SUDAH ADA: 
                // Update google_id saja (jika sebelumnya kosong). 
                // Jangan sentuh password dan role yang sudah ada!
                $user->update([
                    'google_id' => $googleUser->getId(),
                ]);
            } else {
                // JIKA USER BELUM ADA:
                // Buat pengguna baru secara utuh
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    // Pastikan kolom 'password' di tabel migration Anda sudah di-set ->nullable()
                    'password' => null,
                    'role_id' => 4, // Otomatis pengguna biasa
                ]);
            }

            // 3. Login-kan user ke sistem
            Auth::login($user);
            request()->session()->regenerate();
            
            // Lepaskan kunci sesi sebelum redirect agar dashboard langsung terbuka
            request()->session()->save();

            // 4. Redirect Berdasarkan ROLE dengan .intended() agar lebih cerdas
            if ($user->role_id == 1) {
                return redirect()->intended(route('admin.dashboard'));
            } elseif ($user->role_id == 3) {
                return redirect()->intended(route('penanggungjawab.dashboard'));
            } else {
                return redirect()->intended(route('pengguna.dashboard'));
            }
        } catch (Exception $e) {
            // Jika terjadi error
            return redirect('/login')->withErrors([
                'email' => 'Login dengan Google gagal. Silakan coba lagi.'
            ]);
        }
    }
}
