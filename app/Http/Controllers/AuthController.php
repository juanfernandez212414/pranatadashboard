<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Menangani proses login pengguna.
     */
    public function login(Request $request)
    {
        // 1. Validasi input
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2. Cari User berdasarkan Email
        $user = User::where('email', $request->input('email'))->first();

        // 3. Cek Password
        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors([
                'email' => 'Email atau password salah.',
            ])->onlyInput('email');
        }

        // 4. Login User
        Auth::login($user);
        $request->session()->regenerate();
        
        // Lepaskan kunci sesi sebelum redirect agar dashboard langsung terbuka
        $request->session()->save();

        // 5. Redirect Berdasarkan ROLE (Angka)
        // Role 1 = Admin
        // Role 2 = Pimpinan
        // Role 3 = Penanggung Jawab
        // Role 4 = Pengguna Biasa (akun lama; masyarakat kini membuka dashboard tanpa login)
        if ($user->role_id == 1) {
            return redirect()->intended(route('admin.dashboard'));
        } elseif ($user->role_id == 3) {
            return redirect()->intended(route('penanggungjawab.dashboard'));
        } else {
            // Jika role adalah 2 atau 4 (atau role lainnya)
            return redirect()->intended(route('pengguna.dashboard'));
        }
    }

    /**
     * Menangani proses logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Lepaskan kunci (lock) file sesi secara paksa sebelum redirect
        // Ini mencegah "pending" yang lama pada halaman login berikutnya
        $request->session()->save();

        return redirect('/login');
    }

    /**
     * Mengirim link reset password.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            // Gagal mengirim email (mis. pengaturan SMTP di .env salah). Detailnya dicatat di laravel.log.
            report($e);

            // Token sudah tersimpan sebelum email gagal dikirim; hapus agar pengguna bisa langsung mencoba lagi.
            if ($user = Password::getUser($request->only('email'))) {
                Password::deleteToken($user);
            }

            return back()->withInput()->withErrors(['email' => 'Email reset password gagal dikirim. Silakan coba lagi beberapa saat lagi atau hubungi Admin PRANATA.']);
        }

        if ($status == Password::RESET_LINK_SENT) {
            return back()->with('status', 'Link reset password telah dikirim ke email Anda!');
        }

        // Laravel menolak permintaan ulang untuk email yang sama sebelum jeda di config/auth.php (throttle) berlalu.
        if ($status == Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors(['email' => 'Link reset password baru saja dikirim. Periksa kotak masuk atau folder spam, lalu tunggu 1 menit sebelum meminta lagi.']);
        }

        return back()->withErrors(['email' => 'Tidak dapat menemukan pengguna dengan alamat email tersebut.']);
    }

    /**
     * Menampilkan halaman untuk mereset password.
     */
    public function showResetForm(Request $request, $token = null)
    {
        return view('resetpassword')->with(
            ['token' => $token, 'email' => $request->email]
        );
    }

    /**
     * Menangani pembaruan password.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Password Anda telah berhasil direset!');
        }

        return back()->withErrors(['email' => 'Token reset password tidak valid atau telah kedaluwarsa.']);
    }
}
