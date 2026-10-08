{{-- resources/views/databps/index.blade.php — dipakai Admin & Penanggung Jawab (layout mengikuti role) --}}
<x-dynamic-component :component="$area['layout']" title="Data API BPS - PRANATA">
    @php
        $rute = $area['rute'];
        $daftarTab = [
            'dinamis' => ['label' => 'Tabel Dinamis', 'url' => route($rute . 'databps')],
            'publikasi' => ['label' => 'Publikasi', 'url' => route($rute . 'databps', ['tab' => 'publikasi'])],
        ];
    @endphp

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1">Data API BPS</h1>
            <p class="text-sm md:text-base text-gray-500">
                Ambil tabel dinamis dan publikasi BPS Kota Pematangsiantar (domain {{ $domain }}) langsung dari WebAPI BPS.
            </p>
        </div>

        @include('databps.partials.notifikasi')

        {{-- Tabel dinamis otomatis dipakai dashboard: ringkasan & tombol cek tabel baru --}}
        <div class="bg-white rounded-xl shadow-lg p-4 md:p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="space-y-1">
                <h3 class="font-semibold text-gray-900">Dashboard otomatis memakai tabel dinamis BPS</h3>
                <p class="text-sm text-gray-600">
                    {{ number_format($otomatis['jumlah'], 0, ',', '.') }} indikator tertaut ke tabel dinamis WebAPI BPS. Setiap tabel dinamis
                    baru otomatis menjadi indikator, dan datanya (seluruh tahun) diambil dari API saat indikator dibuka di Dashboard,
                    Lihat Data, ekspor, atau saat narasi AI dibuat.
                </p>
                @if ($otomatis['terakhir'])
                    <p class="text-xs text-gray-500">Data terakhir diambil dari API: {{ \Illuminate\Support\Carbon::parse($otomatis['terakhir'])->format('d-m-Y H:i') }}</p>
                @endif
                @if ($otomatis['diabaikan'])
                    <details class="text-sm text-gray-600 pt-1">
                        <summary class="cursor-pointer text-[#002D72] font-medium">
                            {{ count($otomatis['diabaikan']) }} tabel disembunyikan karena indikatornya dihapus
                        </summary>
                        <ul class="mt-2 space-y-1">
                            @foreach ($otomatis['diabaikan'] as $t)
                                <li class="flex flex-wrap items-center gap-2">
                                    <span>{{ $t['judul'] ?: 'Tabel dinamis ' . $t['bps_table_id'] }}</span>
                                    <form method="POST" action="{{ route($rute . 'databps.diabaikan.pulihkan', $t['bps_table_id']) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-[#002D72] hover:underline">Tampilkan lagi</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
            <form method="POST" action="{{ route($rute . 'databps.katalog.perbarui') }}" x-data="{ memuat: false }" @submit="memuat = true" class="flex-shrink-0">
                @csrf
                <button type="submit" :disabled="memuat"
                    class="bg-[#002D72] text-white hover:bg-[#001f52] px-5 py-2.5 rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!memuat">Cek Tabel Baru Sekarang</span>
                    <span x-show="memuat" x-cloak>Memeriksa...</span>
                </button>
            </form>
        </div>

        <div class="flex gap-2 border-b border-gray-200 bg-white rounded-t-xl px-2 md:px-4 overflow-x-auto whitespace-nowrap">
            @foreach ($daftarTab as $kunci => $t)
                <a href="{{ $t['url'] }}"
                    class="px-4 md:px-6 py-4 font-medium text-sm transition-all duration-200 border-b-2 {{ $tab === $kunci ? 'text-[#002D72] font-semibold border-[#002D72]' : 'text-gray-500 hover:text-gray-700 border-transparent' }}">
                    {{ $t['label'] }}
                </a>
            @endforeach
        </div>

        @include('databps.partials.tab-' . $tab)
    </div>
</x-dynamic-component>
