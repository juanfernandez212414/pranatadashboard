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

        <div class="flex gap-2 border-b border-gray-200 bg-white rounded-t-xl px-2 md:px-4 overflow-x-auto whitespace-nowrap">
            @foreach ($daftarTab as $kunci => $t)
                <a href="{{ $t['url'] }}"
                    class="px-4 md:px-6 py-4 font-medium text-sm transition-all duration-200 border-b-2 {{ $tab === $kunci ? 'text-[#002D72] font-semibold border-[#002D72]' : 'text-gray-500 hover:text-gray-700 border-transparent' }}">
                    {{ $t['label'] }}
                </a>
            @endforeach
        </div>

        {{-- Tabel dinamis BPS untuk dashboard: ringkasan & tombol impor semua (hanya di tab Tabel Dinamis) --}}
        @if ($tab === 'dinamis')
        <div class="bg-white rounded-xl shadow-lg p-4 md:p-6 space-y-4"
            x-data="imporSemuaBps({{ Js::from(['mulai' => route($rute . 'databps.imporsemua'), 'satu' => route($rute . 'databps.imporsemua.satu', ['indicator' => 0])]) }})">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="space-y-1">
                    <h3 class="font-semibold text-gray-900">Tabel dinamis BPS untuk dashboard</h3>
                    <p class="text-sm text-gray-600">
                        {{ number_format($otomatis['jumlah'], 0, ',', '.') }} indikator tertaut ke tabel dinamis WebAPI BPS,
                        {{ number_format($otomatis['berisiData'], 0, ',', '.') }} di antaranya sudah berisi data.
                        "Impor Semua Tabel Dinamis" mengambil semua tabel dinamis beserta data seluruh tahunnya dan menyimpannya ke
                        database, sehingga Dashboard, Lihat Data, ekspor, dan narasi AI langsung memakainya tanpa menunggu API.
                    </p>
                    @if ($otomatis['terakhir'])
                        <p class="text-xs text-gray-500">Data terakhir diambil dari API: {{ \Illuminate\Support\Carbon::parse($otomatis['terakhir'])->timezone('Asia/Jakarta')->format('d-m-Y H:i') }} WIB</p>
                    @endif
                    @if ($otomatis['kandidat'])
                        <details class="text-sm text-gray-600 pt-1" open>
                            <summary class="cursor-pointer text-[#002D72] font-medium">
                                {{ count($otomatis['kandidat']) }} indikator lama bernama sama dengan tabel dinamis BPS
                            </summary>
                            <p class="mt-1 text-xs text-gray-500">
                                Indikator ini dibuat manual/impor Excel. Pilih "Pakai data API" agar datanya diganti data tabel dinamis BPS
                                (seluruh tahun). Nama, subjek, dan narasinya tetap.
                            </p>
                            <ul class="mt-2 space-y-1">
                                @foreach ($otomatis['kandidat'] as $k)
                                    <li class="flex flex-wrap items-center gap-2">
                                        <span>{{ $k['indikator']['name'] }}<span class="text-gray-400"> · {{ $k['indikator']['subjek'] }}</span></span>
                                        <form method="POST" action="{{ route($rute . 'databps.tautkan', $k['indikator']['id']) }}"
                                            onsubmit="return confirm('Data indikator ini akan diganti data dari WebAPI BPS. Lanjutkan?')">
                                            @csrf
                                            <button type="submit" class="text-xs text-[#002D72] hover:underline">Pakai data API</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
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
                <button type="button" @click="mulai()" :disabled="berjalan"
                    class="flex-shrink-0 bg-[#002D72] text-white hover:bg-[#001f52] px-5 py-2.5 rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!berjalan">Impor Semua Tabel Dinamis</span>
                    <span x-show="berjalan" x-cloak>Mengimpor...</span>
                </button>
            </div>

            <div x-show="berjalan || log.length > 0" x-cloak class="border-t border-gray-200 pt-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <p class="font-medium text-gray-900" x-text="status"></p>
                    <button type="button" x-show="berjalan" @click="berhenti = true" class="text-red-600 hover:underline">Hentikan</button>
                    <a x-show="!berjalan" href="" class="text-[#002D72] hover:underline">Muat ulang halaman</a>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                    <div class="bg-[#002D72] h-2 rounded-full" :style="`width: ${total ? Math.round(selesai / total * 100) : 0}%`"></div>
                </div>
                <ul class="text-xs space-y-1 overflow-y-auto" style="max-height: 12rem">
                    <template x-for="(l, n) in log" :key="n">
                        <li :class="l.galat ? 'text-red-700' : 'text-gray-600'" x-text="(l.galat ? '✗ ' : '✓ ') + l.teks"></li>
                    </template>
                </ul>
            </div>
        </div>
        @endif

        @include('databps.partials.tab-' . $tab)
    </div>
@push('scripts')
    <script>
        // Tombol "Impor Semua Tabel Dinamis": cek tabel baru, lalu ambil data tiap indikator satu per satu
        // (satu permintaan per indikator), agar halaman tidak terkena batas waktu walau tabelnya ratusan.
        window.imporSemuaBps = function (url) {
            const token = '{{ csrf_token() }}';
            const kirim = async (alamat) => {
                const res = await fetch(alamat, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) throw Object.assign(new Error(json.galat || json.message || `Permintaan gagal (HTTP ${res.status}).`), { berhenti: !!json.berhenti });
                return json;
            };

            return {
                berjalan: false, berhenti: false, selesai: 0, berhasil: 0, total: 0, status: '', log: [],

                async mulai() {
                    if (!confirm('Ambil semua tabel dinamis beserta data seluruh tahunnya dari WebAPI BPS? Proses ini bisa memakan beberapa menit; biarkan halaman ini tetap terbuka.')) return;
                    Object.assign(this, { berjalan: true, berhenti: false, selesai: 0, berhasil: 0, total: 0, log: [], status: 'Mengecek daftar tabel dinamis...' });
                    window.addEventListener('beforeunload', this.cegahTutup);
                    try {
                        const awal = await kirim(url.mulai);
                        const daftar = awal.indikator;
                        this.total = daftar.length;
                        for (const i of daftar) {
                            if (this.berhenti) { this.log.push({ galat: true, teks: 'Dihentikan.' }); break; }
                            this.status = `Mengambil data ${this.selesai + 1} dari ${this.total}: ${i.nama}`;
                            try {
                                this.log.unshift({ galat: false, teks: (await kirim(url.satu.replace(/\/0$/, `/${i.id}`))).pesan });
                                this.berhasil++;
                            } catch (e) {
                                this.log.unshift({ galat: true, teks: `${i.nama}: ${e.message}` });
                                if (e.berhenti) { this.log.unshift({ galat: true, teks: 'Dihentikan: WebAPI BPS menolak permintaan. Coba lagi nanti.' }); break; }
                            }
                            this.selesai++;
                        }
                    } catch (e) {
                        this.log.unshift({ galat: true, teks: e.message });
                    }
                    this.status = `Selesai: ${this.berhasil} dari ${this.total} indikator berhasil diimpor.`;
                    this.berjalan = false;
                    window.removeEventListener('beforeunload', this.cegahTutup);
                },

                cegahTutup(e) { e.preventDefault(); e.returnValue = ''; },
            };
        };
    </script>
@endpush
</x-dynamic-component>
