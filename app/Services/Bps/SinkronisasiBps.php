<?php

namespace App\Services\Bps;

use App\Models\Category;
use App\Models\Indicator;
use App\Models\Subject;

/**
 * Sinkronisasi tabel WebAPI BPS ke indikator PRANATA. Tiga sumber tabel BPS dipakai:
 * - 'dinamis': tabel dinamis (model var/data), diambil dengan SELURUH tahun yang tersedia,
 * - 'simdasi': tabel publikasi "... Dalam Angka" (SIMDASI), seluruh tahun digabung dalam satu tabel,
 * - 'statis' : tabel statis (HTML) di situs BPS.
 *
 * Indikator hasil impor menyimpan tautan ke tabel sumbernya (bps_source, bps_table_id, bps_options),
 * sehingga bisa diperbarui kapan saja dari halaman Data API BPS atau perintah "php artisan bps:sinkron".
 * Dashboard, Lihat Data, ekspor, dan narasi AI membaca indikator, jadi otomatis memakai data API terbaru.
 */
class SinkronisasiBps
{
    public const SUMBER = [
        'dinamis' => 'Tabel Dinamis',
        'simdasi' => 'Tabel Publikasi (SIMDASI)',
        'statis' => 'Tabel Statis',
    ];

    public function __construct(private BpsApiClient $bps)
    {
    }

    // ===========================================
    // --- KATALOG TABEL ---
    // ===========================================

    /**
     * Semua tabel satu sumber: [['sumber', 'id', 'judul', 'kategori', 'subjek', 'subjekLain', 'tahun', 'satuan'], ...].
     * 'tahun' berisi tahun yang tersedia bila API memberitahukannya (SIMDASI), selain itu kosong.
     */
    public function katalog(string $sumber): array
    {
        return match ($sumber) {
            'dinamis' => $this->katalogDinamis(),
            'simdasi' => $this->katalogSimdasi(),
            'statis' => $this->katalogStatis(),
            default => throw new BpsApiException("Sumber tabel \"{$sumber}\" tidak dikenal."),
        };
    }

    private function katalogDinamis(): array
    {
        $katalog = $this->bps->katalogDinamis();
        $kategori = collect($katalog['kategori'])->mapWithKeys(fn ($k) => [(int) $k['subcat_id'] => KonverterTabelBps::bersihkanTeks($k['title'] ?? '')]);
        $subjek = collect($katalog['subjek'])->mapWithKeys(fn ($s) => [(int) $s['sub_id'] => [
            'nama' => KonverterTabelBps::bersihkanTeks($s['title'] ?? ''),
            'kategori' => $kategori[(int) ($s['subcat_id'] ?? 0)] ?? '',
        ]]);

        return collect($katalog['variabel'])->map(fn ($v) => $this->butir('dinamis', (string) $v['var_id'], $v['title'] ?? '', [
            'kategori' => $subjek[(int) ($v['subcsa_id'] ?? 0)]['kategori'] ?? '',
            'subjek' => KonverterTabelBps::bersihkanTeks($v['subcsa_name'] ?? '') ?: ($subjek[(int) ($v['subcsa_id'] ?? 0)]['nama'] ?? ''),
            'subjekLain' => KonverterTabelBps::bersihkanTeks($v['sub_name'] ?? ''),
            'satuan' => self::satuan($v['unit'] ?? ''),
        ]))->values()->all();
    }

    private function katalogSimdasi(): array
    {
        return collect($this->bps->daftarSimdasi())
            ->filter(fn ($t) => filled($t['id_tabel'] ?? null))
            ->map(function ($t) {
                $tahun = collect(is_array($t['ketersediaan_tahun'] ?? null) ? $t['ketersediaan_tahun'] : explode(',', (string) ($t['ketersediaan_tahun'] ?? '')))
                    ->map(fn ($x) => (int) trim((string) $x))->filter(fn ($x) => $x >= 1900 && $x <= 2100)->unique()->sort()->values()->all();

                return $this->butir('simdasi', (string) $t['id_tabel'], $t['judul'] ?? '', [
                    'kode' => (string) ($t['kode_tabel'] ?? ''),
                    'kategori' => KonverterTabelBps::bersihkanTeks($t['bab'] ?? ''),
                    'subjek' => KonverterTabelBps::bersihkanTeks($t['subject'] ?? ''),
                    'tahun' => $tahun,
                ]);
            })
            ->sortBy(fn ($t) => self::urutanKode($t['kode']))
            ->values()->all();
    }

    private function katalogStatis(): array
    {
        $subjekLama = $this->bps->subjekLama();

        return collect($this->bps->daftarTabelStatis())
            ->filter(fn ($t) => filled($t['table_id'] ?? null))
            ->map(fn ($t) => $this->butir('statis', (string) $t['table_id'], $t['title'] ?? '', [
                'kategori' => KonverterTabelBps::bersihkanTeks($subjekLama[(int) ($t['subj_id'] ?? 0)]['kategori'] ?? ''),
                'subjek' => KonverterTabelBps::bersihkanTeks($t['subj'] ?? ($subjekLama[(int) ($t['subj_id'] ?? 0)]['subjek'] ?? '')),
                'diperbarui' => (string) ($t['updt_date'] ?? ''),
            ]))
            ->unique('id')->values()->all();
    }

    private function butir(string $sumber, string $id, string $judul, array $lain): array
    {
        return [
            'sumber' => $sumber,
            'id' => $id,
            'judul' => KonverterTabelBps::bersihkanTeks($judul, buangTerjemahan: false) ?: "Tabel {$id}",
            'kode' => '',
            'kategori' => '',
            'subjek' => '',
            'subjekLain' => '',
            'tahun' => [],
            'satuan' => '',
            'diperbarui' => '',
            ...$lain,
        ];
    }

    // Kode tabel SIMDASI "3.1.10" diurutkan per angka, bukan per huruf.
    private static function urutanKode(string $kode): string
    {
        return implode('.', array_map(fn ($b) => str_pad($b, 4, '0', STR_PAD_LEFT), explode('.', $kode)));
    }

    /** Satu butir katalog berdasarkan sumber dan ID (katalog diambil dari cache API bila ada). */
    public function cariDiKatalog(string $sumber, string $id): ?array
    {
        return collect($this->katalog($sumber))->firstWhere('id', $id);
    }

    // ===========================================
    // --- MENGAMBIL ISI TABEL ---
    // ===========================================

    /**
     * Isi satu tabel dari API dalam format indikator: ['judul', 'satuan', 'subjek', 'matriks', 'catatan',
     * 'tahun' (tahun yang berisi data), 'diperbarui'].
     */
    public function ambilTabel(string $sumber, string $id, array $opsi = []): array
    {
        return match ($sumber) {
            'dinamis' => $this->tabelDinamis((int) $id, null, $opsi),
            'simdasi' => $this->tabelSimdasi($id),
            'statis' => $this->tabelStatis((int) $id),
            default => throw new BpsApiException("Sumber tabel \"{$sumber}\" tidak dikenal."),
        };
    }

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
            'tahun' => collect($data['tahun'] ?? [])->pluck('label')->map(fn ($t) => (string) $t)->sort()->values()->all(),
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

    /** Tabel SIMDASI dengan seluruh tahun yang tersedia (ketersediaan_tahun di daftar SIMDASI). */
    public function tabelSimdasi(string $idTabel): array
    {
        $info = $this->cariDiKatalog('simdasi', $idTabel);
        if ($info === null) {
            throw new BpsApiException('Tabel SIMDASI ini tidak ada di daftar tabel SIMDASI wilayah ' . $this->bps->wilayahSimdasi() . '.');
        }
        // Tanpa daftar tahun, dicoba 10 tahun terakhir.
        $tahun = $info['tahun'] ?: range((int) date('Y') - 9, (int) date('Y'));
        $hasil = KonverterTabelBps::dariSimdasi($this->bps->tabelSimdasi($idTabel, $tahun));

        return [
            'judul' => $hasil['judul'] ?: $info['judul'],
            'subjek' => $info['subjek'],
            'satuan' => self::satuan($hasil['satuan']),
            'tahun' => self::tahunDiMatriks($hasil['matriks']),
            'matriks' => $hasil['matriks'],
            'catatan' => $hasil['catatan'],
            'diperbarui' => null,
        ];
    }

    public function tabelStatis(int $id): array
    {
        $data = $this->bps->tabelStatis($id);
        if ($data === null) {
            throw new BpsApiException("Tabel statis {$id} tidak ditemukan di WebAPI BPS.");
        }
        $matriks = KonverterTabelBps::dariHtml((string) ($data['table'] ?? ''));

        return [
            'judul' => KonverterTabelBps::bersihkanTeks($data['title'] ?? '', buangTerjemahan: false) ?: "Tabel statis {$id}",
            'subjek' => (string) ($data['subj'] ?? ''),
            'satuan' => '',
            'tahun' => self::tahunDiMatriks($matriks),
            'matriks' => $matriks,
            'catatan' => '',
            'diperbarui' => $data['updt_date'] ?? null,
        ];
    }

    private static function tahunDiMatriks(array $matriks): array
    {
        $tahun = [];
        foreach ([$matriks['headers'] ?? [], ...($matriks['rows'] ?? [])] as $baris) {
            foreach ($baris as $sel) {
                if (preg_match('/^(19|20)\d{2}$/', trim((string) ($sel['value'] ?? '')))) {
                    $tahun[$sel['value']] = true;
                }
            }
        }
        ksort($tahun);

        return array_map('strval', array_keys($tahun));
    }

    private static function satuan(?string $satuan): string
    {
        $satuan = trim((string) $satuan);

        return in_array(mb_strtolower($satuan), ['', '-', 'tidak ada satuan'], true) ? '' : $satuan;
    }

    // ===========================================
    // --- MENYIMPAN KE INDIKATOR ---
    // ===========================================

    /**
     * Mengambil satu tabel dari API lalu menyimpannya sebagai indikator:
     * 1. indikator yang sudah tertaut ke tabel ini (dengan saringan yang sama) diperbarui;
     * 2. bila belum ada, indikator tanpa tautan API yang namanya sama ditautkan dan datanya diganti;
     * 3. selain itu dibuat indikator baru di $subjekId, atau di subjek yang mengikuti klasifikasi BPS
     *    (subjek dengan nama yang sama dipakai; bila belum ada, kategori dan subjeknya dibuat).
     *
     * @return array ['indikator' => Indicator, 'status' => 'baru'|'diperbarui'|'ditautkan']
     */
    public function impor(string $sumber, string $id, array $opsi = [], ?int $subjekId = null, ?int $userId = null): array
    {
        $opsi = self::normalisasiOpsi($opsi);
        $tabel = $this->ambilTabel($sumber, $id, $opsi ?? []);
        if (empty($tabel['matriks']['rows'])) {
            throw new BpsApiException("Tabel \"{$tabel['judul']}\" tidak berisi data yang bisa dibaca, jadi tidak disimpan."
                . ($sumber === 'dinamis' ? '' : " Lihat respons API-nya dengan: php artisan bps:cek {$sumber} {$id} --mentah"));
        }

        $indikator = $this->indikatorTertaut($sumber, $id, $opsi);
        $status = 'diperbarui';
        if ($indikator === null && ($indikator = $this->indikatorBernamaSama($tabel['judul'])) !== null) {
            $status = 'ditautkan';
        }

        $nilai = [
            'data' => $tabel['matriks'],
            'bps_source' => $sumber,
            'bps_table_id' => $id,
            'bps_options' => $opsi,
            'bps_synced_at' => now(),
        ];

        if ($indikator) {
            // Nama dan subjek yang sudah diatur pengguna dipertahankan; satuan diisi bila masih kosong.
            $indikator->update($nilai + ['unit' => $indikator->unit ?: (self::potong($tabel['satuan'], 50) ?: null)]);

            return ['indikator' => $indikator, 'status' => $status];
        }

        $subjek = $subjekId ? Subject::findOrFail($subjekId) : $this->subjekTujuan($this->cariDiKatalogAman($sumber, $id) ?? ['subjek' => $tabel['subjek']]);
        $indikator = Indicator::create($nilai + [
            'subject_id' => $subjek->id,
            'user_id' => $userId,
            'name' => self::potong($tabel['judul'], 255),
            'unit' => self::potong($tabel['satuan'], 50) ?: null,
        ]);

        return ['indikator' => $indikator, 'status' => 'baru'];
    }

    /**
     * Memperbarui satu indikator yang tertaut ke WebAPI BPS dengan data terbaru (seluruh tahun). Cache API
     * yang lebih tua dari $segarSejak (bawaan: sekarang) tidak dipakai, sehingga tahun dan angka baru ikut
     * terambil. Perintah bps:sinkron memberi waktu mulainya, jadi daftar yang sama cukup diambil sekali.
     */
    public function perbarui(Indicator $indikator, ?int $segarSejak = null): Indicator
    {
        if (!$indikator->bps_source || !$indikator->bps_table_id) {
            throw new BpsApiException("Indikator \"{$indikator->name}\" tidak tertaut ke tabel WebAPI BPS.");
        }

        $this->bps->segarSejak($segarSejak ?? now()->getTimestamp());
        try {
            $tabel = $this->ambilTabel($indikator->bps_source, $indikator->bps_table_id, $indikator->bps_options ?? []);
        } finally {
            $this->bps->segarSejak(null);
        }
        if (empty($tabel['matriks']['rows'])) {
            throw new BpsApiException("Tabel sumber indikator \"{$indikator->name}\" tidak berisi data, jadi data lama dipertahankan.");
        }

        $indikator->update([
            'data' => $tabel['matriks'],
            'unit' => $indikator->unit ?: (self::potong($tabel['satuan'], 50) ?: null),
            'bps_synced_at' => now(),
        ]);

        return $indikator;
    }

    /**
     * Status tiap tabel katalog terhadap indikator yang ada: "sumber:id" => ['id', 'name'] untuk tabel yang
     * sudah tertaut, dan nama indikator (huruf kecil) => ['id', 'name'] untuk indikator tanpa tautan API.
     */
    public function statusIndikator(): array
    {
        $tertaut = [];
        $tanpaTautan = [];
        foreach (Indicator::query()->get(['id', 'name', 'bps_source', 'bps_table_id']) as $i) {
            if ($i->bps_source && $i->bps_table_id) {
                $tertaut["{$i->bps_source}:{$i->bps_table_id}"] ??= ['id' => $i->id, 'name' => $i->name];
            } else {
                $tanpaTautan[self::kunciNama($i->name)] ??= ['id' => $i->id, 'name' => $i->name];
            }
        }

        return ['tertaut' => $tertaut, 'tanpaTautan' => $tanpaTautan];
    }

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

    public static function kunciNama(string $nama): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $nama) ?? $nama));
    }

    private function indikatorTertaut(string $sumber, string $id, ?array $opsi): ?Indicator
    {
        return Indicator::where('bps_source', $sumber)->where('bps_table_id', $id)->get()
            ->first(fn (Indicator $i) => self::normalisasiOpsi($i->bps_options) === $opsi);
    }

    private function indikatorBernamaSama(string $judul): ?Indicator
    {
        $kunci = self::kunciNama($judul);

        return Indicator::whereNull('bps_source')->get(['id', 'name'])
            ->first(fn (Indicator $i) => self::kunciNama($i->name) === $kunci)
            ?->fresh();
    }

    // Katalog hanya dipakai untuk menentukan kategori/subjek tujuan; kegagalannya tidak menggagalkan impor.
    private function cariDiKatalogAman(string $sumber, string $id): ?array
    {
        try {
            return $this->cariDiKatalog($sumber, $id);
        } catch (BpsApiException) {
            return null;
        }
    }

    /**
     * Subjek tujuan indikator baru: subjek PRANATA yang namanya sama dengan subjek BPS tabel ini. Bila
     * belum ada, subjek dibuat di kategori yang namanya sama dengan kategori BPS (dibuat bila belum ada).
     */
    private function subjekTujuan(array $info): Subject
    {
        $namaSubjek = array_values(array_filter(array_map('trim', [$info['subjek'] ?? '', $info['subjekLain'] ?? ''])));
        foreach ($namaSubjek as $nama) {
            if ($subjek = Subject::whereRaw('LOWER(name) = ?', [mb_strtolower($nama)])->first()) {
                return $subjek;
            }
        }

        $namaKategori = trim($info['kategori'] ?? '') ?: 'Data API BPS';
        $kategori = Category::whereRaw('LOWER(name) = ?', [mb_strtolower($namaKategori)])->first()
            ?? Category::create(['name' => self::potong($namaKategori, 255)]);

        return Subject::firstOrCreate(['category_id' => $kategori->id, 'name' => self::potong($namaSubjek[0] ?? 'Lainnya', 255)]);
    }

    private static function potong(string $teks, int $panjang): string
    {
        return mb_substr(trim($teks), 0, $panjang);
    }
}
