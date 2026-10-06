<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // <-- TAMBAHKAN INI
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password; // <-- TAMBAHKAN INI

class UserController extends Controller
{
    private $roles = [
        1 => 'Admin',
        2 => 'Pimpinan',
        3 => 'Penanggung Jawab',
        4 => 'Pengguna Biasa',
    ];

    /**
     * Manajemen pengguna khusus Admin (role 1). Rute /admin/pengguna hanya mewajibkan login, dan
     * menyembunyikan menu di sidebar tidak mencegah role lain membuka URL-nya secara langsung,
     * jadi pemeriksaan ini wajib dipanggil di awal setiap method.
     */
    private function checkAdminAccess()
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role_id, [1])) {
            abort(403, 'Akses Ditolak. Hanya Admin yang dapat mengelola pengguna.');
        }
    }

    /**
     * Menampilkan daftar semua pengguna beserta fitur pencarian dan paginasi.
     * Halaman ini memuat daftar user untuk dikelola oleh Admin.
     *
     * @param  \Illuminate\Http\Request  $request Objek request berisi parameter pencarian ('search').
     * @return \Illuminate\View\View Tampilan halaman admin pengguna.
     */
    public function index(Request $request)
    {
        $this->checkAdminAccess();
        // ... (method index Anda yang sudah ada)
        $search = $request->query('search');

        $users = User::when($search, function ($query, $search) {
            return $query->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.pengguna', [
            'users' => $users,
            'roles' => $this->roles,
            'search' => $search
        ]);
    }

    // --- TAMBAHKAN METHOD BARU DI BAWAH INI ---
    /**
     * Menyimpan data pengguna baru yang didaftarkan oleh Admin ke dalam database.
     * Melakukan validasi input seperti format email, kecocokan password, dan keabsahan role.
     *
     * @param  \Illuminate\Http\Request  $request Objek request berisi data form pendaftaran pengguna baru.
     * @return \Illuminate\Http\RedirectResponse Mengembalikan admin ke halaman daftar pengguna dengan pesan sukses.
     */
    public function store(Request $request)
    {
        $this->checkAdminAccess();

        // 1. Validasi input
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_id' => ['required', 'integer', Rule::in(array_keys($this->roles))],
        ]);

        // 2. Buat pengguna baru
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            // 'photo' akan otomatis diisi 'avatar.png' oleh migrasi/model
        ]);

        // 3. Redirect kembali
        return redirect()->route('admin.pengguna')->with('success', 'Pengguna baru berhasil ditambahkan.');
    }
    // --- AKHIR METHOD BARU ---


    /**
     * Memperbarui peran (role) dari pengguna tertentu.
     * Admin tidak diperbolehkan mengubah role akunnya sendiri dari rute ini demi keamanan.
     *
     * @param  \Illuminate\Http\Request  $request Objek request berisi 'role_id' yang baru.
     * @param  \App\Models\User  $user    Objek User yang akan diperbarui rolenya.
     * @return \Illuminate\Http\RedirectResponse Kembali ke halaman sebelumnya dengan pesan sukses atau error.
     */
    public function updateRole(Request $request, User $user)
    {
        $this->checkAdminAccess();
        // ... (method updateRole Anda yang sudah ada)
        if ($user->id === Auth::id()) {
            return back()->with('error-modal', 'Anda tidak dapat mengubah role Anda sendiri dari halaman ini.');
        }

        $request->validate([
            'role_id' => ['required', 'integer', Rule::in(array_keys($this->roles))],
        ]);

        $user->role_id = $request->role_id;
        $user->save();

        return back()->with('success', 'Role untuk ' . $user->name . ' berhasil diperbarui.');
    }

    /**
     * Menghapus pengguna secara permanen dari database.
     * Juga menghapus foto profil pengguna (jika bukan avatar bawaan) dari penyimpanan lokal.
     * Admin tidak diperbolehkan menghapus akunnya sendiri.
     *
     * @param  \App\Models\User  $user Objek User yang akan dihapus.
     * @return \Illuminate\Http\RedirectResponse Kembali ke halaman sebelumnya dengan pesan sukses penghapusan.
     */
    public function destroy(User $user)
    {
        $this->checkAdminAccess();
        // ... (method destroy Anda yang sudah ada)
        if ($user->id === Auth::id()) {
            return back()->with('error-modal', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        if ($user->photo && $user->photo != 'avatar.png') {
            \Illuminate\Support\Facades\Storage::delete('public/profil/' . $user->photo);
        }

        $userName = $user->name;
        $user->delete();

        return back()->with('success', 'Pengguna ' . $userName . ' berhasil dihapus.');
    }
}
