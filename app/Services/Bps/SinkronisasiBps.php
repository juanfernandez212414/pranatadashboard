<?php

namespace App\Services\Bps;

use App\Models\Category;
use App\Models\Indicator;
use App\Models\Subject;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tabel dinamis WebAPI BPS sebagai sumber data indikator PRANATA.
 *
 * 1. Cermin katalog: setiap tabel dinamis domain ini otomatis menjadi indikator (kategori & subjek
 *    mengikuti klasifikasi CSA di situs BPS), tanpa impor manual. Dijalankan berkala (paling sering sekali
 *    per services.bps.katalog_menit) saat Admin/PJ membuka dashboard atau Data API BPS, oleh penjadwal, dan
 *    oleh perintah "php artisan bps:sinkron". Indikator lama (manual/Excel) yang namanya sama dengan tabel
 *    BPS tidak ditimpa otomatis: Admin/PJ memilih sendiri untuk menautkannya (tautkanIndikator).
 * 2. Baca API saat dibuka: saat indikator dibuka (dashboard, Lihat Data, ekspor, narasi AI), datanya
 *    diambil dari API dengan SELURUH tahun yang tersedia bila sudah lebih tua dari services.bps.segar_menit,
 *    lalu disimpan di Indicator.data. Halaman lain dan narasi AI (RAG) memakai data yang sama. Bila API
 *    gagal, data terakhir yang tersimpan tetap dipakai.
 *
 * Indikator tertaut API ditandai bps_source = 'dinamis' dan bps_table_id = ID var. bps_options berisi
 * saringan (judul baris/karakteristik/turunan tahun) untuk indikator yang disimpan dari tab Tabel Dinamis;
 * cermin katalog selalu tanpa saringan.
 */
class SinkronisasiBps
{
    public const SUMBER = 'dinamis';

    // Kunci cache: penanda katalog baru saja dicerminkan, dan kunci agar cermin tidak berjalan ganda.
    private const KUNCI_CERMIN = 'bps:katalog-dicerminkan';
    private const KUNCI_KUNCIAN = 'bps:kunci-cermin-katalog';

    // Setelah pengambilan data satu indikator gagal, API tidak dicoba lagi untuk indikator itu selama ini.
    private const JEDA_GAGAL_MENIT = 30;

    public function __construct(private BpsApiClient $bps)
    {
    }

    // ===========================================
    // --- KATALOG TABEL DINAMIS ---
    // ===========================================

    /**
     * Semua tabel dinamis domain ini: [['id', 'judul', 'kategori', 'subjek', 'subjekLain', 'satuan', 'grafik'], ...].
     * Kategori & subjek mengikuti klasifikasi CSA (sama dengan penyaring di situs BPS); 'subjekLain' adalah
     * subjek lama BPS; 'grafik' adalah jenis grafik bawaan BPS untuk tabel itu (line, bar, ...).
     */
    public function katalog(): array
    {
        $katalog = $this->bps->katalogDinamis();
        $kategori = collect($katalog['kategori'])->mapWithKeys(fn ($k) => [(int) $k['subcat_id'] => KonverterTabelBps::bersihkanTeks($k['title'] ?? '')]);
        $subjek = collect($katalog['subjek'])->mapWithKeys(fn ($s) => [(int) $s['sub_id'] => [
            'nama' => KonverterTabelBps::bersihkanTeks($s['title'] ?? ''),
            'kategori' => $kategori[(int) ($s['subcat_id'] ?? 0)] ?? '',
        ]]);

        return collect($katalog['variabel'])
            ->filter(fn ($v) => filled($v['var_id'] ?? null))
            ->map(fn ($v) => [
                'id' => (string) $v['var_id'],
                'judul' => KonverterTabelBps::bersihkanTeks($v['title'] ?? '', buangTerjemahan: false) ?: "Tabel dinamis {$v['var_id']}",
                'kategori' => $subjek[(int) ($v['subcsa_id'] ?? 0)]['kategori'] ?? '',
                'subjek' => KonverterTabelBps::bersihkanTeks($v['subcsa_name'] ?? '') ?: ($subjek[(int) ($v['subcsa_id'] ?? 0)]['nama'] ?? ''),
                'subjekLain' => KonverterTabelBps::bersihkanTeks($v['sub_name'] ?? ''),
                'satuan' => self::satuan($v['unit'] ?? ''),
                'grafik' => strtolower(trim((string) ($v['graph_name'] ?? ''))),
            ])
            ->unique('id')->values()->all();
    }

    /**
     * Mencerminkan katalog tabel dinamis ke indikator. Tabel yang belum menjadi indikator dibuat (datanya
     * diambil saat pertama dibuka). Dilewati: tabel yang indikatornya pernah dihapus pengguna (abaikanTabel)
     * dan tabel yang namanya sama dengan indikator lama tanpa tautan API (lihat kandidatTautan).
     *
     * Tanpa $paksa, hanya berjalan bila cermin terakhir lebih tua dari services.bps.katalog_menit.
     * Mengembalikan jumlah ['baru', 'namaSama', 'tetap'], atau null bila dilewati.
     */
    public function cerminkanKatalog(bool $paksa = false): ?array
    {
        if (!$this->bps->siap() || (!$paksa && Cache::has(self::KUNCI_CERMIN))) {
            return null;
        }

        $hasil = Cache::lock(self::KUNCI_KUNCIAN, 300)->get(function () use ($paksa) {
            if (!$paksa && Cache::has(self::KUNCI_CERMIN)) {
                return null; // baru saja dicerminkan oleh permintaan lain
            }

            $katalog = $this->denganCacheSegar($paksa ? now()->getTimestamp() : null, fn () => $this->katalog());
            $tertaut = Indicator::where('bps_source', self::SUMBER)->whereNull('bps_options')->get(['id', 'bps_table_id', 'bps_chart'])->keyBy('bps_table_id');
            $diabaikan = array_flip(DB::table('bps_tabel_diabaikan')->pluck('bps_table_id')->all());
            $namaManual = $this->petaNamaManual();
            $jumlah = ['baru' => 0, 'namaSama' => 0, 'tetap' => 0];

            foreach ($katalog as $t) {
                if (isset($tertaut[$t['id']])) {
                    $jumlah['tetap']++;
                    if (($tertaut[$t['id']]->bps_chart ?? null) !== self::grafik($t['grafik'])) {
                        Indicator::whereKey($tertaut[$t['id']]->id)->toBase()->update(['bps_chart' => self::grafik($t['grafik'])]);
                    }
                } elseif (isset($namaManual[self::kunciNama($t['judul'])])) {
                    $jumlah['namaSama']++;
                } elseif (!isset($diabaikan[$t['id']])) {
                    $this->buatIndikator($t);
                    $jumlah['baru']++;
                }
            }

            Cache::put(self::KUNCI_CERMIN, now()->getTimestamp(), now()->addMinutes(self::menit('katalog_menit', 1440)));

            return $jumlah;
        });

        return $hasil ?: null;
    }

    /**
     * Versi untuk halaman web: memakai batas waktu halaman, dan kegagalan API hanya dicatat di log tanpa
     * menghentikan halaman.
     */
    public function cerminkanKatalogDiam(): void
    {
        try {
            $this->bps->denganBatasHalaman(fn () => $this->cerminkanKatalog());
        } catch (\Throwable $e) {
            Log::warning('Cermin katalog tabel dinamis BPS gagal: ' . $e->getMessage());
            // Jangan coba lagi pada setiap permintaan halaman selama API bermasalah.
            Cache::put(self::KUNCI_CERMIN, now()->getTimestamp(), now()->addMinutes(15));
        }
    }

    // Satu tabel katalog menjadi indikator baru (datanya diambil saat pertama dibuka).
    private function buatIndikator(array $t): void
    {
        Indicator::create(self::tautan($t) + [
            'subject_id' => $this->subjekTujuan($t)->id,
            'name' => self::potong($t['judul'], 255),
            'unit' => self::potong($t['satuan'], 50) ?: null,
            'data' => null,
        ]);
    }

    private static function tautan(array $t): array
    {
        return ['bps_source' => self::SUMBER, 'bps_table_id' => $t['id'], 'bps_options' => null, 'bps_chart' => self::grafik($t['grafik']), 'bps_synced_at' => null];
    }

    /**
     * Indikator lama tanpa tautan API yang namanya sama dengan tabel dinamis BPS (dan tabelnya belum tertaut):
     * [['indikator' => ['id', 'name', 'subjek'], 'var' => ID var, 'judul' => judul tabel BPS], ...].
     * Tidak ditautkan otomatis karena datanya akan diganti data API.
     */
    public function kandidatTautan(): array
    {
        $namaManual = $this->petaNamaManual();
        if ($namaManual === []) {
            return [];
        }
        $tertaut = array_flip(Indicator::where('bps_source', self::SUMBER)->whereNull('bps_options')->pluck('bps_table_id')->all());

        $hasil = [];
        foreach ($this->katalog() as $t) {
            if (!isset($tertaut[$t['id']]) && ($i = $namaManual[self::kunciNama($t['judul'])] ?? null)) {
                $hasil[] = ['indikator' => ['id' => $i->id, 'name' => $i->name, 'subjek' => $i->subject->name ?? ''], 'var' => $t['id'], 'judul' => $t['judul']];
            }
        }

        return $hasil;
    }

    /**
     * Menautkan indikator lama ke tabel dinamis BPS bernama sama (pilihan Admin/PJ). Nama, subjek, dan narasi
     * tetap; datanya diganti data API (seluruh tahun) saat dibuka berikutnya.
     */
    public function tautkanIndikator(Indicator $indikator): bool
    {
        $t = collect($this->katalog())->first(fn ($t) => self::kunciNama($t['judul']) === self::kunciNama($indikator->name));
        if ($t === null || $indikator->bps_source !== null) {
            return false;
        }

        $indikator->update(self::tautan($t) + ['unit' => $indikator->unit ?: (self::potong($t['satuan'], 50) ?: null)]);
        DB::table('bps_tabel_diabaikan')->where('bps_table_id', $t['id'])->delete();

        return true;
    }

    // Indikator tanpa tautan API, dikunci dengan nama yang dibakukan (dibuat sekali per cermin).
    private function petaNamaManual(): array
    {
        $peta = [];
        foreach (Indicator::whereNull('bps_source')->with('subject:id,name')->get(['id', 'name', 'subject_id']) as $i) {
            $peta[self::kunciNama($i->name)] ??= $i;
        }

        return $peta;
    }

    /**
     * Dipanggil saat pengguna menghapus indikator yang dicerminkan dari katalog, agar cermin katalog
     * berikutnya tidak membuatnya lagi. Tabel itu muncul lagi bila tautannya dihapus dari daftar ini.
     */
    public static function abaikanTabel(Indicator $indikator): void
    {
        if ($indikator->bps_source === self::SUMBER && $indikator->bps_table_id && empty($indikator->bps_options)) {
            DB::table('bps_tabel_diabaikan')->updateOrInsert(
                ['bps_table_id' => $indikator->bps_table_id],
                ['judul' => self::potong((string) $indikator->name, 255), 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    /** abaikanTabel untuk semua indikator tertaut di bawah subjek-subjek ini (sebelum subjek/kategori dihapus). */
    public static function abaikanTabelDiSubjek(array $idSubjek): void
    {
        Indicator::whereIn('subject_id', $idSubjek)->where('bps_source', self::SUMBER)->whereNull('bps_options')
            ->get(['id', 'name', 'bps_source', 'bps_table_id', 'bps_options'])
            ->each(fn (Indicator $i) => self::abaikanTabel($i));
    }

    /** Tabel dinamis yang disembunyikan karena indikatornya dihapus: [['bps_table_id', 'judul', 'updated_at'], ...]. */
    public static function tabelDiabaikan(): array
    {
        return DB::table('bps_tabel_diabaikan')->orderBy('judul')->get(['bps_table_id', 'judul', 'updated_at'])
            ->map(fn ($t) => (array) $t)->all();
    }

    /** Tabel yang disembunyikan dimunculkan lagi: indikatornya dibuat pada cermin katalog berikutnya (dipaksa). */
    public function pulihkanTabel(string $idVar): ?array
    {
        DB::table('bps_tabel_diabaikan')->where('bps_table_id', $idVar)->delete();

        return $this->cerminkanKatalog(paksa: true);
    }

    // ===========================================
    // --- BACA API SAAT DIBUKA ---
    // ===========================================

    /**
     * Memastikan data indikator tertaut API cukup baru sebelum ditampilkan/dikirim ke AI: bila belum pernah
     * diambil atau lebih tua dari services.bps.segar_menit (atau $paksa), data diambil dari API. Indikator
     * yang tidak tertaut API tidak disentuh. Kegagalan API tidak menghentikan halaman: data tersimpan
     * tetap dipakai dan pesan galatnya dikembalikan.
     *
     * @return string|null pesan galat bila pengambilan dari API gagal, selain itu null
     */
    public function pastikanSegar(Indicator $indikator, bool $paksa = false): ?string
    {
        if ($indikator->bps_source !== self::SUMBER || !$indikator->bps_table_id || !$this->bps->siap()) {
            return null;
        }

        $menit = self::menit('segar_menit', 360);
        $segar = $indikator->bps_synced_at && $indikator->bps_synced_at->gt(now()->subMinutes($menit)) && !empty($indikator->data['rows']);
        if ($segar && !$paksa) {
            return null;
        }

        // Baru saja gagal: jangan membebani API (dan membuat halaman lambat) pada setiap pembukaan.
        $kunciGagal = "bps:gagal-segarkan:{$indikator->id}";
        if (!$paksa && ($galat = Cache::get($kunciGagal)) !== null) {
            return $galat;
        }

        // Satu pengambilan per indikator pada satu waktu. Permintaan lain memakai data tersimpan, atau bila
        // datanya belum ada sama sekali, menunggu pengambilan yang sedang berjalan selesai.
        $kunci = Cache::lock("bps:segarkan-indikator:{$indikator->id}", 120);
        if (!$kunci->get()) {
            if (empty($indikator->data)) {
                try {
                    $kunci->block(20);
                    $kunci->release();
                    $indikator->refresh();
                } catch (LockTimeoutException) {
                    // tetap tampil tanpa data; pembukaan berikutnya mencoba lagi
                }
            }

            return null;
        }

        try {
            // Satu tabel bisa butuh beberapa permintaan API; batas waktu PHP dihitung ulang dari sini.
            @set_time_limit(120);
            // Respons API di cache yang lebih muda dari batas segar boleh dipakai; $paksa = harus dari API.
            $this->bps->denganBatasHalaman(fn () => $this->perbarui($indikator, $paksa ? now()->getTimestamp() : now()->subMinutes($menit)->getTimestamp()));
            Cache::forget($kunciGagal);

            return null;
        } catch (\Throwable $e) {
            // Halaman tetap tampil dengan data tersimpan, apa pun penyebab kegagalannya.
            Log::warning("Data indikator {$indikator->id} (var {$indikator->bps_table_id}) gagal diambil dari WebAPI BPS: {$e->getMessage()}");
            $galat = $e instanceof BpsApiException ? $e->getMessage() : 'Terjadi kesalahan saat membaca data dari WebAPI BPS.';
            Cache::put($kunciGagal, $galat, now()->addMinutes(self::JEDA_GAGAL_MENIT));

            return $galat;
        } finally {
            $kunci->release();
        }
    }

    /**
     * Mengambil data terbaru (seluruh tahun) satu indikator tertaut dari API lalu menyimpannya. Respons cache
     * API yang diambil sebelum $segarSejak (timestamp; bawaan: sekarang) tidak dipakai, sehingga tahun dan
     * angka baru ikut terambil.
     */
    public function perbarui(Indicator $indikator, ?int $segarSejak = null): Indicator
    {
        if ($indikator->bps_source !== self::SUMBER || !$indikator->bps_table_id) {
            throw new BpsApiException("Indikator \"{$indikator->name}\" tidak tertaut ke tabel dinamis WebAPI BPS.");
        }

        $tabel = $this->denganCacheSegar($segarSejak ?? now()->getTimestamp(),
            fn () => $this->tabelDinamis((int) $indikator->bps_table_id, null, $indikator->bps_options ?? []));
        if (empty($tabel['matriks']['rows'])) {
            throw new BpsApiException("Tabel dinamis \"{$tabel['judul']}\" belum berisi data di WebAPI BPS.");
        }

        // updated_at hanya berubah bila isi datanya berubah (dashboard memakainya untuk indikator terbaru).
        $indikator->timestamps = $indikator->data !== $tabel['matriks'];
        try {
            $indikator->update([
                'data' => $tabel['matriks'],
                'unit' => $indikator->unit ?: (self::potong($tabel['satuan'], 50) ?: null),
                'bps_synced_at' => now(),
            ]);
        } finally {
            $indikator->timestamps = true;
        }

        return $indikator;
    }

    private function denganCacheSegar(?int $segarSejak, callable $ambil): mixed
    {
        $this->bps->segarSejak($segarSejak);
        try {
            return $ambil();
        } finally {
            $this->bps->segarSejak(null);
        }
    }

    // ===========================================
    // --- MENGAMBIL ISI TABEL DINAMIS ---
    // ===========================================

    /**
     * Satu tabel dinamis. $tahun: label tahun yang diambil ('2024', ...), atau null untuk SELURUH tahun
     * yang tersedia. $saring: pilihan judul baris/karakteristik/turunan tahun (kosong = semua).
     */
    public function tabelDinamis(int $idVar, ?array $tahun = null, array $saring = []): array
    {
        $tersedia = $this->bps->tahunVariabel($idVar); // [th_id => '2024'], terbaru dulu
        $dipilih = $tahun === null ? array_values($tersedia) : array_values(array_intersect($tersedia, array_map('strval', $tahun)));
        $thIds = array_keys(array_intersect($tersedia, $dipilih));
        $data = $thIds ? $this->bps->dataDinamis($idVar, $thIds) : [];

        $var = $data['var'][0] ?? [];
        $info = $var ? [] : $this->infoVariabel($idVar);

        return [
            'judul' => KonverterTabelBps::bersihkanTeks($var['label'] ?? '', buangTerjemahan: false) ?: ($info['judul'] ?? "Variabel {$idVar}"),
            'subjek' => $var['subj'] ?? ($info['subjek'] ?? ''),
            'satuan' => self::satuan($var['unit'] ?? ''),
            'tahunDipilih' => $dipilih,
            'matriks' => $data ? KonverterTabelBps::dariDinamis($data, $saring) : ['headers' => [], 'rows' => []],
            'catatan' => KonverterTabelBps::bersihkanTeks($var['note'] ?? '', buangTerjemahan: false),
            'diperbarui' => $data['last_update'] ?? null,
        ];
    }

    // Judul/subjek dari daftar tabel dinamis (tersimpan di cache), dipakai bila tahun yang dipilih tidak berisi data.
    private function infoVariabel(int $idVar): array
    {
        $v = collect($this->bps->katalogDinamis()['variabel'])->firstWhere('var_id', $idVar);

        return $v ? [
            'judul' => KonverterTabelBps::bersihkanTeks($v['title'] ?? '', buangTerjemahan: false),
            'subjek' => (string) ($v['sub_name'] ?? ''),
        ] : [];
    }

    // ===========================================
    // --- PEMBANTU ---
    // ===========================================

    /** Saringan tabel dinamis yang dibakukan (ID berurutan), atau null bila tanpa saringan. */
    public static function normalisasiOpsi(?array $opsi): ?array
    {
        $hasil = [];
        foreach (['baris', 'karakteristik', 'turtahun'] as $kunci) {
            if (!empty($opsi[$kunci])) {
                $id = array_values(array_unique(array_map('intval', $opsi[$kunci])));
                sort($id);
                $hasil[$kunci] = $id;
            }
        }

        return $hasil ?: null;
    }

    private static function kunciNama(string $nama): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $nama) ?? $nama));
    }

    /**
     * Subjek tujuan indikator baru: subjek PRANATA yang namanya sama dengan subjek BPS tabel ini (CSA, lalu
     * subjek lama). Bila belum ada, subjek CSA dibuat di kategori CSA-nya (kategori dibuat bila belum ada).
     */
    private function subjekTujuan(array $t): Subject
    {
        $namaSubjek = array_values(array_filter(array_map('trim', [$t['subjek'] ?? '', $t['subjekLain'] ?? ''])));
        foreach ($namaSubjek as $nama) {
            if ($subjek = Subject::whereRaw('LOWER(name) = ?', [mb_strtolower($nama)])->first()) {
                return $subjek;
            }
        }

        $namaKategori = trim($t['kategori'] ?? '') ?: 'Data API BPS';
        $kategori = Category::whereRaw('LOWER(name) = ?', [mb_strtolower($namaKategori)])->first()
            ?? Category::create(['name' => self::potong($namaKategori, 255)]);

        return Subject::firstOrCreate(['category_id' => $kategori->id, 'name' => self::potong($namaSubjek[0] ?? 'Lainnya', 255)]);
    }

    // graph_name WebAPI BPS menjadi jenis grafik dashboard ('line', 'bar', 'pie'); lainnya null.
    private static function grafik(string $nama): ?string
    {
        return match (strtolower(trim($nama))) {
            'line', 'garis' => 'line',
            'bar', 'column', 'batang' => 'bar',
            'pie', 'lingkaran' => 'pie',
            default => null,
        };
    }

    private static function satuan(?string $satuan): string
    {
        $satuan = trim((string) $satuan);

        return in_array(mb_strtolower($satuan), ['', '-', 'tidak ada satuan'], true) ? '' : $satuan;
    }

    private static function potong(string $teks, int $panjang): string
    {
        return mb_substr(trim($teks), 0, $panjang);
    }

    private static function menit(string $kunci, int $bawaan): int
    {
        return max(1, (int) config("services.bps.{$kunci}", $bawaan));
    }
}
