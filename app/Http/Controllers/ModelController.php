<?php

namespace App\Http\Controllers; // 1. Namespace sudah dikeluarkan dari Admin

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth; // 2. Tambahkan Auth untuk cek Role

class ModelController extends Controller
{
    /**
     * Memeriksa apakah pengguna yang sedang login memiliki hak akses untuk mengelola Model AI.
     * Fitur ini hanya diperbolehkan untuk pengguna dengan role_id 1 (Admin) dan 3 (Penanggung Jawab).
     *
     * @return bool True jika pengguna memiliki akses, False jika tidak.
     */
    private function bolehKelolaModel(): bool
    {
        $user = Auth::user();
        return $user && in_array($user->role_id, [1, 3]);
    }

    /**
     * Menampilkan halaman pengelolaan Model AI.
     * Mengambil daftar model AI yang tersedia dan menentukan tampilan (view) mana yang harus
     * dimuat berdasarkan peran (role) pengguna saat ini.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        if (!$this->bolehKelolaModel()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengelola model AI.');
        }

        $activeSetting = Setting::where('user_id', auth()->id())->where('key', 'active_ai_model')->first();
        $activeModel = $activeSetting ? $activeSetting->value : 'llama-3.3-70b-versatile';

        // DAFTAR MODEL AI TERMASUK 3 VARIAN GEMINI
        $models = [
    'gemini-3-flash-preview' => [
        'name' => 'Gemini 3.6 Flash',
        'description' => 'Model Gemini 3.6 Flash terbaru dengan API interactions.',
        'provider' => 'Google',
        'logo' => asset('images/Gemini.png'),
        'api_model' => 'gemini-3-flash-preview',
    ],
    'gemini-3.5-flash' => [
        'name' => 'Gemini 3.5 Flash',
        'description' => 'Model Gemini 3.5 Flash dengan API interactions.',
        'provider' => 'Google',
        'logo' => asset('images/Gemini.png'),
        'api_model' => 'gemini-3.5-flash',
    ],
    'gemini-3-flash' => [
        'name' => 'Gemini 3 Flash',
        'description' => 'Model Gemini 3 Flash (Preview) terbaru. Cepat dan efisien.',
        'provider' => 'Google',
        'logo' => asset('images/Gemini.png'),
        'api_model' => 'gemini-3-flash-preview',
    ],
    'meta-llama/Llama-3.3-70B-Instruct' => [
        'name' => 'Llama 3.3 (70B)',
        'description' => 'Model Produksi Stabil dari Meta.',
        'provider' => 'Meta',
        'logo' => asset('images/Meta.png'),
        'api_model' => 'meta-llama/Llama-3.3-70B-Instruct',
    ],
    'openai/gpt-oss-120b' => [
        'name' => 'GPT-OSS (120B)',
        'description' => 'Model open-source terbesar dari OpenAI.',
        'provider' => 'OpenAI',
        'logo' => asset('images/GPT.png'),
        'api_model' => 'openai/gpt-oss-120b',
    ],
];

        // 3. Logika penentuan View berdasarkan Role
        $user = Auth::user();

        if ($user->role_id == 3) {
            $viewPath = 'penanggungjawab.model';
        } else {
            // Default ke admin (Role 1)
            $viewPath = 'admin.model';
        }

        return view($viewPath, compact('activeModel', 'models'));
    }

    /**
     * Memperbarui pengaturan Model AI yang aktif untuk pengguna yang sedang login.
     * Menyimpan pilihan model ke dalam tabel 'settings'.
     *
     * @param  \Illuminate\Http\Request  $request Request yang berisi data model yang dipilih ('active_model').
     * @return \Illuminate\Http\RedirectResponse Kembali ke halaman sebelumnya dengan pesan sukses.
     */
    public function update(Request $request)
    {
        // 4. Keamanan Tambahan: Pastikan hanya Role 1 dan 3 yang bisa update
        $user = Auth::user();
        if (!$user || !in_array($user->role_id, [1, 3])) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengubah model AI.');
        }

        $request->validate([
            'active_model' => 'required|string'
        ]);

        // Mapping key ke nama ramah pengguna
        $modelNames = [
            'gemini-3-flash-preview'            => 'Gemini 3.6 Flash',
            'gemini-3.5-flash'                  => 'Gemini 3.5 Flash',
            'gemini-3-flash'                    => 'Gemini 3 Flash',
            'meta-llama/Llama-3.3-70B-Instruct' => 'Llama 3.3 (70B)',
            'openai/gpt-oss-120b'               => 'GPT-OSS (120B)',
        ];
        $modelName = $modelNames[$request->active_model] ?? $request->active_model;

        Setting::updateOrCreate(
            ['user_id' => $user->id, 'key' => 'active_ai_model'],
            ['value' => $request->active_model]
        );

        return redirect()->back()->with('success', 'Model berhasil diganti ke: ' . $modelName);
    }

    /**
     * Mengecek status ketersediaan (health check) dari server/API AI eksternal (Hugging Face).
     * Endpoint ini berguna untuk memeriksa apakah API sedang aktif atau dalam status tertidur (sleep/waking up).
     *
     * @return \Illuminate\Http\JsonResponse Status JSON yang berisi status server AI.
     */
    public function checkStatus()
    {
        if (!$this->bolehKelolaModel()) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $apiUrl = env('HUGGINGFACE_API_URL');
        if (!$apiUrl) {
            return response()->json(['status' => 'error', 'message' => 'URL AI belum dikonfigurasi di .env']);
        }
        
        try {
            // Ping root URL Hugging Face. Jika tertidur, request ini akan memicu wake-up.
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($apiUrl);
            
            if ($response->successful()) {
                return response()->json(['status' => 'active', 'message' => 'AI Server Aktif dan Siap Digunakan!']);
            }
            
            return response()->json(['status' => 'starting', 'message' => 'AI Server sedang dipanaskan (waking up).']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'starting', 'message' => 'AI Server sedang dipanaskan (waking up) dari sleep.']);
        }
    }
}
