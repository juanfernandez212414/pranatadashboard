{{-- Pratinjau matriks indikator ({headers, rows}), dengan aturan tampilan yang sama seperti halaman
     Lihat Data: jumlah baris judul tambahan = rowspan terbesar di baris judul pertama. --}}
@php
    $tinggiJudul = max(1, ...array_map(fn ($s) => (int) ($s['rowspan'] ?? 1), $matriks['headers']));
    $barisJudul = [$matriks['headers'], ...array_slice($matriks['rows'], 0, $tinggiJudul - 1)];
    $barisIsi = array_slice($matriks['rows'], $tinggiJudul - 1);

    // Angka ditampilkan berformat Indonesia (1.234,5); kolom pertama (label baris) apa adanya.
    $tampilan = function (string $nilai, bool $kolomPertama): array {
        $nilai = trim($nilai);
        if ($kolomPertama) {
            return [$nilai, 'text-left font-bold text-gray-900'];
        }
        if ($nilai === '' || $nilai === '-') {
            return [$nilai, 'text-center text-gray-400'];
        }
        if (is_numeric($nilai)) {
            $desimal = str_contains($nilai, '.') ? strlen(substr(strrchr($nilai, '.'), 1)) : 0;
            return [number_format((float) $nilai, $desimal, ',', '.'), 'text-right font-semibold text-[#002D72]'];
        }
        return [$nilai, 'text-center text-gray-600'];
    };
@endphp

<div class="overflow-x-auto">
    <table class="w-full text-sm whitespace-nowrap border-collapse">
        <thead class="bg-gray-100">
            @foreach ($barisJudul as $baris)
                <tr>
                    @foreach ($baris as $sel)
                        @continue($sel['hidden'] ?? false)
                        <th class="px-4 py-3 font-bold text-gray-900 border border-gray-200 {{ $loop->first ? 'text-left' : 'text-center' }}"
                            @if (($sel['colspan'] ?? 1) > 1) colspan="{{ $sel['colspan'] }}" @endif
                            @if (($sel['rowspan'] ?? 1) > 1) rowspan="{{ $sel['rowspan'] }}" @endif>
                            {{ $sel['value'] ?? '' }}
                        </th>
                    @endforeach
                </tr>
            @endforeach
        </thead>
        <tbody>
            @foreach ($barisIsi as $baris)
                <tr class="hover:bg-blue-50/50 transition-colors">
                    @foreach ($baris as $sel)
                        @continue($sel['hidden'] ?? false)
                        @php [$teks, $kelas] = $tampilan((string) ($sel['value'] ?? ''), $loop->first); @endphp
                        <td class="px-4 py-2.5 border border-gray-200 {{ $kelas }}"
                            @if (($sel['colspan'] ?? 1) > 1) colspan="{{ $sel['colspan'] }}" @endif
                            @if (($sel['rowspan'] ?? 1) > 1) rowspan="{{ $sel['rowspan'] }}" @endif>
                            {{ $teks }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
