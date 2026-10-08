<?php

// Pembantu tes WebAPI BPS (DataBpsTest, SinkronisasiBpsTest). Dimuat dari tests/Pest.php.
//
// Fixture di tests/Fixtures/bps:
// - data_*, th_*, turvar_*, vervar_*, turth_*, var_daftar, kategori_csa, subjek_csa, publikasi_*:
//   cuplikan respons asli domain 1273 (Oktober 2026).
// - simdasi_*, statis_*, subjek_lama: disusun mengikuti contoh di dokumentasi WebAPI BPS
//   (webapi.bps.go.id/documentation), belum dicocokkan dengan respons asli.

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function fixtureBps(string $nama): array
{
    return json_decode(file_get_contents(__DIR__ . '/Fixtures/bps/' . $nama), true);
}

// Respons model=data var 31 untuk th tertentu, disusun dari fixture tahun 2020, 2023, dan 2024.
// Seperti API aslinya, tahun tanpa data tidak muncul dan permintaan tanpa data sama sekali dibalas "null".
function dataVar31(array $thIds): ?array
{
    $data = fixtureBps('data_31_2023_2024.json');
    $lama = fixtureBps('data_31_2020.json');
    $tahun = array_merge($lama['tahun'], $data['tahun']);
    $isi = $data['datacontent'] + $lama['datacontent'];

    // Kunci datacontent var 31: vervar . "31" . "0" . tahun(3 digit) . "0"
    $data['tahun'] = array_values(array_filter($tahun, fn ($t) => in_array((string) $t['val'], $thIds, true)));
    $data['datacontent'] = array_filter($isi, fn ($k) => in_array(substr((string) $k, -4, 3), $thIds, true), ARRAY_FILTER_USE_KEY);

    return $data['tahun'] ? $data : null;
}

// Membalas permintaan ke WebAPI BPS seperti server aslinya, berdasarkan jalur dan parameter query.
// $timpa: [potongan URL => fn (Request) => respons] untuk mengganti balasan tertentu.
function palsukanBps(array $timpa = []): void
{
    Http::fake(function (Request $request) use ($timpa) {
        $url = $request->url();
        foreach ($timpa as $potongan => $balasan) {
            if (str_contains($url, $potongan)) {
                return $balasan($request);
            }
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $jalur = (string) parse_url($url, PHP_URL_PATH);

        // SIMDASI ditulis bergaya jalur: .../simdasi/id/25/tahun/2024/id_tabel/.../wilayah/1273000/key/.../
        if (preg_match('#/interoperabilitas/datasource/simdasi/id/(\d+)/(.*)$#', $jalur, $m)) {
            $segmen = array_map('rawurldecode', explode('/', trim($m[2], '/')));
            $p = [];
            for ($i = 0; $i + 1 < count($segmen); $i += 2) {
                $p[$segmen[$i]] = $segmen[$i + 1];
            }
            $berkas = match ($m[1]) {
                '23' => ($p['wilayah'] ?? '') === '1273000' ? 'simdasi_daftar.json' : 'simdasi_tidak_ada.json',
                '25' => match ($p['id_tabel'] ?? '') {
                    'UFpWMmJZOVZlZTJnc1pXaHhDV1hPQT09' => "simdasi_luas_{$p['tahun']}.json",
                    'c2ltZGFzaS9wZW5kdWR1aw==' => "simdasi_penduduk_{$p['tahun']}.json",
                    default => 'simdasi_tidak_ada.json',
                },
                default => 'simdasi_tidak_ada.json',
            };

            return Http::response(fixtureBps(is_file(__DIR__ . '/Fixtures/bps/' . $berkas) ? $berkas : 'simdasi_tidak_ada.json'));
        }

        if (str_ends_with($jalur, '/download.php')) {
            return Http::response("%PDF-1.4\n% PDF uji\n", 200, ['Content-Type' => 'application/pdf']);
        }
        if (str_ends_with($jalur, '/list') && ($q['model'] ?? '') === 'data') {
            $th = explode(';', $q['th'] ?? '');
            $data = match ($q['var'] ?? '') {
                '31' => dataVar31($th),
                '32' => in_array('124', $th, true) ? fixtureBps('data_32_2024.json') : null, // var 32 hanya berisi 2024
                default => null,
            };

            return $data ? Http::response($data) : Http::response('null');
        }

        $var = $q['var'] ?? '';
        $berkas = match (true) {
            str_ends_with($jalur, '/list') => match ($q['model'] ?? '') {
                'th' => $var === '32' ? 'th_32.json' : (($q['page'] ?? '1') === '2' ? 'th_31_hal2.json' : 'th_31_hal1.json'),
                'var' => 'var_daftar.json',
                'subjectcsa' => 'subjek_csa.json',
                'subcatcsa' => 'kategori_csa.json',
                'turvar' => "turvar_{$var}.json",
                'vervar' => "vervar_{$var}.json",
                'turth' => 'turth_tahunan.json',
                'publication' => 'publikasi_daftar.json',
                'statictable' => 'statis_daftar.json',
                'subject' => 'subjek_lama.json',
                default => null,
            },
            str_ends_with($jalur, '/view') && ($q['model'] ?? '') === 'publication' => 'publikasi_detail.json',
            str_ends_with($jalur, '/view') && ($q['model'] ?? '') === 'statictable' => 'statis_detail_' . ($q['id'] ?? '') . '.json',
            default => null,
        };

        return $berkas && is_file(__DIR__ . '/Fixtures/bps/' . $berkas)
            ? Http::response(fixtureBps($berkas))
            : Http::response('null');
    });
}
