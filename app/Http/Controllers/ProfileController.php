<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Models\User;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    /**
     * Menampilkan halaman edit profil secara dinamis berdasarkan role.
     */
    public function edit(): View
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Tentukan view berdasarkan role
        if ($user->role_id == 1) {
            $viewPath = 'admin.pengaturan';
        } elseif ($user->role_id == 3) {
            $viewPath = 'penanggungjawab.pengaturan';
        } else {
            // Role 2 & 4 (Pengguna)
            $viewPath = 'pengguna.pengaturan';
        }

        return view($viewPath, [
            'user' => $user,
            'title' => 'Pengaturan Akun' // Tambahkan title agar layout tidak error
        ]);
    }

    /**
     * Menangani update data profil (HANYA Nama dan Foto).
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Validasi HANYA untuk nama dan foto
        $validationRules = [
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ];

        $request->validate($validationRules);

        // Update HANYA nama
        $user->name = $request->name;

        // Cek jika ada file foto baru
        if ($request->hasFile('photo')) {
            // Hapus foto lama JIKA BUKAN 'avatar.png'
            if ($user->photo && $user->photo != 'avatar.png') {
                Storage::disk('public')->delete('profil/' . $user->photo);
            }

            // Gunakan disk 'public' secara eksplisit
            $file = $request->file('photo');
            $fileName = $file->hashName();

            // Simpan dengan disk 'public'
            Storage::disk('public')->putFileAs('profil', $file, $fileName);

            $user->photo = $fileName;
        }

        // Simpan semua perubahan
        $user->save();

        // Tentukan rute redirect berdasarkan role
        $redirectRoute = 'pengguna.pengaturan'; // Default untuk pengguna (2 & 4)
        if ($user->role_id == 1) $redirectRoute = 'admin.pengaturan';
        if ($user->role_id == 3) $redirectRoute = 'penanggungjawab.pengaturan';

        return redirect()->route($redirectRoute)->with('success', 'Profil berhasil diperbarui!');
    }

    /**
     * Menangani update password (DAN Email).
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Tentukan rute redirect berdasarkan role
        $redirectRoute = 'pengguna.pengaturan'; // Default untuk pengguna (2 & 4)
        if ($user->role_id == 1) $redirectRoute = 'admin.pengaturan';
        if ($user->role_id == 3) $redirectRoute = 'penanggungjawab.pengaturan';

        // Cek akun Google
        if ($user->google_id) {
            return redirect()->route($redirectRoute)->withErrors(['password' => 'Tidak dapat mengubah password untuk akun yang login via Google.']);
        }

        // Validasi untuk Email, Password Lama, dan Password Baru
        $validationRules = [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ]
        ];

        $request->validate($validationRules);

        // Update password DAN email
        $user->forceFill([
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ])->save();

        return redirect()->route($redirectRoute)->with('success-password', 'Email & Password berhasil diperbarui!');
    }
}
