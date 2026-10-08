<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export PDF - {{ $indicator->name }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 16px;
            border-bottom: 2px solid #002D72;
            padding-bottom: 8px;
        }

        .header h2 {
            margin: 0;
            color: #002D72;
            font-size: 16px;
        }

        .info-table {
            width: 100%;
            margin-bottom: 14px;
        }

        .info-table td {
            padding: 3px;
            vertical-align: top;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            table-layout: fixed;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
            padding: 4px 3px;
            text-align: center;
            /* Default center */
            font-size: 8px;
            word-break: break-word;
            vertical-align: middle;
        }

        .data-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #002D72;
            text-transform: uppercase;
        }

        .data-table tr:nth-child(even) {
            background-color: #fafafa;
        }

        /* Utility classes khusus PDF dengan !important agar menang melawan default td */
        .text-left {
            text-align: left !important;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }
    </style>
</head>

<body>

    <div class="header">
        <h2>Data Detail Indikator</h2>
    </div>

    <table class="info-table">
        <tr>
            <td width="20%"><strong>Nama Indikator</strong></td>
            <td width="2%">:</td>
            <td>{{ $indicator->name }}</td>
        </tr>
        <tr>
            <td><strong>Kategori Utama</strong></td>
            <td>:</td>
            <td>{{ $indicator->subject->category->name }}</td>
        </tr>
        <tr>
            <td><strong>Subjek Data</strong></td>
            <td>:</td>
            <td>{{ $indicator->subject->name }}</td>
        </tr>
        <tr>
            <td><strong>Satuan</strong></td>
            <td>:</td>
            <td>{{ $indicator->unit ?? '-' }}</td>
        </tr>
    </table>

    @php
        $matrix = null;
        $raw = $indicator->data ?? ($indicator->matrix_data ?? null);
        if (!empty($raw)) {
            $matrix = is_string($raw) ? json_decode($raw, true) : $raw;
        }

        $headerRows = [];
        $bodyRows = [];
        $extraHeaderCount = 0;

        if ($matrix && isset($matrix['headers']) && isset($matrix['rows'])) {
            $maxRowspan = 1;
            foreach ($matrix['headers'] as $h) {
                $rs = $h['rowspan'] ?? 1;
                if ($rs > $maxRowspan) {
                    $maxRowspan = $rs;
                }
            }
            $extraHeaderCount = max(0, $maxRowspan - 1);
            $headerRows = array_slice($matrix['rows'], 0, $extraHeaderCount);
            $bodyRows = array_slice($matrix['rows'], $extraHeaderCount);
        }
    @endphp

    @if ($matrix && isset($matrix['headers']) && isset($matrix['rows']))
        <table class="data-table">
            <thead>
                {{-- Header utama --}}
                <tr>
                    @foreach ($matrix['headers'] as $header)
                        @if (!isset($header['hidden']) || !$header['hidden'])
                            <th @if (isset($header['colspan']) && $header['colspan'] > 1) colspan="{{ $header['colspan'] }}" @endif
                                @if (isset($header['rowspan']) && $header['rowspan'] > 1) rowspan="{{ $header['rowspan'] }}" @endif>
                                {{ $header['value'] ?? '' }}
                            </th>
                        @endif
                    @endforeach
                </tr>

                {{-- Header tambahan (baris 2/3) --}}
                @foreach ($headerRows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            @if (!isset($cell['hidden']) || !$cell['hidden'])
                                <th @if (isset($cell['colspan']) && $cell['colspan'] > 1) colspan="{{ $cell['colspan'] }}" @endif
                                    @if (isset($cell['rowspan']) && $cell['rowspan'] > 1) rowspan="{{ $cell['rowspan'] }}" @endif>
                                    {{ $cell['value'] ?? '' }}
                                </th>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </thead>
            <tbody>
                @foreach ($bodyRows as $row)
                    <tr>
                        @php $colIndex = 0; @endphp
                        @foreach ($row as $cell)
                            @if (!isset($cell['hidden']) || !$cell['hidden'])
                                @php
                                    // Ambil nilai dan pastikan bersih dari spasi khusus (non-breaking space)
                                    $rawVal = $cell['value'] ?? '';
                                    $val = trim(str_replace(['&nbsp;', "\xc2\xa0"], ' ', (string) $rawVal));
                                    $displayVal = $val;
                                    $alignClass = 'text-center'; // Default style

                                    if ($colIndex === 0) {
                                        // SELALU jadikan kolom paling kiri Rata Kiri
                                        $alignClass = 'text-left';
                                    } else {
                                        // Jika bukan kolom pertama, periksa apakah angka
                                        $checkVal = str_replace(',', '.', $val);

                                        if ($val === '' || $val === '-') {
                                            $alignClass = 'text-center';
                                        } elseif (is_numeric($checkVal)) {
                                            $alignClass = 'text-right';
                                            $decimals = 0;
                                            if (strpos($checkVal, '.') !== false) {
                                                $parts = explode('.', $checkVal);
                                                $decimals = strlen(end($parts));
                                            }
                                            $displayVal = number_format((float) $checkVal, $decimals, ',', '.');
                                        }
                                    }
                                @endphp
                                <td class="{{ $alignClass }}"
                                    @if (isset($cell['colspan']) && $cell['colspan'] > 1) colspan="{{ $cell['colspan'] }}" @endif
                                    @if (isset($cell['rowspan']) && $cell['rowspan'] > 1) rowspan="{{ $cell['rowspan'] }}" @endif>
                                    {{ $displayVal }}
                                </td>
                                @php $colIndex++; @endphp
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-left">Karakteristik / Tahun</th>
                    <th class="text-center">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($indicator->data as $key => $value)
                    @if (!is_array($value))
                        @php
                            $val = trim(str_replace(['&nbsp;', "\xc2\xa0"], ' ', (string) $value));
                            $displayVal = $val;
                            $alignClass = 'text-center';

                            $checkVal = str_replace(',', '.', $val);

                            if ($val === '' || $val === '-') {
                                $alignClass = 'text-center';
                            } elseif (is_numeric($checkVal)) {
                                $alignClass = 'text-right';
                                $decimals = 0;
                                if (strpos($checkVal, '.') !== false) {
                                    $parts = explode('.', $checkVal);
                                    $decimals = strlen(end($parts));
                                }
                                $displayVal = number_format((float) $checkVal, $decimals, ',', '.');
                            }
                        @endphp
                        <tr>
                            <td class="text-left">{{ $key }}</td>
                            <td class="{{ $alignClass }}">{{ $displayVal }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

</body>

</html>
