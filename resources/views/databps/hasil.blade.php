{{-- resources/views/databps/hasil.blade.php — hasil "Data Terpilih" dari tab Tabel Dinamis (maksimal 2 data) --}}
<x-dynamic-component :component="$area['layout']" title="Hasil Tabel Dinamis - PRANATA">
    @php $rute = $area['rute']; @endphp

    <div class="space-y-6">
        <div>
            <a href="{{ route($rute . 'databps') }}" class="text-sm text-[#002D72] font-medium hover:underline">&larr; Kembali ke Tabel Dinamis</a>
            <h1 class="text-xl md:text-2xl font-bold text-gray-900 mt-2 mb-1">Hasil Tabel Dinamis</h1>
            <p class="text-sm text-gray-500">
                {{ count($hasil) }} data terpilih dari WebAPI BPS. Periksa tabelnya, lalu simpan sebagai indikator bila sesuai.
            </p>
        </div>

        @include('databps.partials.notifikasi', ['galat' => null])

        @foreach ($hasil as $nomor => $h)
            @php
                $tabel = $h['tabel'];
                $adaData = $tabel && !empty($tabel['matriks']['rows']);
                $tahun = $tabel ? collect($tabel['tahunDipilih'])->sort()->values()->all() : [];
            @endphp
            <section class="space-y-4">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="p-4 md:p-6 border-b border-gray-200 space-y-2">
                        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-[#002D72] text-white">Data {{ $nomor + 1 }}</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Tabel Dinamis</span>
                            @if (!empty($tabel['subjek']))
                                <span>Subjek: {{ $tabel['subjek'] }}</span>
                            @endif
                            @if (!empty($tabel['satuan']))
                                <span>· Satuan: {{ $tabel['satuan'] }}</span>
                            @endif
                            @if (!empty($tabel['diperbarui']))
                                <span>· Diperbarui BPS: {{ substr($tabel['diperbarui'], 0, 10) }}</span>
                            @endif
                        </div>
                        <h2 class="text-lg md:text-xl font-bold text-gray-900">{{ $tabel['judul'] ?? 'Tabel dinamis' }}</h2>
                        @if ($tahun)
                            <p class="text-sm text-gray-500">Tahun: {{ implode(', ', $tahun) }}</p>
                        @endif
                    </div>
                    <div class="p-4 md:p-6">
                        @if ($h['galat'])
                            <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-lg" role="alert">
                                <span class="font-bold">Gagal mengambil data dari WebAPI BPS.</span>
                                <span class="block text-sm mt-1">{{ $h['galat'] }}</span>
                            </div>
                        @elseif ($adaData)
                            @include('databps.partials.matriks', ['matriks' => $tabel['matriks']])
                        @else
                            <p class="text-center text-gray-500 py-8">
                                Tidak ada data untuk pilihan ini. Kembali ke Tabel Dinamis dan pilih tahun atau isian lain.
                            </p>
                        @endif
                        @if (!empty($tabel['catatan']))
                            <p class="mt-4 text-xs text-gray-500"><span class="font-semibold">Catatan BPS:</span> {{ $tabel['catatan'] }}</p>
                        @endif
                    </div>
                </div>

                @if ($adaData)
                    @include('databps.partials.form-simpan', [
                        'var' => $h['var'],
                        'tabel' => $tabel,
                        'saring' => $h['saring'],
                    ])
                @endif
            </section>
        @endforeach
    </div>
</x-dynamic-component>
