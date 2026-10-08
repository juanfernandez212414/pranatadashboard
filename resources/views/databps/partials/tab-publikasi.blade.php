{{-- Tab Publikasi: daftar publikasi BPS dari WebAPI, bisa disimpan ke folder basis pengetahuan. --}}
@php
    $meta = $publikasi['meta'];
    $halaman = (int) ($meta['page'] ?? 1);
    $jumlahHalaman = (int) ($meta['pages'] ?? 0);
    $urlHalaman = fn ($h) => route($rute . 'databps', array_filter(['tab' => 'publikasi', 'q' => $kataKunci, 'page' => $h]));
@endphp

<div class="bg-white rounded-xl shadow-lg">
    <div class="p-4 md:p-6 border-b border-gray-200 space-y-4">
        <div>
            <h3 class="text-lg md:text-xl font-semibold text-gray-900">Publikasi BPS</h3>
            <p class="text-sm text-gray-500">
                "Simpan ke Basis Pengetahuan" mengunduh PDF ke folder dokumen pengetahuan AI. Setelah itu buka
                <a href="{{ route($rute . 'pengetahuan') }}" class="text-[#002D72] font-medium underline">Manajemen Pengetahuan</a>
                lalu klik Ingest agar isinya dapat dipakai AI.
            </p>
        </div>
        <form method="GET" action="{{ route($rute . 'databps') }}" class="flex flex-col sm:flex-row gap-2">
            <input type="hidden" name="tab" value="publikasi">
            <input type="text" name="q" value="{{ $kataKunci }}"
                placeholder="Cari publikasi, misalnya: dalam angka, inflasi, kemiskinan..."
                class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none">
            <button type="submit"
                class="bg-[#002D72] text-white hover:bg-[#001f52] px-5 py-2.5 rounded-lg text-sm font-medium">Cari</button>
            @if ($kataKunci !== '')
                <a href="{{ route($rute . 'databps', ['tab' => 'publikasi']) }}"
                    class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 text-center hover:bg-gray-50">Reset</a>
            @endif
        </form>
        @if (!$galat)
            <p class="text-sm text-gray-500">{{ number_format($meta['total'] ?? 0, 0, ',', '.') }} publikasi ditemukan.</p>
        @endif
    </div>

    <div class="p-4 md:p-6 bg-slate-50/50 space-y-4">
        @forelse ($publikasi['item'] as $pub)
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 flex flex-col sm:flex-row gap-4">
                @if (!empty($pub['cover']))
                    <img src="{{ $pub['cover'] }}" alt="Sampul {{ $pub['title'] }}" loading="lazy"
                        referrerpolicy="no-referrer"
                        class="w-20 h-28 object-cover rounded border border-gray-200 flex-shrink-0 bg-gray-100">
                @endif
                <div class="flex-1 min-w-0 space-y-2">
                    <h4 class="font-semibold text-gray-900">{{ $pub['title'] }}</h4>
                    <p class="text-xs text-gray-500">
                        Rilis {{ $pub['rl_date'] ?? '–' }}
                        @if (!empty($pub['size'])) · {{ $pub['size'] }} @endif
                        @if (!empty($pub['issn']) && $pub['issn'] !== '-') · ISSN/ISBN {{ $pub['issn'] }} @endif
                    </p>
                    @if (!empty($pub['abstract']))
                        <p class="text-sm text-gray-600">{{ Str::limit(\App\Services\Bps\KonverterTabelBps::bersihkanTeks($pub['abstract'], buangTerjemahan: false), 280) }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        @if (!empty($pub['pdf']))
                            <a href="{{ $pub['pdf'] }}" target="_blank" rel="noopener noreferrer"
                                class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50">
                                Buka PDF
                            </a>
                        @endif
                        @if ($pub['tersimpan'])
                            <span class="px-3 py-1.5 rounded-lg text-xs font-medium bg-green-100 text-green-800"
                                title="{{ $pub['tersimpan'] }}">Sudah ada di basis pengetahuan</span>
                        @elseif (!empty($pub['pdf']))
                            <form method="POST" action="{{ route($rute . 'databps.publikasi.simpan', $pub['pub_id']) }}"
                                x-data="{ menyimpan: false }" @submit="menyimpan = true">
                                @csrf
                                <button type="submit" :disabled="menyimpan"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-[#002D72] text-white hover:bg-[#001f52] disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span x-show="!menyimpan">Simpan ke Basis Pengetahuan</span>
                                    <span x-show="menyimpan" x-cloak>Mengunduh PDF...</span>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="text-center text-gray-500 py-8">
                {{ $galat ? 'Daftar publikasi belum dapat dimuat.' : 'Tidak ada publikasi yang cocok.' }}
            </p>
        @endforelse

        @if ($jumlahHalaman > 1)
            <div class="flex items-center justify-between text-sm pt-2">
                @if ($halaman > 1)
                    <a href="{{ $urlHalaman($halaman - 1) }}"
                        class="px-4 py-2 border border-gray-300 rounded-lg bg-white hover:bg-gray-50 font-medium text-gray-700">&larr; Sebelumnya</a>
                @else
                    <span></span>
                @endif
                <span class="text-gray-500">Halaman {{ $halaman }} dari {{ $jumlahHalaman }}</span>
                @if ($halaman < $jumlahHalaman)
                    <a href="{{ $urlHalaman($halaman + 1) }}"
                        class="px-4 py-2 border border-gray-300 rounded-lg bg-white hover:bg-gray-50 font-medium text-gray-700">Berikutnya &rarr;</a>
                @else
                    <span></span>
                @endif
            </div>
        @endif
    </div>
</div>
