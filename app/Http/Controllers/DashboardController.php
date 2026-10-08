<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Models\Category;
use App\Models\Subject;
use App\Models\Indicator;
use App\Models\Narrative;
use App\Models\Setting;
use App\Services\Bps\SinkronisasiBps;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth; // <--- TAMBAHKAN INI

class DashboardController extends Controller
{
    // Variabel privat untuk cache GeoJSON agar tidak di-load berulang kali
    private $geoJsonData = null;

    /**
     * ====================================================================
     * FUNGSI INDEX UTAMA (DENGAN FILTER SUBJEK/INDIKATOR)
     * ====================================================================
     */
    public function index(Request $request, SinkronisasiBps $sinkron)
    {
        // Dashboard Pengguna terbuka untuk publik: tamu (belum login) memakai tampilan Pengguna.
        // (int): role_id bisa terbaca sebagai teks dari database, sedangkan match/in_array di bawah ketat.
        $roleId = Auth::check() ? (int) Auth::user()->role_id : null;

        // Tabel dinamis BPS yang baru muncul di API otomatis menjadi indikator (paling sering sekali per
        // BPS_KATALOG_MENIT; bila API bermasalah dashboard tetap tampil dengan data yang ada). Hanya saat
        // Admin/PJ membuka dashboard, agar Pengguna dan tamu tidak pernah menunggu daftar tabel dari API.
        if (in_array($roleId, [1, 3], true)) {
            $sinkron->cerminkanKatalogDiam();
        }

        $viewPath = match ($roleId) {
            1 => 'admin.dashboard',
            3 => 'penanggungjawab.dashboard',
            default => 'pengguna.dashboard',
        };

        $categories = Category::all();

        // ... (Bagian 0: Ambil Filter) ...
        // Halaman ini publik: hanya ID berupa bilangan bulat yang dipakai. Nilai lain dari alamat halaman
        // (mis. category_id[]=1 atau teks) dianggap tidak dipilih, bukan menimbulkan galat server.
        $ambilId = fn (string $kunci) => filter_var($request->query($kunci), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $selectedCategoryId = $ambilId('category_id');
        $selectedCategory = $selectedCategoryId ? Category::find($selectedCategoryId) : null;
        $title = $selectedCategory ? 'Dashboard: ' . $selectedCategory->name : 'Dashboard Utama';
        $selectedSubjectId = $ambilId('subject_id');
        $selectedIndicatorId = $ambilId('indicator_id');

        // ... (Bagian 1: Data Statistik - sudah benar) ...
        $statsSubjectQuery = Subject::query();
        $statsIndicatorQuery = Indicator::query();
        if ($selectedCategoryId) {
            $statsSubjectQuery->where('category_id', $selectedCategoryId);
            $statsIndicatorQuery->whereHas('subject', function ($q) use ($selectedCategoryId) {
                $q->where('category_id', $selectedCategoryId);
            });
        }
        try {
            $totalCategories = $selectedCategoryId ? 1 : Category::count();
            $totalSubjects = (clone $statsSubjectQuery)->count();
            $totalIndicators = (clone $statsIndicatorQuery)->count();
        } catch (\Exception $e) {
            $totalCategories = 0;
            $totalSubjects = 0;
            $totalIndicators = 0;
        }

        // ... (Bagian 2: Persiapan Filter Dropdown - sudah benar) ...
        $subjectsForFilter = collect();
        $indicatorsForFilter = collect();

        // HANYA muat daftar Subjek JIKA Kategori sudah dipilih di Sidebar
        if ($selectedCategoryId) {
            $subjectsForFilter = Subject::where('category_id', $selectedCategoryId)->orderBy('name')->get();
        }

        // HANYA muat daftar Indikator JIKA Subjek sudah dipilih di Dropdown
        if ($selectedSubjectId) {
            // Dropdown hanya butuh id & nama, tanpa kolom data JSON yang besar.
            $indicatorsForFilter = Indicator::where('subject_id', $selectedSubjectId)->orderBy('name')->get(['id', 'subject_id', 'name']);
        }
        // ... (Bagian 3: Persiapan Visualisasi - sudah benar) ...
        $indicatorsWithVisualization = []; // Set default kosong
        $galatApiBps = null;
        $dataApiKosong = false;

        // HANYA ambil data visualisasi JIKA Indikator sudah benar-benar dipilih
        if ($selectedIndicatorId) {
            // Indikator tabel dinamis BPS yang belum berdata diambil dari API saat pertama dibuka, lalu disimpan
            // agar filter AJAX, Lihat Data, dan narasi AI memakai data yang sama. Tamu hanya memicu pengambilan
            // pertama itu (dibatasi kunci per indikator dan jeda bila gagal); penyegaran data yang sudah ada
            // (BPS_SEGAR_MENIT) hanya untuk pengguna yang login.
            if ($indikatorDipilih = Indicator::find($selectedIndicatorId)) {
                if (Auth::check() || empty($indikatorDipilih->data)) {
                    $galatApiBps = $sinkron->pastikanSegar($indikatorDipilih);
                }
                $dataApiKosong = $indikatorDipilih->bps_source !== null && empty($indikatorDipilih->data);
            }
            $indicatorQuery = Indicator::query()->where('id', $selectedIndicatorId);
            $indicatorsWithVisualization = $this->prepareIndicatorsForVisualization($indicatorQuery);
        }
        // ... (Bagian 4: Return View - sudah benar) ...
        return view($viewPath, [
            'title' => $title,
            'selectedCategory' => $selectedCategory,
            'selectedCategoryId' => $selectedCategoryId,
            'totalCategories' => $totalCategories,
            'totalSubjects' => $totalSubjects,
            'totalIndicators' => $totalIndicators,
            'subjectsForFilter' => $subjectsForFilter,
            'indicatorsForFilter' => $indicatorsForFilter,
            'selectedSubjectId' => $selectedSubjectId ? intval($selectedSubjectId) : null,
            'selectedIndicatorId' => $selectedIndicatorId ? intval($selectedIndicatorId) : null,
            'indicatorsWithVisualization' => $indicatorsWithVisualization,
            'galatApiBps' => $galatApiBps,
            'dataApiKosong' => $dataApiKosong,
        ]);
    }

    /**
     * Helper untuk load GeoJSON sekali saja
     */
    private function getGeoJson()
    {
        if ($this->geoJsonData !== null) {
            return $this->geoJsonData;
        }
        $geoJsonPath = public_path('data/pematangsiantar.json');
        $this->geoJsonData = File::exists($geoJsonPath) ? json_decode(File::get($geoJsonPath)) : null;
        return $this->geoJsonData;
    }


    /**
     * Prepare indicators untuk visualisasi otomatis
     */
    private function prepareIndicatorsForVisualization($indicatorQuery)
    {
        $indicators = (clone $indicatorQuery)->with(['subject', 'narrative'])->get();
        $result = [];
        foreach ($indicators as $indicator) {
            $parsedData = $this->parseIndicatorData($indicator);
            if ($parsedData) {
                if (in_array('choropleth', $parsedData['available_types'])) {
                    $geoJson = $this->getGeoJson();
                    if ($geoJson === null) {
                        $parsedData['available_types'] = array_filter(
                            $parsedData['available_types'],
                            fn($t) => $t !== 'choropleth'
                        );
                    } else {
                        $parsedData['config']['geojson'] = $geoJson;
                    }
                }
                if (empty($parsedData['available_types'])) continue;

                // array_filter di atas bisa menyisakan kunci berlubang; JS butuh array JSON, bukan objek.
                $parsedData['available_types'] = array_values($parsedData['available_types']);
                $result[] = [
                    'id' => $indicator->id,
                    'name' => $indicator->name,
                    'unit' => $indicator->unit,
                    'subject' => $indicator->subject->name ?? 'N/A',
                    'parsed_data' => $parsedData['long_form'],
                    'available_types' => $parsedData['available_types'],
                    'filters' => $parsedData['filters'],
                    'visualization_config' => $parsedData['config'],
                    'narrative' => $indicator->narrative->content ?? '',
                    'narasi_info' => self::infoNarasi($indicator),
                    // Tabel dinamis BPS: waktu data diambil, dan jenis grafik yang disarankan BPS (graph_name).
                    'sumber_bps' => $indicator->bps_source ? [
                        'diperbarui' => $indicator->bps_synced_at?->timezone('Asia/Jakarta')->format('d-m-Y H:i'),
                        'grafik' => in_array($indicator->bps_chart, $parsedData['available_types'], true)
                            ? ['line' => 'Garis', 'bar' => 'Batang', 'pie' => 'Lingkaran'][$indicator->bps_chart] ?? null : null,
                    ] : null,
                ];
            }
        }
        return $result;
    }

    /**
     * Keterangan narasi yang sudah diterbitkan: waktu terakhir disimpan (WIB) dan apakah data indikator
     * sudah berubah sesudahnya (mis. diperbarui sinkron BPS), agar pembaca tahu narasinya mungkin tertinggal.
     */
    private static function infoNarasi(Indicator $indicator): ?array
    {
        $narasi = $indicator->narrative;
        if (!$narasi || blank($narasi->content)) {
            return null;
        }

        return [
            'diperbarui' => $narasi->updated_at?->timezone('Asia/Jakarta')->format('d-m-Y H:i'),
            'dataBerubah' => $narasi->dataBerubah($indicator->data),
        ];
    }

    private function parseIndicatorData($indicator)
    {
        $dataArray = $indicator->data;
        if (is_string($dataArray)) $dataArray = json_decode($dataArray, true);
        if (!isset($dataArray['headers']) || !isset($dataArray['rows'])) return null;

        // 1. GABUNGKAN HEADER DAN ROWS MENJADI GRID MENTAH
        $rawGrid = [];
        $rawGrid[] = $dataArray['headers'];
        foreach ($dataArray['rows'] as $row) {
            $rawGrid[] = $row;
        }

        // 2. BANGUN DENSE GRID
        $denseGrid = $this->buildDenseGrid($rawGrid);
        if (empty($denseGrid)) return null;

        // 3. DETEKSI BATAS HEADER DAN DATA
        $headerRowCount = 1;
        $numCols = count($denseGrid[0]);
        for ($i = 1; $i < count($denseGrid); $i++) {
            $numericCount = 0;
            $dataValueCount = 0;
            $yearCount = 0;

            for ($j = 1; $j < $numCols; $j++) {
                $val = trim(strval($denseGrid[$i][$j]));
                if ($val !== '') {
                    $dataValueCount++;
                    if ($this->isStrictNumeric($val)) $numericCount++;
                    if (preg_match('/^(19|20)\d{2}$/', $val)) $yearCount++;
                }
            }

            if ($yearCount > 0 && ($yearCount / $dataValueCount) >= 0.5) {
                $headerRowCount = $i + 1;
                continue;
            }

            if ($dataValueCount > 0 && ($numericCount / $dataValueCount) >= 0.5) {
                $headerRowCount = $i;
                break;
            }
        }

        // 4. GABUNGKAN MULTI-LEVEL HEADER SECARA VERTIKAL
        $flatHeaders = [];
        for ($c = 0; $c < $numCols; $c++) {
            $colHeaderParts = [];
            for ($r = 0; $r < $headerRowCount; $r++) {
                $val = trim(strval($denseGrid[$r][$c]));
                if (!empty($val) && !in_array($val, $colHeaderParts)) {
                    $colHeaderParts[] = $val;
                }
            }
            $headerName = implode(' - ', $colHeaderParts);
            if (empty($headerName)) $headerName = "Kolom_$c";

            $originalName = $headerName;
            $counter = 1;
            while (in_array($headerName, $flatHeaders)) {
                $headerName = $originalName . " ($counter)";
                $counter++;
            }
            $flatHeaders[$c] = $headerName;
        }

        // 5. TRANSFORMASI WIDE KE LONG (UNPIVOT)
        $temporalCols = [];
        $categoryCols = [];
        $unpivotMap = [];
        $variables = [];

        foreach ($flatHeaders as $c => $header) {
            // Deteksi jika header mengandung Tahun
            if (preg_match('/\b((19|20)\d{2})\b/', $header)) {
                // PERBAIKAN: Ambil tahun TERAKHIR jika ada beberapa tahun bertumpuk (misal: "SP2020 - 2021")
                preg_match_all('/\b((19|20)\d{2})\b/', $header, $allMatches);
                $year = end($allMatches[1]);

                $temporalCols[$c] = $year;

                // PERBAIKAN: Hapus HANYA angka tahun yang berada di akhir string menggunakan strrpos 
                // Ini mencegah bug di mana tahun 2020 pada teks "SP2020" ikut terhapus secara tidak sengaja.
                $pos = strrpos($header, $year);
                if ($pos !== false) {
                    $varName = substr_replace($header, '', $pos, strlen($year));
                } else {
                    $varName = str_replace($year, '', $header);
                }

                // --- TAMBAHAN: Deteksi dan Ekstraksi Bulan ---
                $monthFound = null;
                $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember', 'Tahunan'];
                foreach ($months as $m) {
                    if (stripos($varName, $m) !== false) {
                        $monthFound = $m;
                        // Bersihkan nama bulan dari varName agar tidak double
                        $varName = str_ireplace($m, '', $varName);
                        break;
                    }
                }

                // Masukkan info month ke mapping
                $unpivotMap[$c] = ['year' => $year, 'month' => $monthFound, 'var' => $varName];
                if (!empty($varName)) {
                    $variables[$varName] = true;
                }

                $varName = trim($varName, " -()");

                $unpivotMap[$c] = [
                    'year' => $year,
                    'month' => $monthFound,
                    'var' => $varName
                ];

                if (!empty($varName)) {
                    $variables[$varName] = true;
                }
            } else {
                $categoryCols[] = $c;
            }
        }

        $longForm = [];
        $isWideTemporal = count($temporalCols) > 1;

        if ($isWideTemporal) {
            // HAPUS PREFIX REDUNDAN (Membersihkan kata yang diulang-ulang)
            $varNamesList = array_keys($variables);
            if (count($varNamesList) > 1) {
                $prefix = $varNamesList[0];
                foreach ($varNamesList as $vn) {
                    while (strpos($vn, $prefix) !== 0 && $prefix !== '') {
                        $prefix = substr($prefix, 0, -1);
                    }
                }
                if (strlen(trim($prefix, " -")) > 3) {
                    foreach ($unpivotMap as $c => $meta) {
                        $cleanVar = trim(substr($meta['var'], strlen($prefix)), " -");
                        if (empty($cleanVar)) $cleanVar = "Total";
                        $unpivotMap[$c]['var'] = $cleanVar;
                    }
                }
            }

            $mainCategoryIndex = $categoryCols[0] ?? 0;
            $mainCategoryHeader = explode(' - ', $flatHeaders[$mainCategoryIndex])[0] ?? 'Kategori';

            // Proses Unpivot
            for ($r = $headerRowCount; $r < count($denseGrid); $r++) {
                $mainCategoryValue = $denseGrid[$r][$mainCategoryIndex];
                if (empty(trim(strval($mainCategoryValue)))) continue;

                foreach ($unpivotMap as $c => $meta) {
                    $year = $meta['year'];
                    $subCategory = empty($meta['var']) ? 'Total' : $meta['var'];
                    $month = $meta['month'];

                    $rawValue = $denseGrid[$r][$c];
                    $parsedValue = $this->parseNumericValue($rawValue);

                    if ($parsedValue !== null || trim(strval($rawValue)) !== '') {
                        $item = [
                            $mainCategoryHeader => $mainCategoryValue,
                            'Tahun' => $year,
                            'Nilai' => $parsedValue ?? $rawValue
                        ];

                        if ($month) {
                            $item['Bulan'] = $month;
                        }

                        if (count($variables) > 1 || !empty($meta['var'])) {
                            $item['Kategori'] = $subCategory;
                        }

                        $longForm[] = $item;
                    }
                }
            }
        } else {
            // Skenario Normal
            for ($r = $headerRowCount; $r < count($denseGrid); $r++) {
                $item = [];
                $hasData = false;
                foreach ($flatHeaders as $c => $header) {
                    $rawValue = $denseGrid[$r][$c];
                    $parsedValue = $this->parseNumericValue($rawValue);
                    $item[$header] = $parsedValue ?? $rawValue;
                    if (trim(strval($rawValue)) !== '') $hasData = true;
                }
                if ($hasData) $longForm[] = $item;
            }
        }

        if (empty($longForm)) return null;

        $finalHeaders = array_keys($longForm[0]);

        $columnTypes = $this->detectColumnTypes($finalHeaders, $longForm);
        $availableChartTypes = $this->detectChartTypes($columnTypes, $longForm);
        $filters = $this->generateFilterOptions($columnTypes, $longForm);
        $config = $this->generateVisualizationConfig($columnTypes, $longForm);

        return [
            'long_form' => $longForm,
            'available_types' => $availableChartTypes,
            'filters' => $filters,
            'config' => $config,
        ];
    }


    private function buildDenseGrid($sparseGrid)
    {
        $dense = [];
        $maxC = 0;

        foreach ($sparseGrid as $r => $row) {
            $c = 0;
            foreach ($row as $cell) {
                if (isset($cell['hidden']) && $cell['hidden'] === true) continue;

                while (isset($dense[$r][$c])) $c++;

                $value = $cell['value'] ?? '';
                $colspan = max(1, isset($cell['colspan']) ? (int)$cell['colspan'] : 1);
                $rowspan = max(1, isset($cell['rowspan']) ? (int)$cell['rowspan'] : 1);

                for ($rs = 0; $rs < $rowspan; $rs++) {
                    for ($cs = 0; $cs < $colspan; $cs++) {
                        $dense[$r + $rs][$c + $cs] = $value;
                    }
                }
                $c += $colspan;
                $maxC = max($maxC, $c);
            }
        }

        for ($r = 0; $r < count($dense); $r++) {
            for ($c = 0; $c < $maxC; $c++) {
                if (!isset($dense[$r][$c])) $dense[$r][$c] = '';
            }
            ksort($dense[$r]);
        }
        return $dense;
    }

    private function isStrictNumeric($value)
    {
        if ($value === null || trim(strval($value)) === '') return false;
        $clean = str_replace(['.', ','], '', trim(strval($value)));
        $clean = preg_replace('/\s+/', '', $clean);
        return is_numeric($clean);
    }
    /**
     * Deteksi tipe kolom (temporal, categorical, numeric, geographic)
     */
    private function detectColumnTypes($headers, $data)
    {
        $types = [];
        foreach ($headers as $header) {
            $sampleValues = array_slice(array_column($data, $header), 0, 10);
            if ($this->isTemporalColumn($header, $sampleValues)) $types[$header] = 'temporal';
            elseif ($this->isGeographicColumn($header, $sampleValues)) $types[$header] = 'geographic';
            elseif ($this->isNumericColumn($sampleValues)) $types[$header] = 'numeric';
            else $types[$header] = 'categorical';
        }
        return $types;
    }

    /**
     * ====================================================================
     * FUNGSI HELPER DETEKSI (INI YANG HILANG SEBELUMNYA)
     * ====================================================================
     */

    private function isTemporalColumn($header, $values)
    {
        $temporalKeywords = ['tahun', 'year', 'bulan', 'month', 'tanggal', 'date', 'periode'];
        $headerLower = strtolower($header);

        foreach ($temporalKeywords as $keyword) {
            if (strpos($headerLower, $keyword) !== false) return true;
        }

        // Cek apakah angka 4 digit tersebut MASUK AKAL sebagai tahun (1900 - 2099).
        // Ini mencegah nilai data murni seperti 2410, 3065, atau 8826 dideteksi sebagai tahun.
        $yearMatchCount = 0;
        $totalValid = 0;

        foreach ($values as $val) {
            if ($val !== null && trim($val) !== '') {
                $totalValid++;
                if (preg_match('/^(19|20)\d{2}$/', trim(strval($val)))) {
                    $yearMatchCount++;
                }
            }
        }

        // Hanya anggap sebagai kolom waktu jika lebih dari 80% isinya valid sebagai tahun
        return ($totalValid > 0 && ($yearMatchCount / $totalValid) > 0.8);
    }

    private function isGeographicColumn($header, $values)
    {
        $geoKeywords = ['kecamatan', 'kabupaten', 'provinsi', 'kota', 'desa', 'kelurahan', 'wilayah', 'region', 'kode_wilayah'];
        $headerLower = strtolower($header);

        // 1. CEGAHAN KATA KUNCI
        $metricKeywords = ['menurut', 'jumlah', 'persentase', 'kepadatan', 'total', 'indeks', 'angka', 'nilai', 'penduduk', 'kasus', 'rasio', 'proporsi'];
        foreach ($metricKeywords as $mk) {
            if (strpos($headerLower, $mk) !== false) {
                return false;
            }
        }

        // 2. CEGAHAN ISI DATA
        $numericCount = 0;
        $totalCount = 0;
        foreach ($values as $val) {
            $valStr = trim(strval($val));
            // PERBAIKAN: Abaikan karakter strip '-'
            if ($val === null || $valStr === '' || $valStr === '-' || $valStr === '--') continue;

            $totalCount++;

            if (is_numeric($valStr) || $this->parseNumericValue($valStr) !== null) {
                $numericCount++;
            }
        }

        if ($totalCount > 0 && ($numericCount / $totalCount) > 0.8) {
            return false;
        }

        // 3. SAHKAN SEBAGAI GEOGRAFI
        foreach ($geoKeywords as $keyword) {
            if (strpos($headerLower, $keyword) !== false) return true;
        }

        return false;
    }

    private function isNumericColumn($values)
    {
        $numericCount = 0;
        $totalCount = 0;

        foreach ($values as $val) {
            $valStr = trim(strval($val));
            // PERBAIKAN: Abaikan sel kosong DAN karakter strip '-' yang sering dipakai BPS
            if ($val === null || $valStr === '' || $valStr === '-' || $valStr === '--') continue;

            $totalCount++;

            // Cek apakah bisa dikonversi jadi angka (termasuk desimal)
            if (is_numeric($valStr) || $this->parseNumericValue($valStr) !== null) {
                $numericCount++;
            }
        }

        if ($totalCount === 0) return false;

        // Jika 80% data yang tidak kosong adalah numerik, sahkan sebagai numerik murni
        return ($numericCount / $totalCount) > 0.8;
    }

    /**
     * ====================================================================
     * FUNGSI "OTAK" BARU (PLURAL) - (Sudah Benar)
     * ====================================================================
     */
    private function detectChartTypes($columnTypes, $longForm)
    {
        $types = array_values($columnTypes);
        $counts = array_count_values($types);

        $numericCount = $counts['numeric'] ?? 0;
        $categoricalCount = $counts['categorical'] ?? 0;
        $temporalCount = $counts['temporal'] ?? 0;
        $geographicCount = $counts['geographic'] ?? 0;

        $availableTypes = [];

        $geoColumn = array_search('geographic', $columnTypes);
        $catColumn = array_search('categorical', $columnTypes);

        $uniqueGeoCount = $geoColumn ? count(array_unique(array_column($longForm, $geoColumn))) : 0;
        $uniqueCatCount = $catColumn ? count(array_unique(array_column($longForm, $catColumn))) : 0;

        // Deteksi jika data ini hanya mewakili 1 wilayah dan 1 kategori (Data Tunggal seperti UHH)
        $isSingleRowEntity = ($geographicCount > 0 && $uniqueGeoCount <= 1) && ($categoricalCount == 0 || $uniqueCatCount <= 1);

        // --- DETEKSI KHUSUS PIRAMIDA PENDUDUK ---
        $hasAgeGroup = false;
        foreach ($columnTypes as $col => $t) {
            if ($t === 'categorical' && (stripos($col, 'umur') !== false || stripos($col, 'usia') !== false)) {
                $hasAgeGroup = true;
                break;
            }
        }
        if ($hasAgeGroup && $numericCount >= 1 && $temporalCount >= 1) {
            $availableTypes[] = 'pyramid';
        }

        // 1. Peta Choropleth
        if ($geographicCount == 1 && $numericCount >= 1) {
            $availableTypes[] = 'choropleth';
        }

        // 2. Line Chart (Tren)
        if ($temporalCount >= 1 && $numericCount >= 1) {
            $availableTypes[] = 'line';
        }

        // 3. Bar Chart (Batang)
        if (($categoricalCount >= 1 || $geographicCount >= 1 || $numericCount > 1) && $numericCount >= 1) {
            if (!$isSingleRowEntity) {
                $availableTypes[] = 'bar';
            }
        }

        // 4. Pie Chart (Komposisi) - Proteksi Ketat Multi-Satuan
        if ($numericCount > 1 && !$isSingleRowEntity) {
            $availableTypes[] = 'pie';
        } else {
            $labelCol = $catColumn ?: $geoColumn;
            if ($labelCol && !$isSingleRowEntity) {
                $uniqueValues = array_unique(array_column($longForm, $labelCol));

                // CEGAHAN CERDAS: Deteksi satuan (unit) di dalam tanda kurung
                // Contoh: "Angka Harapan Hidup (Tahun)" vs "Pengeluaran (Ribu Rupiah)"
                $units = [];
                foreach ($uniqueValues as $val) {
                    if (preg_match('/\((.*?)\)/', $val, $match)) {
                        $units[] = strtolower(trim($match[1]));
                    } else {
                        $units[] = 'none';
                    }
                }
                $uniqueUnits = array_unique($units);

                // Hanya izinkan Pie Chart jika nilai uniknya 2 s.d 10 DAN satuannya seragam!
                if (count($uniqueValues) > 1 && count($uniqueValues) <= 10 && count($uniqueUnits) <= 1) {
                    $availableTypes[] = 'pie';
                }
            }
        }

        // 5. KOTAK DETAIL NILAI (Value Card)
        $availableTypes[] = 'card';

        $availableTypes[] = 'table';
        return array_unique($availableTypes);
    } // Akhir dari fungsi detectChartTypes

    private function generateVisualizationConfig($columnTypes, $data)
    {
        $categoricals = array_keys(array_filter($columnTypes, fn($t) => $t === 'categorical'));
        $temporals = array_keys(array_filter($columnTypes, fn($t) => $t === 'temporal'));
        $geographics = array_keys(array_filter($columnTypes, fn($t) => $t === 'geographic'));
        $numerics = array_keys(array_filter($columnTypes, fn($t) => $t === 'numeric'));

        $groupBy = null;
        if (count($temporals) >= 1 && (count($categoricals) >= 1 || count($geographics) >= 1)) {
            $groupBy = $categoricals[0] ?? $geographics[0] ?? null;
        } elseif (count($categoricals) >= 1 && count($geographics) >= 1) {
            $groupBy = $categoricals[0];
        }

        // Cari tahu kolom mana yang memegang data umur
        $ageColumn = null;
        foreach ($columnTypes as $col => $t) {
            if ($t === 'categorical' && (stripos($col, 'umur') !== false || stripos($col, 'usia') !== false)) {
                $ageColumn = $col;
                break;
            }
        }

        return [
            'x_axis_temporal' => $temporals[0] ?? null,
            'x_axis_categorical' => $geographics[0] ?? $categoricals[0] ?? null,
            'y_axis_numeric' => $numerics,
            'pie_label' => $categoricals[0] ?? $geographics[0] ?? null,
            'pie_value' => $numerics[0] ?? null,
            'geo_column' => $geographics[0] ?? null,
            'val_column' => $numerics[0] ?? null,
            'group_by' => $groupBy,
            'age_column' => $ageColumn // Masukkan ke config JS
        ];
    }


    /**
     * Generate filter options
     */
    private function generateFilterOptions($columnTypes, $data)
    {
        $filters = [];
        foreach ($columnTypes as $column => $type) {
            // --- PENGAMAN MUTLAK ---
            // Haram hukumnya membuat filter dropdown untuk kolom yang bernama "Nilai" atau "Value"
            $colNameLower = strtolower(trim($column));
            if ($colNameLower === 'nilai' || $colNameLower === 'value') {
                continue;
            }

            if (in_array($type, ['categorical', 'temporal', 'geographic'])) {
                $values = array_column($data, $column);
                $uniqueValues = array_unique(array_filter($values, fn($val) => $val !== null && $val !== ''));

                // PENGURUTAN CUSTOM UNTUK KALENDER
                usort($uniqueValues, function ($a, $b) {
                    $months = [
                        'januari' => 1,
                        'februari' => 2,
                        'maret' => 3,
                        'april' => 4,
                        'mei' => 5,
                        'juni' => 6,
                        'juli' => 7,
                        'agustus' => 8,
                        'september' => 9,
                        'oktober' => 10,
                        'november' => 11,
                        'desember' => 12,
                        'tahunan' => 13
                    ];

                    $aLower = strtolower(trim($a));
                    $bLower = strtolower(trim($b));

                    $aIndex = $months[$aLower] ?? 99;
                    $bIndex = $months[$bLower] ?? 99;

                    if ($aIndex !== 99 || $bIndex !== 99) {
                        if ($aIndex === $bIndex) return 0;
                        return $aIndex < $bIndex ? -1 : 1;
                    }

                    if (is_numeric($a) && is_numeric($b)) return $a <=> $b;
                    return strcasecmp($a, $b);
                });

                if (count($uniqueValues) > 1) {
                    $filters[$column] = ['type' => $type, 'options' => $uniqueValues,];
                }
            }
        }
        return $filters;
    }

    /**
     * AJAX Endpoint
     */
    public function getFilteredData(Request $request)
    {
        // Endpoint publik: input yang tidak sesuai dijawab 422, bukan galat server.
        // Nama kolom bisa memuat titik (mis. "Kab./Kota"), jadi isi filters diperiksa di sini, bukan dengan
        // aturan "filters.*"; nilai yang bukan teks/angka diabaikan.
        $input = $request->validate([
            'indicator_id' => 'required|integer',
            'filters' => 'nullable|array',
        ]);
        $indicatorId = $input['indicator_id'];
        $filters = $request->input('filters'); // bisa null (filters= kosong)
        $filters = array_filter(is_array($filters) ? $filters : [], fn ($nilai) => is_scalar($nilai));
        $indicator = Indicator::find($indicatorId);
        if (!$indicator) return response()->json(['error' => 'Indicator not found'], 404);

        $parsedData = $this->parseIndicatorData($indicator);
        if (!$parsedData) return response()->json(['error' => 'Invalid data structure'], 400);

        $filteredData = $parsedData['long_form'];
        // Kolom waktu yang tidak difilter di backend dan menjadi selected_year: selalu 'Tahun', sesuai yang
        // diharapkan JS dashboard. (config x_axis_temporal bisa menunjuk kolom lain pada tabel lebar, mis.
        // "Bulan" atau judul kolom yang memuat kata waktu, sehingga tidak dipakai di sini.)
        $temporalColumn = 'Tahun';

        $selectedYear = $filters[$temporalColumn] ?? null;

        foreach ($filters as $column => $value) {
            if ($value !== null && $value !== '') {
                // Jangan filter kolom waktu di backend
                if ($column === $temporalColumn) {
                    continue;
                }

                // --- BYPASS FILTER BULAN DI BACKEND ---
                // Kolom Bulan dijadikan Sumbu X pada mode tren bulanan.
                // Filter bulan ditangani oleh frontend (JS) agar semua bulan
                // tetap tampil di grafik dengan bulan terpilih diberi highlight detail.
                if (strtolower(trim($column)) === 'bulan') {
                    continue;
                }

                // --- BYPASS FILTER AGREGAT ---
                // Jika user memilih "Total" atau "Jumlah", biarkan datanya utuh 
                // agar Pie Chart tetap menerima data Laki-laki & Perempuan.
                $valLower = strtolower(trim(strval($value)));
                if ($valLower === 'total' || $valLower === 'jumlah' || str_starts_with($valLower, 'jumlah ') || str_starts_with($valLower, 'total ')) {
                    continue;
                }

                $filteredData = array_filter($filteredData, function ($item) use ($column, $value) {
                    return isset($item[$column]) && $item[$column] == $value;
                });
            }
        }

        return response()->json([
            'data' => array_values($filteredData),
            'unit' => $indicator->unit ?? '',
            'selected_year' => $selectedYear,
            'temporal_column' => $temporalColumn,
            'active_filters' => $filters // Kirimkan info filter aktif ke Frontend
        ]);
    }

    /**
     * ====================================================================
     * FUNGSI HELPER LAINNYA (INI JUGA HILANG SEBELUMNYA)
     * ====================================================================
     */

    private function normalizeKecamatanName($name)
    {
        if ($name === null) return null;
        $name = strtoupper(trim($name));
        $name = str_replace(' ', '', $name);
        $mapping = [
            'SIANTARMARIHAT' => 'SIANTARMARIHAT',
            'SIANTARMARIBUN' => 'SIANTARMARIMBUN',
            'SIANTARMARIMBUN' => 'SIANTARMARIMBUN',
            'SIANTARSELATAN' => 'SIANTARSELATAN',
            'SIANTARBARAT' => 'SIANTARBARAT',
            'SIANTARUTARA' => 'SIANTARUTARA',
            'SIANTARTIMUR' => 'SIANTARTIMUR',
            'SIANTARMARTOBA' => 'SIANTARMARTOBA',
            'SIANTARSITALASARI' => 'SIANTARSITALASARI',
            'PEMATANGSIANTAR' => 'PEMATANGSIANTAR',
        ];
        return $mapping[$name] ?? $name;
    }

    private function parseNumericValue($value)
    {
        // 1. Cek jika kosong
        if ($value === null || $value === '') return null;

        // Bersihkan spasi
        $value = trim(strval($value));

        // --- PENGAMAN MUTLAK (ANTI-HALUSINASI) ---
        // Jika string mengandung huruf abjad (A-Z, a-z), itu PASTI teks kategori
        // (Misalnya: "Bintang 3", "Tahunan", "Non Bintang"). Tolak mentah-mentah sebagai angka!
        if (preg_match('/[a-zA-Z]/', $value)) {
            return null;
        }

        // 2. PRIORITAS UTAMA: Cek Format Excel/Inggris (Contoh: 139.58)
        // Logika: Jika ada titik, tapi TIDAK ada koma, itu pasti desimal.
        if (strpos($value, '.') !== false && strpos($value, ',') === false) {
            // Pastikan isinya benar-benar angka dan titik saja
            if (is_numeric($value)) {
                return (float)$value;
            }
        }

        // 3. PRIORITAS KEDUA: Format Indonesia (Contoh: 1.000,58)
        // Jika lolos dari cek di atas, berarti formatnya mungkin Indonesia.
        // Hapus titik (pemisah ribuan)
        $valueClean = str_replace('.', '', $value);
        // Ganti koma jadi titik (desimal)
        $valueClean = str_replace(',', '.', $valueClean);

        // Bersihkan karakter non-angka (jaga-jaga ada simbol currency)
        $valueClean = preg_replace('/[^0-9.\-]/', '', $valueClean);

        if (is_numeric($valueClean)) {
            return (float)$valueClean;
        }

        return null;
    }

    // Menampilkan data dalam bentuk peta interaktif.
    public function map(Request $request)
    {
        return redirect()->route('admin.dashboard');
    }

    /**
     * ====================================================================
     * FUNGSI INTEGRASI AI (BARU)
     * ====================================================================
     */
    public function generateNarrative(Request $request, SinkronisasiBps $sinkron)
    {
        // 0. Hak akses: hanya Admin (1) dan Penanggung Jawab (3), sama seperti saveNarrative. Tombol
        // generate memang disembunyikan dari role lain, tetapi endpoint ini tetap bisa dipanggil langsung.
        $user = Auth::user();
        if (!$user || !in_array($user->role_id, [1, 3])) {
            return response()->json(['error' => 'Akses ditolak. Hanya Admin dan Penanggung Jawab yang dapat membuat narasi.'], 403);
        }

        // 1. Validasi Input
        $indicatorId = $request->input('indicator_id');
        if (!$indicatorId) {
            return response()->json(['error' => 'ID Indikator diperlukan'], 400);
        }

        // 2. Ambil Data Indikator beserta relasinya (Subject & Category)
        $indicator = Indicator::with(['subject.category'])->find($indicatorId);

        if (!$indicator) {
            return response()->json(['error' => 'Indikator tidak ditemukan'], 404);
        }

        // Indikator tabel dinamis BPS: AI menerima data terbaru dari API (seluruh tahun), sama dengan dashboard.
        $sinkron->pastikanSegar($indicator);

        // 3. Persiapkan Data Payload
        // Decode data JSON dari database (karena di DB tersimpan sebagai string/json column)
        // Kita decode jadi array PHP dulu, nanti Laravel HTTP Client akan meng-encode ulang jadi JSON rapi.
        $rawData = is_string($indicator->data) ? json_decode($indicator->data, true) : $indicator->data;

        // --- [BAGIAN BARU] AMBIL MODEL DARI DB ---
        $activeSetting = Setting::where('user_id', auth()->id())->where('key', 'active_ai_model')->first();
        // Default ke Llama 3.3 jika setting belum ada
        $selectedModel = $activeSetting ? $activeSetting->value : 'llama-3.3-70b-versatile';

        // Mapping key tampilan ke API model yang sebenarnya
        $modelApiMap = [
            'gemini-3-flash-preview'          => 'gemini-3-flash-preview',
            'gemini-3.5-flash'                => 'gemini-3.5-flash',
            'gemini-3-flash'                  => 'gemini-3-flash-preview', // Gemini 3 pakai API yang sama
            'meta-llama/Llama-3.3-70B-Instruct' => 'meta-llama/Llama-3.3-70B-Instruct',
            'openai/gpt-oss-120b'             => 'openai/gpt-oss-120b',
        ];
        $selectedModel = $modelApiMap[$selectedModel] ?? $selectedModel;
        // -----------------------------------------

        // Cek URL API dari .env (lewat config agar tetap terbaca setelah php artisan config:cache)
        $apiUrl = config('services.huggingface.url');
        if (!$apiUrl) {
            return response()->json(['error' => 'Konfigurasi API URL belum diset di .env'], 500);
        }

        // Beri PHP waktu sedikit di atas timeout HTTP (300 detik). Tanpa ini, PHP di Windows/Herd
        // berhenti setelah 30 detik menunggu AI dan browser hanya menerima halaman error, bukan JSON,
        // sehingga yang tampil "Gagal menghubungi server AI" padahal AI masih bekerja.
        set_time_limit(330);

        try {
            // 4. Kirim Request ke Hugging Face
            // Timeout 300 detik (sama dengan /check-missing-files di PengetahuanController).
            // Perlu selama ini karena: Space bisa perlu memuat model embedding dulu kalau
            // baru bangun dari tidur, model berpikir dengan thinking_level HIGH, dan worker
            // bisa mengulang sekali kalau hasil percobaan pertama rusak.
            $response = Http::timeout(300)->post($apiUrl . '/generate-narrative', [
                'model_id'  => $selectedModel, // <--- KIRIM ID MODEL DISINI
                'category'  => $indicator->subject->category->name ?? 'Umum',
                'subject'   => $indicator->subject->name ?? 'Umum',
                'indicator' => $indicator->name,
                'data_json' => $rawData
            ]);

            // 5. Cek Respon
            if ($response->successful()) {
                $result = $response->json();
                
                // Cek jika Python mengembalikan error internal
                if (isset($result['status']) && $result['status'] === 'error') {
                    return response()->json([
                        'error' => 'Gagal menghasilkan narasi',
                        'details' => 'Sistem AI mengalami kendala atau kuota habis. Silakan ulangi kembali.'
                    ], 500);
                }

                return response()->json([
                    'status' => 'success',
                    'narrative' => $result['narrative_result'] ?? 'AI tidak mengembalikan teks narasi.'
                ]);
            } else {
                // Jika HF error (misal 500 atau 422)
                return response()->json([
                    'error' => 'Gagal menghubungi AI Service',
                    'details' => $response->body()
                ], $response->status());
            }
        } catch (\Exception $e) {
            // Jika koneksi timeout atau error jaringan lainnya
            return response()->json([
                'error' => 'Terjadi kesalahan server',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // Menyimpan narasi yang telah dihasilkan atau diedit.
    public function saveNarrative(Request $request)
    {
        // 1. Cek Hak Akses lebih dulu (Role 1 = Admin, Role 3 = Penanggung Jawab), agar role lain tidak
        // mendapat pesan validasi yang membocorkan ID indikator mana yang ada.
        $user = Auth::user();
        if (!$user || !in_array($user->role_id, [1, 3])) {
            return response()->json(['error' => 'Unauthorized. Akses khusus Penanggung Jawab.'], 403);
        }

        // 2. Validasi Input
        $request->validate([
            'indicator_id' => 'required|exists:indicators,id',
            'narrative'    => 'required|string',
        ]);

        // 3. Simpan ke Database. Narasi langsung terbit di dashboard publik; sidik data dicatat agar narasi
        // ditandai bila data indikatornya berubah sesudah ini.
        $indicator = Indicator::find($request->indicator_id);
        $narasi = $indicator->narrative()->updateOrCreate(
            ['indicator_id' => $indicator->id],
            ['user_id' => $user->id, 'content' => $request->narrative, 'data_hash' => Narrative::sidikData($indicator->data)]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Narasi berhasil disimpan.',
            'diperbarui' => $narasi->updated_at?->timezone('Asia/Jakarta')->format('d-m-Y H:i'),
        ]);
    }
}
