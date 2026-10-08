<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IndicatorExport implements FromArray, ShouldAutoSize, WithEvents, WithStyles
{
    protected $indicator;
    protected $sheetData = [];
    protected $mergeRanges = [];
    protected $headerRowCount = 1;

    public function __construct($indicator)
    {
        $this->indicator = $indicator;
        $this->buildMatrix();
    }

    protected function buildMatrix()
    {
        $raw = $this->indicator->data ?? $this->indicator->matrix_data ?? null;
        $matrix = !empty($raw) ? (is_string($raw) ? json_decode($raw, true) : $raw) : null;

        if (!$matrix || empty($matrix['headers']) || empty($matrix['rows'])) {
            $this->sheetData = [];
            if (!empty($this->indicator->data)) {
                $this->sheetData[] = ['Karakteristik / Tahun', 'Nilai'];
                foreach ($this->indicator->data as $key => $value) {
                    if (!is_array($value)) {
                        $this->sheetData[] = [$key, $value];
                    }
                }
            }
            // Tanpa data (mis. indikator tabel dinamis BPS yang belum berhasil diambil dari API) berkas
            // tetap dibuat berisi keterangan; lembar kosong membuat penentuan kolom terakhir gagal.
            if ($this->sheetData === []) {
                $this->sheetData[] = ['Data indikator ini belum tersedia.'];
            }
            return;
        }

        $headers = $matrix['headers'];
        $rows = $matrix['rows'];

        $maxRowspan = 1;
        foreach ($headers as $h) {
            $rs = $h['rowspan'] ?? 1;
            if ($rs > $maxRowspan) $maxRowspan = $rs;
        }

        $extraHeaderCount = max(0, $maxRowspan - 1);
        $this->headerRowCount = 1 + $extraHeaderCount;

        $headerRows = array_slice($rows, 0, $extraHeaderCount);
        $bodyRows = array_slice($rows, $extraHeaderCount);

        $this->sheetData[] = $this->valuesWithHidden($headers);

        foreach ($headerRows as $row) {
            $this->sheetData[] = $this->valuesWithHidden($row);
        }

        foreach ($bodyRows as $row) {
            $this->sheetData[] = $this->valuesWithHidden($row);
        }

        $this->collectMerges($headers, 1);

        for ($i = 0; $i < count($headerRows); $i++) {
            $this->collectMerges($headerRows[$i], 2 + $i);
        }

        $bodyStartRow = $this->headerRowCount + 1;
        for ($i = 0; $i < count($bodyRows); $i++) {
            $this->collectMerges($bodyRows[$i], $bodyStartRow + $i);
        }
    }

    protected function valuesWithHidden(array $row): array
    {
        return array_map(function ($cell) {
            if (isset($cell['hidden']) && $cell['hidden']) {
                return '';
            }

            $val = $cell['value'] ?? '';
            if (is_string($val)) {
                $val = trim(preg_replace('/[\r\n]+/', ' ', $val));
            }
            return $val;
        }, $row);
    }

    protected function collectMerges(array $row, int $rowIndex)
    {
        foreach ($row as $colIndex => $cell) {
            if (isset($cell['hidden']) && $cell['hidden']) {
                continue;
            }

            $colspan = $cell['colspan'] ?? 1;
            $rowspan = $cell['rowspan'] ?? 1;

            if ($colspan > 1 || $rowspan > 1) {
                $startCol = $colIndex + 1;
                $endCol = $startCol + $colspan - 1;
                $endRow = $rowIndex + $rowspan - 1;

                $start = Coordinate::stringFromColumnIndex($startCol) . $rowIndex;
                $end = Coordinate::stringFromColumnIndex($endCol) . $endRow;

                $this->mergeRanges[] = "{$start}:{$end}";
            }
        }
    }

    public function array(): array
    {
        return $this->sheetData;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $lastColumn = Coordinate::stringFromColumnIndex(count($this->sheetData[0] ?? []));
                $lastRow = count($this->sheetData);

                if ($lastRow > 0) {
                    $sheet->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ],
                        ],
                    ]);

                    // 🎯 PERBAIKAN: Format teks hanya sebatas data asli, JANGAN Z1000!
                    $sheet->getStyle('A1:' . $lastColumn . $lastRow)->getAlignment()->setWrapText(true);
                    $sheet->getStyle('A1:' . $lastColumn . $lastRow)->getAlignment()->setVertical('center');
                }

                foreach ($this->mergeRanges as $range) {
                    $event->sheet->mergeCells($range);
                }
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle("A1:Z{$this->headerRowCount}")->getFont()->setBold(true);
        $sheet->getStyle("A1:Z{$this->headerRowCount}")->getAlignment()->setHorizontal('center');
        return [];
    }
}
