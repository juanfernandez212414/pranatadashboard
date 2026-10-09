<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Indicator;
use App\Services\Bps\BpsApiClient;
use App\Services\Bps\BpsApiException;
use App\Services\BasisPengetahuan;
use App\Services\Bps\KonverterTabelBps;
use App\Services\Bps\PublikasiBps;
use App\Services\Bps\SinkronisasiBps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Menu "Data API BPS": mengambil tabel dinamis dan publikasi dari WebAPI BPS.
 * - Tabel dinamis dipilih seperti di halaman Tabel Dinamis situs BPS, lalu disimpan sebagai indikator
 *   di Kelola Data, sehingga Lihat Data, ekspor, dashboard, dan narasi AI langsung bisa memakainya.
 * - Semua tabel dinamis otomatis menjadi indikator dan datanya diambil dari API saat dibuka
 *   (SinkronisasiBps); halaman ini menampilkan ringkasannya dan tombol untuk mengecek tabel baru sekarang.
 * - PDF publikasi diunduh dari API ke folder basis pengetahuan lalu langsung dilatihkan (ingest) ke layanan
 *   AI, per publikasi atau semua publikasi baru sekaligus (PublikasiBps), tanpa unggah manual.
 */
class DataBpsController extends Controller
{
    // Batas tahun per tabel agar waktu muat tetap wajar (1 permintaan ke API per 2 tahun).
    private const MAKS_TAHUN = 16;

    // Sama dengan halaman Tabel Dinamis situs BPS: "Hanya dapat memilih maksimal 2 data."
    private const MAKS_DATA = 2;

    public function __construct(private BpsApiClient $bps, private SinkronisasiBps $sinkron, private PublikasiBps $publikasiBps)
    {
    }

    // Hanya Admin (1) dan Penanggung Jawab (3), sama seperti Kelola Data dan Manajemen Pengetahuan.
    private function cekAkses(): void
    {
        if (!in_array(Auth::user()->role_id, [1, 3])) {
            abort(403, 'Akses Ditolak. Hanya Admin dan Penanggung Jawab yang dapat mengambil data dari API BPS.');
        }
    }

    // Awalan nama rute dan komponen layout sesuai role.
    private function area(): array
    {
        return Auth::user()->role_id == 1
            ? ['rute' => 'admin.', 'layout' => 'adminlayout']
            : ['rute' => 'penanggungjawab.', 'layout' => 'penanggungjawablayout'];
    }

    // ===========================================
    // --- HALAMAN UTAMA (TABEL DINAMIS & PUBLIKASI) ---
    // ===========================================
    public function index(Request $request)
    {
        $this->cekAkses();

        $tab = $request->query('tab') === 'publikasi' ? 'publikasi' : 'dinamis';
        $kataKunci = trim((string) $request->query('q', ''));
        $katalog = ['kategori' => [], 'subjek' => [], 'variabel' => []];
        $publikasi = ['meta' => ['page' => 1, 'pages' => 0, 'total' => 0], 'item' => []];
        $galat = null;
        @set_time_limit(90);

        try {
            if ($tab === 'dinamis') {
                // Batas waktu halaman: bila API BPS lambat/menggantung, halaman tetap terbuka dengan pesan galat.
                $katalog = $this->bps->denganBatasHalaman(fn () => $this->katalogDinamis());
            } else {
                $publikasi = $this->bps->denganBatasHalaman(fn () => $this->bps->daftarPublikasi(max(1, (int) $request->query('page', 1)), $kataKunci));
                $tersimpan = PublikasiBps::pdfTersimpan();
                $dilatih = PublikasiBps::kunciDilatih();
                $diabaikan = PublikasiBps::kunciDiabaikan();
                foreach ($publikasi['item'] as &$pub) {
                    $pub['title'] = KonverterTabelBps::bersihkanTeks($pub['title'] ?? '', buangTerjemahan: false);
                    $kunci = PublikasiBps::kunciJudul($pub['title']);
                    $pub['tersimpan'] = $tersimpan[$kunci] ?? null;
                    $pub['dilatih'] = isset($dilatih[$kunci]);
                    $pub['diabaikan'] = isset($diabaikan[$kunci]);
                    $pub['pdf'] = self::urlAman($pub['pdf'] ?? null);
                    $pub['cover'] = self::urlAman($pub['cover'] ?? null);
                }
                unset($pub);
            }
        } catch (BpsApiException $e) {
            $galat = $e->getMessage();
        }

        return view('databps.index', [
            'area' => $this->area(),
            'tab' => $tab,
            'katalog' => $katalog,
            'publikasi' => $publikasi,
            'kataKunci' => $kataKunci,
            'galat' => $galat,
            'domain' => $this->bps->domain(),
            'otomatis' => $this->ringkasanOtomatis($tab === 'dinamis' && $galat === null),
            'publikasiOtomatis' => ['sejak' => PublikasiBps::sejakBawaan(), 'kata' => PublikasiBps::kataBawaan(), 'aiSiap' => BasisPengetahuan::urlAi() !== null,
                'diabaikan' => count(PublikasiBps::kunciDiabaikan())],
        ]);
    }

    /**
     * Ringkasan indikator tabel dinamis API. Cek tabel baru dan daftar indikator lama bernama sama hanya
     * dihitung di tab Tabel Dinamis saat katalog berhasil dimuat (katalognya sudah ada di cache), agar tab
     * Publikasi atau API yang bermasalah tidak membuat halaman lambat.
     */
    private function ringkasanOtomatis(bool $pakaiKatalog): array
    {
        $kandidat = [];
        if ($pakaiKatalog && $this->bps->siap()) {
            $this->sinkron->cerminkanKatalogDiam();
            try {
                $kandidat = $this->bps->denganBatasHalaman(fn () => $this->sinkron->kandidatTautan());
            } catch (\Throwable) {
                // daftar indikator bernama sama hanya pelengkap
            }
        }
        $tertaut = Indicator::where('bps_source', SinkronisasiBps::SUMBER);

        return [
            'jumlah' => (clone $tertaut)->count(),
            'berisiData' => (clone $tertaut)->whereNotNull('data')->count(),
            'terakhir' => (clone $tertaut)->max('bps_synced_at'),
            'diabaikan' => SinkronisasiBps::tabelDiabaikan(),
            'kandidat' => $kandidat,
        ];
    }

    // ===========================================
    // --- IMPOR SEMUA TABEL DINAMIS (DIPANGGIL BROWSER, SATU INDIKATOR PER PERMINTAAN) ---
    // ===========================================

    // Langkah 1: cek tabel dinamis baru (dibuatkan indikator), lalu daftar indikator yang datanya diambil.
    public function imporSemuaMulai()
    {
        $this->cekAkses();
        @set_time_limit(180);

        try {
            $katalog = $this->sinkron->cerminkanKatalog(paksa: true);
        } catch (BpsApiException $e) {
            return response()->json(['galat' => $e->getMessage()], 502);
        }

        return response()->json([
            'katalog' => $katalog,
            'indikator' => Indicator::where('bps_source', SinkronisasiBps::SUMBER)->whereNotNull('bps_table_id')
                ->orderBy('id')->get(['id', 'name'])->map(fn ($i) => ['id' => $i->id, 'nama' => $i->name]),
        ]);
    }

    // Langkah 2: data seluruh tahun satu indikator diambil dari API dan disimpan ke database.
    public function imporSemuaSatu(Indicator $indicator)
    {
        $this->cekAkses();
        @set_time_limit(120);

        try {
            $this->bps->denganBatasHalaman(fn () => $this->sinkron->perbarui($indicator));
        } catch (BpsApiException $e) {
            // Diblokir firewall / kunci ditolak: permintaan berikutnya pasti gagal juga, jadi browser berhenti.
            $berhenti = str_contains($e->getMessage(), 'HTTP 403') || str_contains($e->getMessage(), 'Kunci API BPS ditolak');

            return response()->json(['galat' => $e->getMessage(), 'berhenti' => $berhenti], 502);
        }

        return response()->json(['pesan' => "Data \"{$indicator->name}\" tersimpan."]);
    }

    // Menautkan indikator lama (manual/Excel) ke tabel dinamis BPS yang namanya sama, atas pilihan Admin/PJ.
    public function tautkanIndikator(Indicator $indicator)
    {
        $this->cekAkses();
        @set_time_limit(90);

        try {
            $berhasil = $this->bps->denganBatasHalaman(fn () => $this->sinkron->tautkanIndikator($indicator));
        } catch (BpsApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $berhasil
            ? back()->with('success', "Indikator \"{$indicator->name}\" kini memakai data tabel dinamis WebAPI BPS (seluruh tahun) saat dibuka.")
            : back()->with('error', "Indikator \"{$indicator->name}\" tidak bisa ditautkan: tidak ada tabel dinamis BPS dengan nama yang sama, atau sudah tertaut.");
    }

    // Menautkan sekaligus semua indikator lama yang namanya sama dengan tabel dinamis BPS.
    public function tautkanSemua()
    {
        $this->cekAkses();
        @set_time_limit(90);

        try {
            $jumlah = $this->bps->denganBatasHalaman(function () {
                $jumlah = 0;
                foreach ($this->sinkron->kandidatTautan() as $k) {
                    $indikator = Indicator::find($k['indikator']['id']);
                    if ($indikator && $this->sinkron->tautkanIndikator($indikator)) {
                        $jumlah++;
                    }
                }

                return $jumlah;
            });
        } catch (BpsApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$jumlah} indikator lama kini memakai data tabel dinamis WebAPI BPS. Klik \"Impor Semua Tabel Dinamis\" untuk mengisi datanya.");
    }

    // Tabel dinamis yang indikatornya pernah dihapus dimunculkan lagi sebagai indikator.
    public function pulihkanTabel(int $var)
    {
        $this->cekAkses();
        set_time_limit(120);

        try {
            $this->sinkron->pulihkanTabel((string) $var);
        } catch (BpsApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tabel dinamis ditampilkan lagi sebagai indikator.');
    }

    // Kategori subjek & subjek CSA untuk penyaring, sama persis dengan situs BPS: semua kategori dan
    // subjek ditampilkan, subjek diurutkan per kategori lalu ID. Ditambah daftar tabel dinamis; kategori
    // tiap tabel diambil dari subjek CSA-nya.
    private function katalogDinamis(): array
    {
        $katalog = $this->bps->katalogDinamis();

        $subjek = collect($katalog['subjek'])
            ->map(fn ($s) => ['id' => (int) $s['sub_id'], 'nama' => KonverterTabelBps::bersihkanTeks($s['title'] ?? ''), 'kategori' => (int) ($s['subcat_id'] ?? 0)])
            ->sortBy([['kategori', 'asc'], ['id', 'asc']])->values();
        $kategoriSubjek = $subjek->pluck('kategori', 'id');

        return [
            'kategori' => collect($katalog['kategori'])
                ->map(fn ($k) => ['id' => (int) $k['subcat_id'], 'nama' => KonverterTabelBps::bersihkanTeks($k['title'] ?? '')])
                ->values()->all(),
            'subjek' => $subjek->all(),
            'variabel' => collect($katalog['variabel'])->map(fn ($v) => [
                'id' => (int) $v['var_id'],
                'judul' => KonverterTabelBps::bersihkanTeks($v['title'] ?? '', buangTerjemahan: false),
                'subjek' => (int) ($v['subcsa_id'] ?? 0),
                'kategori' => $kategoriSubjek[(int) ($v['subcsa_id'] ?? 0)] ?? null,
            ])->all(),
        ];
    }

    // ===========================================
    // --- TABEL DINAMIS (SEPERTI HALAMAN TABEL DINAMIS SITUS BPS) ---
    // ===========================================

    // Pilihan satu tabel dinamis (tahun, turunan tahun, karakteristik, judul baris) dalam JSON.
    // Dipanggil browser saat pengguna memilih tabel di tab Tabel Dinamis.
    public function pilihanDinamis(int $var)
    {
        $this->cekAkses();

        try {
            $pilihan = $this->bps->pilihanVariabel($var);
        } catch (BpsApiException $e) {
            return response()->json(['galat' => $e->getMessage()], 502);
        }

        $item = fn (array $daftar, string $id, string $label) => collect($daftar)
            ->map(fn ($x) => ['id' => (int) $x[$id], 'label' => KonverterTabelBps::bersihkanTeks((string) $x[$label], buangTerjemahan: false)])
            ->values();

        return response()->json([
            'tahun' => collect($pilihan['tahun'])->map(fn ($t) => (string) $t['th'])->unique()->sortDesc()->values(),
            'turtahun' => $item($pilihan['turtahun'], 'turth_id', 'turth'),
            'karakteristik' => $item($pilihan['karakteristik'], 'turvar_id', 'turvar'),
            'baris' => $item($pilihan['baris'], 'kode_ver_id', 'vervar'),
            'labelKarakteristik' => $pilihan['karakteristik'][0]['name_group_turvar'] ?? null,
            'labelBaris' => $pilihan['baris'][0]['name_group_ver_id'] ?? null,
        ]);
    }

    // Hasil "Data Terpilih" (maksimal 2): setiap data ditampilkan sebagai tabel beserta form simpan.
    public function hasilDinamis(Request $request)
    {
        $this->cekAkses();

        $input = $request->validate([
            'data' => 'required|array|min:1|max:' . self::MAKS_DATA,
            'data.*.var' => 'required|integer|min:1|max:999999999',
            'data.*.tahun' => 'nullable|array|max:' . self::MAKS_TAHUN,
            'data.*.tahun.*' => 'digits:4',
            ...self::aturanSaringan('data.*.'),
        ], [
            'data.required' => 'Pilih minimal 1 data terlebih dahulu.',
            'data.max' => 'Hanya dapat memilih maksimal ' . self::MAKS_DATA . ' data.',
            'data.*.tahun.max' => 'Paling banyak ' . self::MAKS_TAHUN . ' tahun per data.',
        ]);

        set_time_limit(120);

        $hasil = [];
        foreach (array_values($input['data']) as $pilihan) {
            $saring = self::saringan($pilihan);
            try {
                $hasil[] = ['var' => (int) $pilihan['var'], 'saring' => $saring, 'galat' => null,
                    'tabel' => $this->tabelDinamis((int) $pilihan['var'], $pilihan['tahun'] ?? [], $saring)];
            } catch (BpsApiException $e) {
                $hasil[] = ['var' => (int) $pilihan['var'], 'saring' => $saring, 'galat' => $e->getMessage(), 'tabel' => null];
            }
        }

        return view('databps.hasil', [
            'area' => $this->area(),
            'hasil' => $hasil,
            'kategori' => Category::with(['subjects' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get(),
            'indikator' => Indicator::with('subject:id,name')->orderBy('name')->get(['id', 'name', 'subject_id', 'bps_source', 'bps_options']),
        ]);
    }

    // ===========================================
    // --- SIMPAN TABEL DINAMIS SEBAGAI INDIKATOR ---
    // ===========================================
    public function simpanTabel(Request $request)
    {
        $this->cekAkses();

        $data = $request->validate([
            'var' => 'required|integer|min:1|max:999999999',
            'tahun' => 'nullable|array|max:' . self::MAKS_TAHUN,
            'tahun.*' => 'digits:4',
            ...self::aturanSaringan(''),
            'subject_id' => 'required|exists:subjects,id',
            'name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'indicator_id' => 'nullable|integer|exists:indicators,id',
        ], [
            'subject_id.required' => 'Pilih subjek tujuan terlebih dahulu.',
            'name.required' => 'Nama indikator wajib diisi.',
            'tahun.max' => 'Paling banyak ' . self::MAKS_TAHUN . ' tahun per indikator.',
        ]);

        // Tabel dinamis lengkap sudah otomatis menjadi indikator (seluruh tahun, diperbarui dari API). Tab ini
        // untuk menyimpan potongan tabel sebagai indikator tersendiri, jadi indikator otomatis itu tidak ditimpa
        // dan tidak digandakan.
        $target = !empty($data['indicator_id']) ? Indicator::findOrFail($data['indicator_id']) : null;
        if ($target && $target->bps_source === SinkronisasiBps::SUMBER && empty($target->bps_options)) {
            return back()->withInput()->with('error', "Indikator \"{$target->name}\" berisi tabel dinamis lengkap yang datanya otomatis dari WebAPI BPS, jadi tidak ditimpa. Simpan pilihan ini sebagai indikator baru.");
        }
        if (!$target && !self::saringan($data) && ($cermin = SinkronisasiBps::indikatorCermin((string) $data['var']))) {
            return back()->withInput()->with('error', "Tabel ini sudah otomatis menjadi indikator \"{$cermin->name}\" dengan data seluruh tahun. Pilih judul baris atau karakteristik tertentu bila ingin menyimpan potongan tabel.");
        }

        set_time_limit(120);

        try {
            $tabel = $this->tabelDinamis((int) $data['var'], $data['tahun'] ?? [], self::saringan($data));
        } catch (BpsApiException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if (empty($tabel['matriks']['rows'])) {
            return back()->withInput()->with('error', 'Tabel ini tidak berisi data untuk tahun yang dipilih, jadi tidak ada yang disimpan.');
        }

        // Tautan ke tabel sumber disimpan, sehingga datanya ikut diperbarui dari API saat dibuka.
        $nilai = [
            'subject_id' => $data['subject_id'],
            'name' => $data['name'],
            'unit' => $data['unit'] ?? null,
            'data' => $tabel['matriks'],
            'bps_source' => 'dinamis',
            'bps_table_id' => (string) $data['var'],
            'bps_options' => SinkronisasiBps::normalisasiOpsi(self::saringan($data)),
            'bps_synced_at' => now(),
        ];

        if ($target) {
            $target->update($nilai);
            $pesan = "Indikator \"{$data['name']}\" diperbarui dengan data dari API BPS.";
        } else {
            Indicator::create($nilai + ['user_id' => Auth::id()]); // Audit trail: catat siapa yang mengambil
            $pesan = "Indikator \"{$data['name']}\" berhasil ditambahkan dari API BPS.";
        }

        return redirect()
            ->route($this->area()['rute'] . 'keloladata', ['search' => $data['name']])
            ->with('success', $pesan);
    }

    // ===========================================
    // --- SIMPAN PDF PUBLIKASI KE BASIS PENGETAHUAN ---
    // ===========================================
    public function simpanPublikasi(string $id)
    {
        $this->cekAkses();
        abort_unless(preg_match('/^[A-Za-z0-9_-]{1,100}$/', $id), 404);

        // PDF publikasi bisa puluhan MB.
        set_time_limit(120);

        try {
            $hasil = $this->bps->denganBatasHalaman(fn () => $this->publikasiBps->simpanDanLatih($id, batasHalaman: true));
        } catch (BpsApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $hasil['galatLatih']
            ? back()->with('error', self::pesanPublikasi($hasil))
            : back()->with('success', self::pesanPublikasi($hasil));
    }

    // ===========================================
    // --- AMBIL & LATIH SEMUA PUBLIKASI BARU (DIPANGGIL BROWSER, SATU PUBLIKASI PER PERMINTAAN) ---
    // ===========================================

    // Langkah 1: daftar publikasi (sesuai tahun rilis & kata kunci) yang belum diunduh atau belum dilatihkan.
    public function publikasiOtomatisMulai(Request $request)
    {
        $this->cekAkses();
        $input = $request->validate([
            'sejak' => 'required|integer|min:1990|max:' . now()->year,
            'kata' => 'nullable|string|max:200',
        ]);
        @set_time_limit(120);

        try {
            $kandidat = $this->bps->denganBatasHalaman(fn () => $this->publikasiBps->kandidat((int) $input['sejak'], (string) ($input['kata'] ?? '')));
        } catch (BpsApiException $e) {
            return response()->json(['galat' => $e->getMessage()], 502);
        }

        return response()->json([
            'jumlah' => count($kandidat),
            // Yang pernah dihapus dari basis pengetahuan tidak dilatihkan otomatis.
            'publikasi' => array_values(array_filter($kandidat, fn ($p) => !$p['dilatih'] && !$p['diabaikan'])),
            'diabaikan' => count(array_filter($kandidat, fn ($p) => !$p['dilatih'] && $p['diabaikan'])),
        ]);
    }

    // Publikasi yang pernah dihapus dari basis pengetahuan boleh dilatihkan otomatis lagi.
    public function izinkanPublikasi()
    {
        $this->cekAkses();
        $jumlah = PublikasiBps::izinkanSemua();

        return redirect()->route($this->area()['rute'] . 'databps', ['tab' => 'publikasi'])
            ->with('success', "{$jumlah} publikasi yang pernah dihapus dari basis pengetahuan akan dilatihkan otomatis lagi.");
    }

    // Langkah 2: satu publikasi diunduh dari API (bila belum ada) lalu dikirim ke layanan AI.
    public function publikasiOtomatisSatu(string $id)
    {
        $this->cekAkses();
        abort_unless(preg_match('/^[A-Za-z0-9_-]{1,100}$/', $id), 404);
        @set_time_limit(120);

        try {
            $hasil = $this->bps->denganBatasHalaman(fn () => $this->publikasiBps->simpanDanLatih($id, batasHalaman: true));
        } catch (BpsApiException $e) {
            $berhenti = str_contains($e->getMessage(), 'HTTP 403') || str_contains($e->getMessage(), 'Kunci API BPS ditolak');

            return response()->json(['galat' => $e->getMessage(), 'berhenti' => $berhenti], 502);
        }

        return $hasil['galatLatih']
            // Berhenti bila layanan AI mati/belum diatur (publikasi berikutnya pasti gagal juga); bila hanya
            // publikasi ini yang gagal (mis. batas waktu habis karena PDF besar), lanjut ke publikasi berikutnya.
            ? response()->json(['galat' => self::pesanPublikasi($hasil), 'berhenti' => !$hasil['lanjut']], 502)
            : response()->json(['pesan' => self::pesanPublikasi($hasil)]);
    }

    private static function pesanPublikasi(array $h): string
    {
        $judul = "\"{$h['judul']}\"";

        return match (true) {
            $h['galatLatih'] !== null => ($h['diunduh'] ? "PDF {$judul} tersimpan ({$h['berkas']}), tetapi" : "Publikasi {$judul}")
                . " belum bisa dilatihkan ke AI: {$h['galatLatih']} Ulangi nanti, atau pakai perintah php artisan bps:publikasi.",
            $h['lewatLink'] => "PDF {$judul} diambil server AI langsung dari link WebAPI BPS (tanpa disimpan di server ini) dan sedang"
                . ' dilatihkan (ekstraksi berjalan di server AI, beberapa menit).',
            $h['dilatih'] => ($h['diunduh'] ? "PDF {$judul} diunduh dari WebAPI BPS" : "PDF {$judul} yang tersimpan dikirim ke AI")
                . ' dan sedang dilatihkan (ekstraksi berjalan di server AI, beberapa menit).',
            default => "Publikasi {$judul} sudah ada di basis pengetahuan AI ({$h['berkas']}).",
        };
    }

    // ===========================================
    // --- PEMBANTU ---
    // ===========================================
    private static function bersihkanTahun(array $tahun): array
    {
        return array_values(array_filter(array_map('strval', $tahun), fn ($t) => preg_match('/^\d{4}$/', $t)));
    }

    // Aturan validasi pilihan turunan tahun, karakteristik, dan judul baris (berupa ID dari WebAPI BPS).
    private static function aturanSaringan(string $awalan): array
    {
        $aturan = [];
        foreach (['turtahun', 'karakteristik', 'baris'] as $kunci) {
            $aturan[$awalan . $kunci] = 'nullable|array|max:500';
            $aturan[$awalan . $kunci . '.*'] = 'integer|min:0';
        }

        return $aturan;
    }

    // Pilihan pengguna untuk KonverterTabelBps::dariDinamis; pilihan kosong berarti semua.
    private static function saringan(array $input): array
    {
        $saring = [];
        foreach (['turtahun', 'karakteristik', 'baris'] as $kunci) {
            if (!empty($input[$kunci])) {
                $saring[$kunci] = array_values(array_map('intval', $input[$kunci]));
            }
        }

        return $saring;
    }

    /**
     * Mengambil satu tabel dinamis untuk tahun pilihan pengguna (2 tahun terbaru bila belum memilih) dan
     * mengubahnya ke matriks indikator. Hasil: judul, subjek, satuan, tahunDipilih (terbaru dulu), matriks,
     * catatan, diperbarui.
     */
    private function tabelDinamis(int $idVar, array $tahunDiminta, array $saring = []): array
    {
        $tersedia = array_values($this->bps->tahunVariabel($idVar));

        return $this->sinkron->tabelDinamis($idVar, $this->pilihTahun($tersedia, self::bersihkanTahun($tahunDiminta)), $saring);
    }

    // Tautan dari API hanya ditampilkan bila berupa alamat http(s), bukan skema lain seperti javascript:.
    private static function urlAman(?string $url): ?string
    {
        return $url && preg_match('#^https?://#i', $url) ? $url : null;
    }

    // Tahun pilihan pengguna yang memang tersedia, atau 2 tahun terbaru bila belum memilih.
    private function pilihTahun(array $tersedia, array $diminta): array
    {
        $dipilih = $diminta ? array_values(array_intersect($tersedia, $diminta)) : array_slice($tersedia, 0, 2);

        return array_slice($dipilih, 0, self::MAKS_TAHUN);
    }
}
