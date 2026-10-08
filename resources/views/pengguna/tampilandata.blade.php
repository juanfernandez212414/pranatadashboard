{{-- resources/views/pengguna/tampilandata.blade.php --}}

<x-penggunalayout title="Detail Data Indikator - PRANATA">

    <div class="space-y-6">

        <div class="flex flex-wrap items-center justify-between w-full gap-4">

            <div class="flex items-center gap-2">
                {{-- Tombol Download Excel --}}
                <a href="{{ route('pengguna.indicators.export.excel', $indicator->id) }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold transition-all shadow-sm text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                    </svg>
                    Excel
                </a>

                {{-- Tombol Download PDF --}}
                <a href="{{ route('pengguna.indicators.export.pdf', $indicator->id) }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-bold transition-all shadow-sm text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                    </svg>
                    PDF
                </a>
            </div>

            <div class="ml-auto">
                <a href="{{ route('pengguna.lihatdata') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-bold transition-all shadow-sm text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Kembali
                </a>
            </div>

        </div>

        {{-- Kartu Informasi Indikator --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                <div>
                    <span class="block text-gray-500 uppercase tracking-wider font-bold text-[10px] mb-1">Kategori
                        Utama</span>
                    <span
                        class="inline-flex items-center bg-blue-50 text-[#002D72] px-3 py-1.5 rounded-md font-bold border border-blue-100 text-xs">
                        {{ $indicator->subject->category->name }}
                    </span>
                </div>
                <div>
                    <span class="block text-gray-500 uppercase tracking-wider font-bold text-[10px] mb-1">Subjek
                        Data</span>
                    <span class="font-bold text-gray-800 text-sm">{{ $indicator->subject->name }}</span>
                </div>
                <div>
                    <span class="block text-gray-500 uppercase tracking-wider font-bold text-[10px] mb-1">Satuan</span>
                    <span class="font-bold text-gray-800 text-sm">{{ $indicator->unit ?? 'N/A' }}</span>
                </div>
            </div>
            @if ($indicator->bps_source)
                <p class="mt-4 text-xs text-gray-500">
                    Sumber: Tabel Dinamis WebAPI BPS{{ $indicator->bps_synced_at ? ' · data diambil ' . $indicator->bps_synced_at->timezone('Asia/Jakarta')->format('d-m-Y H:i') . ' WIB' : '' }}
                </p>
            @endif
        </div>

        @if ($galatApiBps ?? null)
            <div class="bg-orange-50 border border-orange-200 text-orange-800 px-4 py-3 rounded-lg text-sm" role="alert">
                Data terbaru dari WebAPI BPS gagal diambil{{ empty($indicator->data) ? '' : ', jadi yang tampil adalah data terakhir yang tersimpan' }}.
            </div>
        @endif

        {{-- Tabel Visualisasi Data --}}
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="p-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <h3 class="font-bold text-[#002D72] flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Tabel Rincian Data
                </h3>
            </div>

            <div class="overflow-x-auto">
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
                    <table class="w-full text-center text-sm whitespace-nowrap border-collapse">
                        <thead class="bg-gray-100 border-b border-gray-200">
                            {{-- HEADER UTAMA --}}
                            <tr>
                                @foreach ($matrix['headers'] as $header)
                                    @if (!isset($header['hidden']) || !$header['hidden'])
                                        <th class="p-4 text-sm font-bold text-gray-900 border-b border-gray-200 border-r last:border-r-0 {{ $loop->index === 0 ? 'text-left' : 'text-center' }}"
                                            @if (isset($header['colspan']) && $header['colspan'] > 1) colspan="{{ $header['colspan'] }}" @endif
                                            @if (isset($header['rowspan']) && $header['rowspan'] > 1) rowspan="{{ $header['rowspan'] }}" @endif>
                                            {{ $header['value'] ?? '' }}
                                        </th>
                                    @endif
                                @endforeach
                            </tr>

                            {{-- HEADER TAMBAHAN (BARIS 2 / 3 dst dari rows) --}}
                            @foreach ($headerRows as $row)
                                <tr>
                                    @foreach ($row as $cell)
                                        @if (!isset($cell['hidden']) || !$cell['hidden'])
                                            <th class="p-4 text-sm font-bold text-gray-900 border-b border-gray-200 border-r last:border-r-0 {{ $loop->index === 0 ? 'text-left' : 'text-center' }}"
                                                @if (isset($cell['colspan']) && $cell['colspan'] > 1) colspan="{{ $cell['colspan'] }}" @endif
                                                @if (isset($cell['rowspan']) && $cell['rowspan'] > 1) rowspan="{{ $cell['rowspan'] }}" @endif>
                                                {{ $cell['value'] ?? '' }}
                                            </th>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </thead>

                        <tbody class="divide-y divide-gray-100">
                            @foreach ($bodyRows as $row)
                                <tr class="hover:bg-blue-50/50 transition-colors">
                                    @foreach ($row as $cell)
                                        @if (!isset($cell['hidden']) || !$cell['hidden'])
                                            @php
                                                // Ambil nilai dan bersihkan spasi kosong di awal/akhir
                                                $rawVal = $cell['value'] ?? '';
                                                $val = trim((string) $rawVal);
                                                $displayVal = $val;
                                                $isFirstCol = $loop->first;

                                                if ($isFirstCol) {
                                                    // Kolom 1 (Kecamatan): Rata kiri murni
                                                    $alignClass = 'text-left font-bold text-gray-900';
                                                } else {
                                                    // Kolom Data: Cek apakah isinya murni angka
                                                    // Gunakan str_replace jika ada koma bawaan excel yang salah baca
                                                    $checkVal = str_replace(',', '.', $val);

                                                    if ($val === '' || $val === '-') {
                                                        // Jika kosong atau strip, rata tengah
                                                        $alignClass = 'text-center text-gray-500';
                                                    } elseif (is_numeric($checkVal)) {
                                                        // Jika valid sebagai angka: Rata kanan
                                                        $alignClass = 'text-right font-semibold text-[#002D72]';

                                                        // Hitung berapa digit di belakang titik
                                                        $decimals = 0;
                                                        if (strpos($checkVal, '.') !== false) {
                                                            $parts = explode('.', $checkVal);
                                                            $decimals = strlen(end($parts));
                                                        }

                                                        // Eksekusi: ubah desimal jadi koma, ribuan jadi titik
                                                        $displayVal = number_format(
                                                            (float) $checkVal,
                                                            $decimals,
                                                            ',',
                                                            '.',
                                                        );
                                                    } else {
                                                        // Teks tak terduga lainnya rata tengah
                                                        $alignClass = 'text-center text-gray-600';
                                                    }
                                                }
                                            @endphp
                                            <td class="p-4 text-sm border-b border-gray-200 border-r last:border-r-0 {{ $alignClass }}"
                                                @if (isset($cell['colspan']) && $cell['colspan'] > 1) colspan="{{ $cell['colspan'] }}" @endif
                                                @if (isset($cell['rowspan']) && $cell['rowspan'] > 1) rowspan="{{ $cell['rowspan'] }}" @endif>
                                                {{ $displayVal }}
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif (empty($indicator->data))
                    <p class="p-8 text-center text-sm text-gray-500">
                        Data indikator ini belum tersedia.{{ $indicator->bps_source ? ' Datanya sedang disiapkan dari WebAPI BPS; silakan coba lagi nanti.' : '' }}
                    </p>
                @else
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-100">
                            <tr>
                                <th
                                    class="p-4 text-xs font-bold text-gray-700 border-b border-gray-200 uppercase tracking-wider">
                                    Karakteristik / Tahun</th>
                                <th
                                    class="p-4 text-xs font-bold text-gray-700 border-b border-gray-200 uppercase tracking-wider text-center">
                                    Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($indicator->data ?? [] as $key => $value)
                                @if (!is_array($value))
                                    @php
                                        // Bersihkan spasi gaib
                                        $val = trim((string) $value);
                                        $displayVal = $val;
                                        $alignClass = 'text-center text-gray-600 font-bold';

                                        // Gunakan str_replace untuk cek angka
                                        $checkVal = str_replace(',', '.', $val);

                                        if ($val === '' || $val === '-') {
                                            $alignClass = 'text-center text-gray-500 font-normal';
                                        } elseif (is_numeric($checkVal)) {
                                            $alignClass = 'text-right text-[#002D72] font-bold';

                                            // Hitung desimal
                                            $decimals = 0;
                                            if (strpos($checkVal, '.') !== false) {
                                                $parts = explode('.', $checkVal);
                                                $decimals = strlen(end($parts));
                                            }

                                            // Format angka
                                            $displayVal = number_format((float) $checkVal, $decimals, ',', '.');
                                        }
                                    @endphp
                                    <tr class="hover:bg-blue-50/50 transition-colors">
                                        <td class="p-4 text-sm font-medium text-gray-800 text-left">{{ $key }}
                                        </td>
                                        <td class="p-4 text-sm {{ $alignClass }}">
                                            {{ $displayVal }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

</x-penggunalayout>
