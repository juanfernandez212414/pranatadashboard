<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Subject;
use App\Models\Indicator;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // <-- TAMBAHAN WAJIB
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\IndicatorExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class DataController extends Controller
{
    /**
     * Memeriksa apakah user memiliki hak akses Kelola Data (Admin & PJ)
     */
    private function checkManageAccess()
    {
        $user = Auth::user();
        // Hanya Role 1 (Admin) dan Role 3 (Penanggung Jawab) yang boleh mengelola data
        if (!in_array($user->role_id, [1, 3])) {
            abort(403, 'Akses Ditolak. Hanya Admin dan Penanggung Jawab yang dapat mengelola data.');
        }
    }

    /**
     * TAMPILAN UTAMA KELOLA DATA
     */
    public function index(Request $request)
    {
        $this->checkManageAccess(); // Proteksi Akses

        $categories = Category::with('subjects')->orderBy('name')->get();
        
        // Pencarian dan Filter Indikator via Backend
        $search = $request->input('search');
        $categoryId = $request->input('category_id');
        $subjectId = $request->input('subject_id');

        $query = Indicator::with('subject.category')->latest();

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        } elseif ($categoryId) {
            $query->whereHas('subject', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        $indicators = $query->paginate(10)->withQueryString();
        
        $allSubjects = Subject::with('category')->orderBy('name')->get();

        $user = Auth::user();
        $viewPath = ($user->role_id == 1) ? 'admin.keloladata' : 'penanggungjawab.keloladata';

        return view($viewPath, [
            'categories' => $categories,
            'indicators' => $indicators,
            'allSubjects' => $allSubjects,
        ]);
    }

    // ===========================================
    // --- KATEGORI ---
    // ===========================================
    public function storeCategory(Request $request)
    {
        $this->checkManageAccess();

        $request->validate(['name' => 'required|string|max:255|unique:categories,name']);
        Category::create(['name' => $request->name]);
        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    // Memperbarui kategori data yang ada.
    public function updateCategory(Request $request, Category $category)
    {
        $this->checkManageAccess();

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
        ]);
        $category->update(['name' => $request->name]);
        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    // Menghapus kategori data.
    public function destroyCategory(Category $category)
    {
        $this->checkManageAccess();

        $category->delete();
        return back()->with('success', 'Kategori berhasil dihapus.');
    }

    // ===========================================
    // --- SUBJEK ---
    // ===========================================
    public function storeSubject(Request $request)
    {
        $this->checkManageAccess();

        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
        ]);
        Subject::create($request->all());
        return back()->with('success', 'Subjek berhasil ditambahkan.');
    }

    // Memperbarui subjek data yang ada.
    public function updateSubject(Request $request, Subject $subject)
    {
        $this->checkManageAccess();

        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
        ]);
        $subject->update($request->all());
        return back()->with('success', 'Subjek berhasil diperbarui.');
    }

    // Menghapus subjek data.
    public function destroySubject(Subject $subject)
    {
        $this->checkManageAccess();

        $subject->delete();
        return back()->with('success', 'Subjek berhasil dihapus.');
    }

    // ===========================================
    // --- INDIKATOR ---
    // ===========================================
    public function storeIndicator(Request $request)
    {
        $this->checkManageAccess();

        $validated = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'matrix_data' => 'required|json',
        ]);

        try {
            $tableData = json_decode($validated['matrix_data'], true);
            if (empty($tableData['headers']) || empty($tableData['rows'])) {
                return back()->withErrors(['matrix_data' => 'Data tabel tidak boleh kosong.']);
            }

            // --- MULAI KODE TRANSFORMASI ANGKA INDONESIA KE INTERNASIONAL ---
            if (isset($tableData['rows'])) {
                foreach ($tableData['rows'] as &$row) {
                    foreach ($row as &$cell) {
                        if (isset($cell['value'])) {
                            // Jadikan string dan hilangkan spasi di awal/akhir untuk berjaga-jaga
                            $val = trim((string) $cell['value']);

                            // 1. Cek apakah format angka Indonesia (Contoh: 1.234 atau 1.234,56)
                            // Syarat: Ada titik diikuti 3 angka, dan opsional ada koma di belakang
                            if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $val)) {
                                $val = str_replace('.', '', $val);  // Hapus titik ribuan
                                $val = str_replace(',', '.', $val); // Ubah koma jadi titik desimal
                            }
                            // 2. Cek apakah desimal pakai koma saja tanpa ribuan (Contoh: 1,31)
                            elseif (preg_match('/^-?\d+(,\d+)?$/', $val)) {
                                $val = str_replace(',', '.', $val); // Ubah koma jadi titik desimal
                            }

                            // 3. Simpan sebagai String (Format Seragam)
                            if (is_numeric($val)) {
                                // Menghapus nol desimal yang tidak perlu (opsional) namun tetap menjadikannya string
                                // Atau langsung simpan $val. Karena user minta string, kita cast ke string.
                                $cell['value'] = (string) (floatval($val) == $val ? $val : floatval($val));
                            } else {
                                $cell['value'] = $val;
                            }
                        }
                    }
                }
                unset($row, $cell); // Bersihkan referensi memori agar aman
            }
            // --- AKHIR KODE TRANSFORMASI ---

            Log::info('DEBUG storeIndicator - Auth::id(): ' . (Auth::id() ?? 'NULL') . ' | Auth::check(): ' . (Auth::check() ? 'true' : 'false'));

            Indicator::create([
                'subject_id' => $validated['subject_id'],
                'user_id'    => Auth::id(), // Audit trail: catat siapa yang membuat
                'name'       => $validated['name'],
                'unit'       => $validated['unit'],
                'data'       => $tableData,
            ]);

            return back()->with('success', 'Indikator tabel berhasil ditambahkan.');
        } catch (Exception $e) {
            Log::error('Error Store Matrix Indicator: ' . $e->getMessage());
            return back()->withErrors(['matrix_data' => 'Gagal menyimpan data tabel. Error: ' . $e->getMessage()]);
        }
    }

    // Memperbarui indikator data yang ada.
    public function updateIndicator(Request $request, Indicator $indicator)
    {
        $this->checkManageAccess();

        $validated = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'matrix_data' => 'required|json',
        ]);

        try {
            $tableData = json_decode($validated['matrix_data'], true);
            if (empty($tableData['headers']) || empty($tableData['rows'])) {
                return back()->withErrors(['matrix_data' => 'Data tabel tidak boleh kosong.']);
            }

            // --- MULAI KODE TRANSFORMASI ANGKA INDONESIA KE INTERNASIONAL ---
            if (isset($tableData['rows'])) {
                foreach ($tableData['rows'] as &$row) {
                    foreach ($row as &$cell) {
                        if (isset($cell['value'])) {
                            // Jadikan string dan hilangkan spasi di awal/akhir
                            $val = trim((string) $cell['value']);

                            // 1. Cek apakah format angka Indonesia (Contoh: 1.234 atau 1.234,56)
                            if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $val)) {
                                $val = str_replace('.', '', $val);  // Hapus titik ribuan
                                $val = str_replace(',', '.', $val); // Ubah koma jadi titik desimal
                            }
                            // 2. Cek apakah desimal pakai koma saja tanpa ribuan (Contoh: 1,31)
                            elseif (preg_match('/^-?\d+(,\d+)?$/', $val)) {
                                $val = str_replace(',', '.', $val); // Ubah koma jadi titik desimal
                            }

                            // 3. Simpan sebagai String (Format Seragam)
                            if (is_numeric($val)) {
                                $cell['value'] = (string) (floatval($val) == $val ? $val : floatval($val));
                            } else {
                                $cell['value'] = $val;
                            }
                        }
                    }
                }
                unset($row, $cell); // Bersihkan referensi memori
            }
            // --- AKHIR KODE TRANSFORMASI ---

            $indicator->update([
                'subject_id' => $validated['subject_id'],
                'name' => $validated['name'],
                'unit' => $validated['unit'],
                'data' => $tableData,
            ]);

            return back()->with('success', 'Indikator tabel berhasil diupdate.');
        } catch (Exception $e) {
            Log::error('Error Update Matrix Indicator: ' . $e->getMessage());
            return back()->withErrors(['matrix_data' => 'Gagal mengupdate data tabel. Error: ' . $e->getMessage()]);
        }
    }

    // Menghapus indikator data.
    public function destroyIndicator(Indicator $indicator)
    {
        $this->checkManageAccess();

        $indicator->delete();
        return back()->with('success', 'Indikator berhasil dihapus.');
    }

    // ===========================================
    // --- FUNGSI BARU UNTUK HALAMAN LIHAT DATA ---
    // (BISA DIAKSES OLEH SEMUA ROLE, VIEW DINAMIS)
    // ===========================================
    public function showDataView()
    {
        $categories = Category::with(['subjects' => function ($query) {
            // 🎯 PERBAIKAN: Jangan panggil kolom 'data' JSON yang raksasa!
            // Kita hanya memanggil ID, nama, unit, dan waktu pembuatan.
            $query->with(['indicators' => function ($q) {
                $q->select('id', 'subject_id', 'name', 'unit', 'created_at', 'updated_at')
                    ->orderBy('name');
            }])->orderBy('name');
        }])->orderBy('name')->get();

        // View dinamis berdasarkan role
        $user = Auth::user();
        if ($user->role_id == 1) {
            $viewPath = 'admin.lihatdata';
        } elseif ($user->role_id == 3) {
            $viewPath = 'penanggungjawab.lihatdata';
        } else {
            $viewPath = 'pengguna.lihatdata';
        }

        return view($viewPath, [
            'categories' => $categories,
        ]);
    }

    // Menampilkan detail data beserta grafiknya.
    public function showDataDetail($id)
    {
        $indicator = Indicator::with('subject.category')->findOrFail($id);

        // View dinamis berdasarkan role
        $user = Auth::user();
        if ($user->role_id == 1) {
            $viewPath = 'admin.tampilandata';
        } elseif ($user->role_id == 3) {
            $viewPath = 'penanggungjawab.tampilandata';
        } else {
            $viewPath = 'pengguna.tampilandata';
        }

        return view($viewPath, compact('indicator'));
    }

    // ===========================================
    // --- EXPORT & IMPORT ---
    // ===========================================
    public function exportExcel($id)
    {
        // Semua role bisa export
        $indicator = Indicator::findOrFail($id);
        $fileName = 'Data_Indikator_' . Str::slug($indicator->name) . '.xlsx';
        return Excel::download(new IndicatorExport($indicator), $fileName);
    }

    // Mengekspor data indikator ke format file PDF.
    public function exportPdf($id)
    {
        // Semua role bisa export
        $indicator = Indicator::with('subject.category')->findOrFail($id);
        $fileName = 'Data_Indikator_' . Str::slug($indicator->name) . '.pdf';

        // KODE BARU: Mengarah langsung ke folder views
        $pdf = Pdf::loadView('datapdf', compact('indicator'));

        return $pdf->download($fileName);
    }

    // Mengimpor data indikator dari file Excel.
    public function importIndicator(Request $request)
    {
        $this->checkManageAccess();

        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'name'       => 'required|string|max:255',
            'unit'       => 'nullable|string|max:50',
            'excel_file' => 'required|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            $file = $request->file('excel_file');

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();

            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            if ($highestRow < 2) {
                throw new Exception("Excel harus memiliki minimal 1 baris header dan 1 baris data.");
            }

            // =======================================================
            // 🎯 PERBAIKAN FINAL: PEMOTONGAN BARIS & KOLOM HANTU
            // =======================================================
            // 1. Cari Kolom Asli (Abaikan kolom koma/kosong di kanan)
            $realHighestColumnIndex = 1;
            $maxRowToCheck = min(5, $highestRow);
            for ($rowCheck = 1; $rowCheck <= $maxRowToCheck; $rowCheck++) {
                for ($colCheck = $highestColumnIndex; $colCheck > $realHighestColumnIndex; $colCheck--) {
                    $cellVal = $sheet->getCellByColumnAndRow($colCheck, $rowCheck)->getCalculatedValue();
                    if ($cellVal !== null && trim((string)$cellVal) !== '') {
                        $realHighestColumnIndex = $colCheck;
                        break;
                    }
                }
            }
            $highestColumnIndex = $realHighestColumnIndex;

            // 2. Cari Baris Asli (Abaikan baris hantu Z1000 dari bawah)
            $realHighestRow = 1;
            for ($rowCheck = $highestRow; $rowCheck >= 1; $rowCheck--) {
                $isEmpty = true;
                for ($colCheck = 1; $colCheck <= $highestColumnIndex; $colCheck++) {
                    $cellVal = $sheet->getCellByColumnAndRow($colCheck, $rowCheck)->getCalculatedValue();
                    if ($cellVal !== null && trim((string)$cellVal) !== '') {
                        $isEmpty = false;
                        break;
                    }
                }
                if (!$isEmpty) {
                    $realHighestRow = $rowCheck;
                    break;
                }
            }
            $highestRow = $realHighestRow;

            if ($highestRow < 2) {
                throw new Exception("Setelah dibersihkan, Excel tidak memiliki baris data yang valid.");
            }
            // =======================================================

            // 1. Ekstrak informasi sel yang di-merge
            $mergedCells = $sheet->getMergeCells();
            $mergeMap = [];
            foreach ($mergedCells as $merge) {
                $pData = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::extractAllCellReferencesInRange($merge);
                $masterCell = $pData[0];

                list($startCol, $startRow) = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($masterCell);
                $endCell = end($pData);
                list($endCol, $endRow) = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($endCell);

                $startColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($startCol);
                $endColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($endCol);

                $mergeMap[$masterCell] = [
                    'colspan' => $endColIdx - $startColIdx + 1,
                    'rowspan' => $endRow - $startRow + 1,
                ];

                for ($i = 1; $i < count($pData); $i++) {
                    $mergeMap[$pData[$i]] = ['hidden' => true];
                }
            }

            // 2. Format Header (Baris 1)
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
                $cellValue = (string) $sheet->getCell($cellAddress)->getCalculatedValue();

                $cellValue = trim(preg_replace('/[\r\n]+/', ' ', $cellValue));

                // --- PEMBERSIHAN & STANDARDISASI HEADER ---
                $bpsDictionary = $this->getBpsDictionary();
                if (array_key_exists($cellValue, $bpsDictionary)) {
                    $cellValue = $bpsDictionary[$cellValue];
                }
                if ($cellValue === '') {
                    $cellValue = '-';
                }

                $headerData = ['value' => $cellValue, 'colspan' => 1, 'rowspan' => 1, 'hidden' => false];
                if (isset($mergeMap[$cellAddress])) {
                    if (isset($mergeMap[$cellAddress]['hidden'])) {
                        $headerData['hidden'] = true;
                    } else {
                        $headerData['colspan'] = $mergeMap[$cellAddress]['colspan'];
                        $headerData['rowspan'] = $mergeMap[$cellAddress]['rowspan'];
                    }
                }
                $headers[] = $headerData;
            }

            // 3. Format Data Rows (Baris 2 sampai akhir)
            $rows = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                    $cellValue = (string) $sheet->getCell($cellAddress)->getCalculatedValue();

                    $cellValue = trim(preg_replace('/[\r\n]+/', ' ', $cellValue));

                    $cellData = ['value' => $cellValue, 'colspan' => 1, 'rowspan' => 1, 'hidden' => false];
                    if (isset($mergeMap[$cellAddress])) {
                        if (isset($mergeMap[$cellAddress]['hidden'])) {
                            $cellData['hidden'] = true;
                        } else {
                            $cellData['colspan'] = $mergeMap[$cellAddress]['colspan'];
                            $cellData['rowspan'] = $mergeMap[$cellAddress]['rowspan'];
                        }
                    }

                    // --- PEMBERSIHAN, STANDARDISASI, & TRANSFORMASI ANGKA ---
                    if (isset($cellData['value'])) {
                        $val = trim((string) $cellData['value']);
                        
                        // 1. Standardisasi Istilah
                        $bpsDictionary = $this->getBpsDictionary();
                        if (array_key_exists($val, $bpsDictionary)) {
                            $val = $bpsDictionary[$val];
                        }

                        // 2. Penanganan Nilai Kosong
                        if ($val === '') {
                            $cellData['value'] = '-';
                        } else {
                            // 3. Transformasi Angka
                            if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $val)) {
                                $val = str_replace('.', '', $val);
                                $val = str_replace(',', '.', $val);
                            } elseif (preg_match('/^-?\d+(,\d+)?$/', $val)) {
                                $val = str_replace(',', '.', $val);
                            }
                            if (is_numeric($val)) {
                                $cellData['value'] = (string) (floatval($val) == $val ? $val : floatval($val));
                            } else {
                                $cellData['value'] = $val;
                            }
                        }
                    }

                    $rowData[] = $cellData;
                }
                $rows[] = $rowData;
            }

            $tableData = [
                'headers' => $headers,
                'rows'    => $rows
            ];

            Indicator::create([
                'subject_id' => $request->subject_id,
                'user_id'    => Auth::id(), // Audit trail: catat siapa yang mengimpor
                'name'       => $request->name,
                'unit'       => $request->unit,
                'data'       => $tableData,
            ]);

            return back()->with('success', 'Data berhasil diimpor!');
        } catch (Exception $e) {
            Log::error('Error Import Excel: ' . $e->getMessage());
            return back()->withErrors(['excel_file' => 'Gagal impor: ' . $e->getMessage()]);
        }
    }

    /**
     * Kamus BPS untuk Standardisasi Terminologi pada saat import Excel
     */
    private function getBpsDictionary()
    {
        return [
            // Pendidikan
            'SLTP' => 'SMP',
            'SLTA' => 'SMA',
            'SD/MI' => 'SD/Sederajat',
            'SMP/MTs' => 'SMP/Sederajat',
            'SMA/MA' => 'SMA/Sederajat',
            'Tdk Tamat SD' => 'Tidak Tamat SD',
            'Tdk/Belum Sekolah' => 'Tidak/Belum Sekolah',
            
            // Jenis Kelamin
            'Laki-Laki' => 'Laki-laki',
            'Perempuan' => 'Perempuan',
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            
            // Bulan (Kependekan umum)
            'Jan' => 'Januari',
            'Feb' => 'Februari',
            'Mar' => 'Maret',
            'Apr' => 'April',
            'Agt' => 'Agustus',
            'Agu' => 'Agustus',
            'Sep' => 'September',
            'Okt' => 'Oktober',
            'Nov' => 'November',
            'Des' => 'Desember',

            // Nilai Kosong / Missing Value Text (dikonversi ke dash)
            'n/a' => '-',
            'N/A' => '-',
            'NA' => '-',
            'Kosong' => '-',
            'null' => '-'
        ];
    }
}
