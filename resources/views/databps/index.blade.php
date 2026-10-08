{{-- resources/views/databps/index.blade.php — dipakai Admin & Penanggung Jawab (layout mengikuti role) --}}
<x-dynamic-component :component="$area['layout']" title="Data API BPS - PRANATA">
    @php
        $rute = $area['rute'];
        $daftarTab = [
            'dinamis' => ['label' => 'Tabel Dinamis', 'url' => route($rute . 'databps')],
            'sinkron' => ['label' => 'Sinkronisasi Semua Tabel', 'url' => route($rute . 'databps', ['tab' => 'sinkron'])],
            'publikasi' => ['label' => 'Publikasi', 'url' => route($rute . 'databps', ['tab' => 'publikasi'])],
        ];
    @endphp

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1">Data API BPS</h1>
            <p class="text-sm md:text-base text-gray-500">
                Ambil tabel (tabel dinamis, tabel publikasi SIMDASI, tabel statis) dan publikasi BPS Kota Pematangsiantar
                (domain {{ $domain }}) langsung dari WebAPI BPS.
            </p>
        </div>

        @include('databps.partials.notifikasi')

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
