<?php

namespace App\Services\Bps;

/**
 * Mengubah tabel dari WebAPI BPS menjadi matriks indikator PRANATA, yaitu format yang sama dengan
 * hasil impor Excel (DataController::importIndicator):
 *
 *   ['headers' => [sel, ...], 'rows' => [[sel, ...], ...]]
 *   sel = ['value' => string, 'colspan' => int, 'rowspan' => int, 'hidden' => bool]
 *
 * Seperti hasil impor Excel, grid selalu persegi: posisi yang tertutup sel gabungan diisi sel
 * 'hidden' (ekspor Excel memakai indeks sel sebagai nomor kolom). Judul kolom bertingkat disimpan
 * dengan baris judul pertama di 'headers', baris judul berikutnya di awal 'rows', dan sel judul kolom
 * pertama ber-rowspan setinggi seluruh baris judul. Halaman Lihat Data, ekspor, dan dashboard
 * membaca format ini, jadi data dari API langsung bisa ditampilkan dan divisualisasikan.
 * Angka disimpan sebagai teks berformat internasional ("1234.5"), sama seperti impor Excel.
 */
class KonverterTabelBps
{
    // ===========================================
    // --- TABEL DINAMIS (model=data) ---
    // ===========================================

    /**
     * Baris = vervar (misalnya kecamatan), kolom = turvar (bila ada) > tahun > turtahun (bila ada,
     * misalnya bulan/triwulan). Nilai diambil dari datacontent dengan kunci gabungan
     * vervar.var.turvar.tahun.turtahun. Kolom dan baris yang sama sekali tidak berisi data dibuang.
     *
     * @param array $saring pilihan pengguna seperti di situs BPS: ['baris' => [id vervar],
     *                      'karakteristik' => [id turvar], 'turtahun' => [id turtahun]]; kosong = semua.
     */
    public static function dariDinamis(array $respons, array $saring = []): array
    {
        $var = $respons['var'][0] ?? [];
        $idVar = (string) ($var['val'] ?? '');
        $desimal = is_numeric($var['decimal'] ?? null) ? (int) $var['decimal'] : null;
        $isi = $respons['datacontent'] ?? [];

        $pilih = function (array $daftar, string $kunci) use ($saring): array {
            $izin = array_map('strval', $saring[$kunci] ?? []);

            return $izin ? array_values(array_filter($daftar, fn ($x) => in_array((string) $x['val'], $izin, true))) : $daftar;
        };
        $vervar = $pilih($respons['vervar'] ?? [], 'baris');
        $turvar = $pilih(($respons['turvar'] ?? []) ?: [['val' => 0, 'label' => 'Tidak ada']], 'karakteristik');
        $turtahun = $pilih(($respons['turtahun'] ?? []) ?: [['val' => 0, 'label' => 'Tahun']], 'turtahun');
        $tahun = $respons['tahun'] ?? [];
        usort($tahun, fn ($a, $b) => strnatcmp((string) $a['label'], (string) $b['label']));

        $pakaiTurvar = !self::hanyaBawaan($turvar, 'tidak ada');
        $pakaiTurtahun = !self::hanyaBawaan($turtahun, 'tahun');

        $kolom = [];
        foreach ($turvar as $tv) {
            foreach ($tahun as $th) {
                foreach ($turtahun as $tt) {
                    $nilai = [];
                    foreach ($vervar as $i => $vv) {
                        $kunci = $vv['val'] . $idVar . $tv['val'] . $th['val'] . $tt['val'];
                        if (isset($isi[$kunci]) && $isi[$kunci] !== '') {
                            $nilai[$i] = self::teksAngka($isi[$kunci], $desimal);
                        }
                    }
                    if ($nilai === []) {
                        continue;
                    }

                    $judul = [];
                    if ($pakaiTurvar) {
                        $judul[] = self::bersihkanTeks($tv['label'], buangTerjemahan: false);
                    }
                    $judul[] = (string) $th['label'];
                    if ($pakaiTurtahun) {
                        $judul[] = self::bersihkanTeks($tt['label'], buangTerjemahan: false);
                    }
                    $kolom[] = ['judul' => $judul, 'nilai' => $nilai];
                }
            }
        }

        $baris = [];
        foreach ($vervar as $i => $vv) {
            $nilai = array_map(fn ($k) => $k['nilai'][$i] ?? '-', $kolom);
            if (array_filter($kolom, fn ($k) => isset($k['nilai'][$i]))) {
                $baris[] = ['label' => self::bersihkanTeks($vv['label'], buangTerjemahan: false), 'nilai' => $nilai];
            }
        }

        $judulBaris = self::bersihkanTeks($respons['labelvervar'] ?? '', buangTerjemahan: false) ?: 'Uraian';

        return self::susunMatriks($judulBaris, array_column($kolom, 'judul'), $baris);
    }

    private static function hanyaBawaan(array $daftar, string $labelBawaan): bool
    {
        if (count($daftar) !== 1) {
            return false;
        }

        return (string) ($daftar[0]['val'] ?? '') === '0'
            || mb_strtolower(trim((string) ($daftar[0]['label'] ?? ''))) === $labelBawaan;
    }

    private static function teksAngka(mixed $nilai, ?int $desimal): string
    {
        if (!is_int($nilai) && !is_float($nilai)) {
            return self::normalisasiAngka((string) $nilai);
        }
        if ($desimal !== null) {
            return number_format((float) $nilai, $desimal, '.', '');
        }
        if (is_int($nilai)) {
            return (string) $nilai;
        }

        $teks = (string) $nilai;

        return stripos($teks, 'e') === false ? $teks : rtrim(rtrim(sprintf('%.10F', $nilai), '0'), '.');
    }

    // ===========================================
    // --- TABEL SIMDASI (TABEL PUBLIKASI DALAM ANGKA) ---
    // ===========================================

    /**
     * Menggabungkan detail satu tabel SIMDASI dari beberapa tahun menjadi satu matriks: baris = label
     * baris SIMDASI (umumnya kecamatan), kolom = kolom SIMDASI > tahun. Kolom/tahun tanpa data dibuang.
     *
     * Struktur balasan SIMDASI berlapis dan nama kuncinya tidak seragam antartabel, jadi tabel dicari
     * di dalam respons (objek yang punya 'kolom' dan daftar baris), lalu nama kolom, label baris, dan nilai
     * dibaca dari beberapa kemungkinan nama kunci. Bila strukturnya tidak dikenali, hasilnya matriks
     * kosong; perintah "php artisan bps:cek simdasi <id_tabel>" menampilkan respons mentahnya.
     *
     * @param  array  $perTahun  [tahun => respons detail SIMDASI (id 25)]
     * @return array             ['matriks', 'judul', 'satuan', 'catatan']
     */
    public static function dariSimdasi(array $perTahun): array
    {
        ksort($perTahun);
        $kolomUrut = []; // kunci kolom => ['label' => [tingkat...], 'satuan' => string]
        $barisUrut = []; // kunci baris => label
        $nilai = [];     // [kunci baris][kunci kolom][tahun] => teks angka
        $tahunAda = [];
        $judul = $catatan = '';
        $adaKodeWilayah = $kodeKecamatan = 0;

        foreach ($perTahun as $tahun => $respons) {
            $tabel = self::cariTabelSimdasi($respons);
            if ($tabel === null) {
                continue;
            }
            $tahun = (string) $tahun;
            $tahunAda[$tahun] = true;
            $judul = self::bersihkanTeks((string) (self::pertama($tabel, ['judul', 'judul_tabel', 'title']) ?? $judul), buangTerjemahan: false);
            $catatan = trim(implode(' ', array_filter([
                self::bersihkanTeks((string) (self::pertama($tabel, ['catatan', 'keterangan', 'note']) ?? ''), buangTerjemahan: false),
                ($sumber = self::bersihkanTeks((string) (self::pertama($tabel, ['sumber', 'source']) ?? ''), buangTerjemahan: false)) !== '' ? "Sumber: {$sumber}" : '',
            ]))) ?: $catatan;

            $kolom = self::kolomSimdasi(self::pertama($tabel, ['kolom', 'columns']));
            $petaKolom = [];
            foreach ($kolom as $id => $k) {
                $kunciKolom = implode("\x1F", $k['label']);
                $kolomUrut[$kunciKolom] ??= $k;
                $petaKolom[$id] = $kunciKolom;
            }
            $urutanKolom = array_keys($petaKolom);

            foreach (self::pertama($tabel, ['data', 'baris', 'rows']) ?? [] as $baris) {
                if (!is_array($baris)) {
                    continue;
                }
                $label = self::bersihkanTeks((string) (self::pertama($baris, ['label', 'nama_wilayah', 'wilayah', 'nama', 'uraian', 'label_raw', 'kategori']) ?? ''), buangTerjemahan: false);
                if ($label === '') {
                    continue;
                }
                if (filled($kode = $baris['kode_wilayah'] ?? null)) {
                    $adaKodeWilayah++;
                    $kodeKecamatan += (int) !str_ends_with((string) $kode, '000');
                }

                $kunciBaris = mb_strtolower($label);
                $barisUrut[$kunciBaris] ??= $label;

                $isi = self::pertama($baris, ['variables', 'variabel', 'nilai', 'values', 'isi']);
                $isi = is_array($isi) ? $isi : $baris;
                foreach ($petaKolom as $id => $kunciKolom) {
                    // Nilai bisa dikunci dengan ID kolom, atau berupa daftar berurutan sesuai urutan kolom.
                    $mentah = array_key_exists($id, $isi) ? $isi[$id] : (array_is_list($isi) ? ($isi[array_search($id, $urutanKolom, true)] ?? null) : null);
                    if (($teks = self::nilaiSimdasi($mentah)) !== null) {
                        $nilai[$kunciBaris][$kunciKolom][$tahun] = $teks;
                    }
                }
            }
        }

        // Satuan: satu satuan untuk seluruh tabel bila semua kolom sama, selain itu ditulis di judul kolom.
        $satuan = array_values(array_unique(array_filter(array_column($kolomUrut, 'satuan'))));
        $satuanTabel = count($satuan) === 1 && count(array_filter(array_column($kolomUrut, 'satuan'))) === count($kolomUrut) ? $satuan[0] : '';
        $kedalaman = $kolomUrut ? max(array_map(fn ($k) => count($k['label']), $kolomUrut)) : 0;

        $judulKolom = $kunciNilai = [];
        foreach ($kolomUrut as $kunciKolom => $k) {
            $label = $k['label'];
            if ($satuanTabel === '' && $k['satuan'] !== '' && !str_contains(mb_strtolower(end($label)), mb_strtolower($k['satuan']))) {
                $label[count($label) - 1] .= " ({$k['satuan']})";
            }
            // Tingkat judul disamakan: kolom yang lebih dangkal diberi tingkat kosong di atasnya.
            $label = [...array_fill(0, $kedalaman - count($label), ''), ...$label];

            foreach (array_keys($tahunAda) as $tahun) {
                if (array_filter($nilai, fn ($b) => isset($b[$kunciKolom][$tahun]))) {
                    $judulKolom[] = [...$label, (string) $tahun];
                    $kunciNilai[] = [$kunciKolom, (string) $tahun];
                }
            }
        }

        $barisMatriks = [];
        foreach ($barisUrut as $kunciBaris => $label) {
            if (isset($nilai[$kunciBaris])) {
                $barisMatriks[] = ['label' => $label, 'nilai' => array_map(fn ($kn) => $nilai[$kunciBaris][$kn[0]][$kn[1]] ?? '-', $kunciNilai)];
            }
        }

        $judulBaris = match (true) {
            $adaKodeWilayah > 0 && $kodeKecamatan * 2 > $adaKodeWilayah => 'Kecamatan',
            $adaKodeWilayah > 0 => 'Wilayah',
            default => 'Uraian',
        };

        return [
            'matriks' => self::susunMatriks($judulBaris, $judulKolom, $barisMatriks),
            'judul' => $judul,
            'satuan' => $satuanTabel,
            'catatan' => $catatan,
        ];
    }

    // Objek tabel di dalam respons detail SIMDASI: objek yang memiliki 'kolom' dan daftar baris.
    private static function cariTabelSimdasi(mixed $json): ?array
    {
        if (!is_array($json)) {
            return null;
        }
        $baris = self::pertama($json, ['data', 'baris', 'rows']);
        if (is_array(self::pertama($json, ['kolom', 'columns'])) && is_array($baris)) {
            return $json;
        }
        foreach ($json as $isi) {
            if (is_array($isi) && ($tabel = self::cariTabelSimdasi($isi)) !== null) {
                return $tabel;
            }
        }

        return null;
    }

    /**
     * Kolom SIMDASI menjadi [id kolom => ['label' => [tingkat...], 'satuan' => string]]. Kolom bisa berupa
     * peta id => info atau daftar info; kolom bertingkat (turunan) diratakan dengan label induknya.
     */
    private static function kolomSimdasi(mixed $kolom, array $induk = []): array
    {
        $hasil = [];
        foreach (is_array($kolom) ? $kolom : [] as $kunci => $k) {
            if (!is_array($k)) {
                $hasil[(string) $kunci] = ['label' => [...$induk, self::bersihkanTeks((string) $k)], 'satuan' => ''];
                continue;
            }

            $id = (string) (self::pertama($k, ['id_kolom', 'id_var', 'id', 'kode', 'key']) ?? $kunci);
            $label = [...$induk, self::bersihkanTeks((string) (self::pertama($k, ['nama_variabel', 'label', 'nama', 'judul', 'name', 'variabel']) ?? $id))];
            if (is_string($turunan = self::pertama($k, ['nama_turunan_variabel', 'turunan_variabel', 'nama_turvar'])) && trim($turunan) !== '') {
                $label[] = self::bersihkanTeks($turunan);
            }

            $anak = self::pertama($k, ['turunan', 'children', 'sub', 'kolom']);
            if (is_array($anak) && $anak !== []) {
                $hasil += self::kolomSimdasi($anak, $label);
                continue;
            }

            $hasil[$id] = ['label' => $label, 'satuan' => self::bersihkanTeks((string) (self::pertama($k, ['satuan', 'unit']) ?? ''))];
        }

        return $hasil;
    }

    // Nilai sel SIMDASI ({value, value_raw} atau angka/teks langsung) menjadi teks angka; null bila kosong.
    private static function nilaiSimdasi(mixed $nilai): ?string
    {
        if (is_array($nilai)) {
            $mentah = $nilai['value_raw'] ?? $nilai['nilai_raw'] ?? null;
            $nilai = is_numeric($mentah) ? $mentah : ($nilai['value'] ?? $nilai['nilai'] ?? $mentah);
        }
        if ($nilai === null || is_array($nilai) || is_bool($nilai)) {
            return null;
        }

        $teks = self::teksAngka(is_string($nilai) ? self::bersihkanTeks($nilai, buangTerjemahan: false) : $nilai, null);

        return $teks === '' || $teks === '-' ? null : $teks;
    }

    // Nilai pertama yang tidak kosong dari beberapa kemungkinan nama kunci.
    private static function pertama(array $data, array $kunci): mixed
    {
        foreach ($kunci as $k) {
            if (isset($data[$k]) && $data[$k] !== '') {
                return $data[$k];
            }
        }

        return null;
    }

    // ===========================================
    // --- TABEL STATIS (HTML) ---
    // ===========================================

    /**
     * Tabel statis BPS berupa HTML (umumnya hasil ekspor Excel). Sel gabungan (colspan/rowspan) diurai ke
     * grid; baris judul tabel di atas, baris nomor kolom "(1) (2) ...", baris sumber/catatan di bawah,
     * kolom "No.", serta baris dan kolom kosong dibuang. Baris judul kolom adalah baris di atas baris data
     * pertama (baris yang sebagian besar selnya angka, bukan tahun).
     */
    public static function dariHtml(string $html): array
    {
        $kosong = ['headers' => [], 'rows' => []];
        if (!preg_match('/<table\b/i', $html) && preg_match('/&lt;table\b/i', $html)) {
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (!preg_match('/<table\b/i', $html)) {
            return $kosong;
        }

        $dom = new \DOMDocument();
        $lama = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"?><html><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($lama);

        // Tabel dengan baris terbanyak (tabel data kadang dibungkus tabel tata letak).
        $barisTr = [];
        foreach ($dom->getElementsByTagName('table') as $t) {
            if (count($b = self::barisTabelHtml($t)) > count($barisTr)) {
                $barisTr = $b;
            }
        }

        $grid = self::gridHtml($barisTr);
        $grid = self::buangKosong($grid);
        if (count($grid) < 2 || count($grid[0]) < 2) {
            return $kosong;
        }

        // Baris judul tabel di atas: satu-satunya sel berisi melebar >= separuh tabel, atau di kolom
        // pertama tanpa rowspan sementara sel lain kosong.
        while (count($grid) > 2 && self::barisJudulTabel($grid, 0)) {
            array_shift($grid);
        }

        $tinggi = self::tinggiJudulHtml($grid);

        // Baris nomor kolom "(1) (2) (3)" dan semua baris mulai "Sumber:"/"Catatan:" dibuang.
        $isi = [];
        foreach (array_slice($grid, $tinggi) as $baris) {
            $teks = array_values(array_unique(array_filter(array_column($baris, 'nilai'), fn ($v) => $v !== '')));
            if (count($teks) === 1 && preg_match('/^(sumber|source|catatan|note|keterangan|ket\.|\*)/i', $teks[0])) {
                break;
            }
            if ($teks && !array_filter($teks, fn ($v) => !preg_match('/^\(\d+\)$/', $v))) {
                continue;
            }
            $isi[] = $baris;
        }
        if ($isi === []) {
            return $kosong;
        }
        $grid = self::buangKolomNomor([...array_slice($grid, 0, $tinggi), ...$isi], $tinggi);
        $grid = self::buangKosong($grid);

        // Angka berformat Indonesia dinormalkan, kecuali kolom pertama (label baris) dan judul kolom.
        foreach ($grid as $r => &$baris) {
            foreach ($baris as $c => &$sel) {
                if ($r >= $tinggi && $c > 0) {
                    $sel['nilai'] = self::normalisasiAngka($sel['nilai']);
                }
            }
        }
        unset($baris, $sel);

        return self::matriksDariGrid($grid, $tinggi);
    }

    /** Baris <tr> milik tabel ini (langsung atau lewat thead/tbody/tfoot), tidak termasuk tabel bersarang. */
    private static function barisTabelHtml(\DOMElement $tabel): array
    {
        $baris = [];
        foreach ($tabel->childNodes as $anak) {
            $nama = $anak instanceof \DOMElement ? strtolower($anak->nodeName) : '';
            if ($nama === 'tr') {
                $baris[] = $anak;
            } elseif (in_array($nama, ['thead', 'tbody', 'tfoot'], true)) {
                foreach ($anak->childNodes as $tr) {
                    if ($tr instanceof \DOMElement && strtolower($tr->nodeName) === 'tr') {
                        $baris[] = $tr;
                    }
                }
            }
        }

        return $baris;
    }

    /**
     * Grid persegi dari baris <tr>: [baris][kolom] = ['nilai' => teks, 'asal' => nomor sel HTML asal].
     * Posisi yang tertutup sel gabungan berbagi nomor 'asal' yang sama.
     */
    private static function gridHtml(array $barisTr): array
    {
        $grid = [];
        $asal = 0;
        $jumlahBaris = count($barisTr);

        foreach ($barisTr as $r => $tr) {
            $c = 0;
            foreach ($tr->childNodes as $sel) {
                if (!$sel instanceof \DOMElement || !in_array(strtolower($sel->nodeName), ['td', 'th'], true)) {
                    continue;
                }
                while (isset($grid[$r][$c])) {
                    $c++;
                }
                $lebar = min(200, max(1, (int) $sel->getAttribute('colspan')));
                $tinggi = min($jumlahBaris - $r, max(1, (int) $sel->getAttribute('rowspan')));

                $html = '';
                foreach ($sel->childNodes as $isi) {
                    $html .= $sel->ownerDocument->saveHTML($isi);
                }
                $teks = self::bersihkanTeks($html);

                for ($i = 0; $i < $tinggi; $i++) {
                    for ($j = 0; $j < $lebar; $j++) {
                        $grid[$r + $i][$c + $j] = ['nilai' => $teks, 'asal' => $asal];
                    }
                }
                $asal++;
                $c += $lebar;
            }
        }

        $lebarGrid = 0;
        foreach ($grid as $baris) {
            $lebarGrid = max($lebarGrid, max(array_keys($baris)) + 1);
        }
        $hasil = [];
        for ($r = 0; $r < $jumlahBaris; $r++) {
            for ($c = 0; $c < $lebarGrid; $c++) {
                $hasil[$r][$c] = $grid[$r][$c] ?? ['nilai' => '', 'asal' => $asal++];
            }
        }

        return $hasil;
    }

    // Membuang baris dan kolom yang seluruh selnya kosong.
    private static function buangKosong(array $grid): array
    {
        $grid = array_values(array_filter($grid, fn ($b) => array_filter($b, fn ($s) => $s['nilai'] !== '')));
        if ($grid === []) {
            return [];
        }

        $kolomTerisi = array_keys(array_filter(array_keys($grid[0]), fn ($c) => array_filter($grid, fn ($b) => $b[$c]['nilai'] !== '')));

        return array_map(fn ($b) => array_values(array_intersect_key($b, array_flip($kolomTerisi))), $grid);
    }

    private static function barisJudulTabel(array $grid, int $r): bool
    {
        $asalTerisi = array_unique(array_column(array_filter($grid[$r], fn ($s) => $s['nilai'] !== ''), 'asal'));
        if (count($asalTerisi) !== 1) {
            return false;
        }
        $asal = reset($asalTerisi);
        if ($grid[$r][0]['asal'] !== $asal) {
            return false; // judul tabel selalu mulai dari kolom pertama
        }
        $lebar = count(array_filter($grid[$r], fn ($s) => $s['asal'] === $asal));
        $turun = isset($grid[$r + 1]) && $grid[$r + 1][0]['asal'] === $asal;

        return $lebar * 2 >= count($grid[$r]) || !$turun;
    }

    // Jumlah baris judul kolom: baris pertama selalu judul; baris berikutnya judul selama berupa tahun,
    // atau teks (bukan angka) yang kolom pertamanya masih bagian sel judul di atasnya.
    private static function tinggiJudulHtml(array $grid): int
    {
        $tinggi = 1;
        for ($r = 1, $jumlah = count($grid); $r < $jumlah; $r++) {
            $nilai = array_filter(array_column(array_slice($grid[$r], 1), 'nilai'), fn ($v) => $v !== '');
            $isi = max(1, count($nilai));
            $angka = count(array_filter($nilai, fn ($v) => is_numeric(self::normalisasiAngka($v))));
            $tahun = count(array_filter($nilai, fn ($v) => preg_match('/^(19|20)\d{2}\D{0,3}$/u', $v)));
            $gabungAtas = $grid[$r][0]['asal'] === $grid[$r - 1][0]['asal'];

            if ($tahun * 2 >= $isi && $tahun > 0) {
                $tinggi = $r + 1;
            } elseif ($angka * 2 >= $isi && $angka > 0) {
                break;
            } elseif ($gabungAtas || preg_match('/^\(\d+\)$/', (string) reset($nilai))) {
                $tinggi = $r + 1;
            } else {
                break;
            }
        }

        // Baris nomor kolom "(1) (2)" tidak dihitung sebagai judul.
        while ($tinggi > 1 && !array_filter(array_column($grid[$tinggi - 1], 'nilai'), fn ($v) => $v !== '' && !preg_match('/^\(\d+\)$/', $v))) {
            $tinggi--;
        }

        return min($tinggi, count($grid) - 1);
    }

    // Kolom pertama berjudul "No"/"Nomor" yang isinya nomor urut dibuang (bukan data).
    private static function buangKolomNomor(array $grid, int $tinggi): array
    {
        $judul = mb_strtolower(trim((string) $grid[0][0]['nilai'], " .:"));
        $nomor = array_filter(array_column(array_column(array_slice($grid, $tinggi), 0), 'nilai'), fn ($v) => $v !== '');
        if (count($grid[0]) > 2 && in_array($judul, ['no', 'nomor', 'no urut'], true) && !array_filter($nomor, fn ($v) => !preg_match('/^\d+\.?$/', $v))) {
            return array_map(fn ($b) => array_slice($b, 1), $grid);
        }

        return $grid;
    }

    /**
     * Grid (dengan nomor sel asal) menjadi matriks indikator. Sel gabungan dipotong di batas judul/isi.
     * Kolom pertama seluruh baris judul dijadikan satu sel ber-rowspan setinggi judul, sesuai aturan
     * matriks yang dibaca halaman Lihat Data.
     */
    private static function matriksDariGrid(array $grid, int $tinggi): array
    {
        $tinggi = max(1, min($tinggi, count($grid) - 1));
        $judulKolomPertama = implode(' ', array_unique(array_filter(array_column(array_column(array_slice($grid, 0, $tinggi), 0), 'nilai'))));
        foreach (range(0, $tinggi - 1) as $r) {
            $grid[$r][0] = ['nilai' => $judulKolomPertama ?: 'Uraian', 'asal' => 'judul-kolom-pertama'];
        }

        $hasil = [];
        foreach ([[0, $tinggi], [$tinggi, count($grid)]] as [$awal, $akhir]) {
            $sudah = [];
            for ($r = $awal; $r < $akhir; $r++) {
                $baris = [];
                foreach ($grid[$r] as $c => $sel) {
                    if (isset($sudah[$sel['asal']])) {
                        $baris[] = self::selTersembunyi();
                        continue;
                    }
                    $sudah[$sel['asal']] = true;
                    $lebar = 1;
                    while (isset($grid[$r][$c + $lebar]) && $grid[$r][$c + $lebar]['asal'] === $sel['asal']) {
                        $lebar++;
                    }
                    $turun = 1;
                    while ($r + $turun < $akhir && $grid[$r + $turun][$c]['asal'] === $sel['asal']) {
                        $turun++;
                    }
                    $baris[] = self::sel($sel['nilai'], $lebar, $turun);
                }
                $hasil[] = $baris;
            }
        }

        return ['headers' => $hasil[0], 'rows' => array_slice($hasil, 1)];
    }

    // ===========================================
    // --- PEMBANTU ---
    // ===========================================

    /**
     * @param string $judulBaris judul kolom pertama (label baris), misalnya "Kecamatan"
     * @param array  $judulKolom per kolom: label dari tingkat teratas ke terbawah, misalnya ['Laki-laki', '2024']
     * @param array  $baris      per baris: ['label' => string, 'nilai' => [teks per kolom]]
     */
    private static function susunMatriks(string $judulBaris, array $judulKolom, array $baris): array
    {
        if ($judulKolom === [] || $baris === []) {
            return ['headers' => [], 'rows' => []];
        }

        $tingkat = count($judulKolom[0]);
        $barisJudul = [];

        for ($t = 0; $t < $tingkat; $t++) {
            $sel = [$t === 0 ? self::sel($judulBaris, rowspan: $tingkat) : self::selTersembunyi()];

            // Kolom berurutan dengan label yang sama dari tingkat teratas sampai tingkat ini digabung.
            for ($k = 0, $jumlah = count($judulKolom); $k < $jumlah; $k += $lebar) {
                $awalan = array_slice($judulKolom[$k], 0, $t + 1);
                $lebar = 1;
                while ($k + $lebar < $jumlah && array_slice($judulKolom[$k + $lebar], 0, $t + 1) === $awalan) {
                    $lebar++;
                }
                $sel[] = self::sel($judulKolom[$k][$t], colspan: $lebar);
                for ($h = 1; $h < $lebar; $h++) {
                    $sel[] = self::selTersembunyi();
                }
            }
            $barisJudul[] = $sel;
        }

        $isi = array_map(
            fn ($b) => [self::sel($b['label']), ...array_map(fn ($v) => self::sel((string) $v), $b['nilai'])],
            $baris
        );

        return ['headers' => $barisJudul[0], 'rows' => [...array_slice($barisJudul, 1), ...$isi]];
    }

    private static function sel(string $nilai, int $colspan = 1, int $rowspan = 1): array
    {
        return ['value' => $nilai, 'colspan' => $colspan, 'rowspan' => $rowspan, 'hidden' => false];
    }

    private static function selTersembunyi(): array
    {
        return ['value' => '', 'colspan' => 1, 'rowspan' => 1, 'hidden' => true];
    }

    /**
     * Membersihkan teks berformat HTML dari API: nomor catatan kaki <sup> dibuang, terjemahan Inggris
     * dalam <i> dibuang (label berbentuk "Indonesia<br><i>English</i>"), tag lain dilepas, entitas HTML
     * diurai, dan spasi dirapikan.
     */
    public static function bersihkanTeks(?string $teks, bool $buangTerjemahan = true): string
    {
        $teks = preg_replace('#<sup\b[^>]*>.*?</sup>#is', '', (string) $teks) ?? (string) $teks;
        if ($buangTerjemahan) {
            $teks = preg_replace('#<(i|em)\b[^>]*>.*?</\1>#is', '', $teks) ?? $teks;
        }
        $teks = preg_replace('#<br\s*/?>|</(p|div|li|td|th)>#i', ' ', $teks) ?? $teks;
        $teks = html_entity_decode(strip_tags($teks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::rapikan($teks, ' /');
    }

    private static function rapikan(string $teks, string $tepiTambahan = ''): string
    {
        $teks = preg_replace('/[\s\x{00A0}]+/u', ' ', $teks) ?? $teks;

        return trim($teks, " \t\n\r\0\x0B" . $tepiTambahan);
    }

    /**
     * Angka berformat BPS/Indonesia menjadi format internasional: "122 098" -> "122098",
     * "1.234,56" -> "1234.56", "7,83" -> "7.83", "2010*)" -> "2010". Teks yang bukan angka
     * dikembalikan apa adanya; sel kosong atau tanda data tidak ada ("–", "...", "NA") menjadi "-".
     */
    public static function normalisasiAngka(string $teks): string
    {
        $teks = trim($teks);
        if ($teks === '' || in_array($teks, ['-', '–', '—', '...', '…', 'NA', 'N/A', 'n/a'], true)) {
            return '-';
        }

        $calon = preg_replace('/\s*\*+\)?$/u', '', $teks) ?? $teks;
        $calon = preg_replace('/(?<=\d)[ \x{00A0}](?=\d{3}(?!\d))/u', '', $calon) ?? $calon;

        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $calon)) {
            $calon = str_replace(['.', ','], ['', '.'], $calon);
        } elseif (preg_match('/^-?\d+,\d+$/', $calon)) {
            $calon = str_replace(',', '.', $calon);
        }

        return is_numeric($calon) ? $calon : $teks;
    }
}
