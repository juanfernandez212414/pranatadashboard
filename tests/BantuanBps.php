<?php

// Pembantu tes WebAPI BPS (DataBpsTest, SinkronisasiBpsTest). Dimuat dari tests/Pest.php.
// Fixture di tests/Fixtures/bps: cuplikan respons asli domain 1273 (Oktober 2026).

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
                default => null,
            },
            str_ends_with($jalur, '/view') && ($q['model'] ?? '') === 'publication' => 'publikasi_detail.json',
            default => null,
        };

        return $berkas && is_file(__DIR__ . '/Fixtures/bps/' . $berkas)
            ? Http::response(fixtureBps($berkas))
            : Http::response('null');
    });
}
