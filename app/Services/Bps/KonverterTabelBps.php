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
