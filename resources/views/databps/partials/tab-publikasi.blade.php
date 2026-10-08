{{-- Tab Publikasi: publikasi BPS dari WebAPI. PDF diunduh langsung dari API ke basis pengetahuan dan
     dilatihkan (ingest) ke layanan AI, per publikasi atau semua publikasi baru sekaligus. --}}
@php
    $meta = $publikasi['meta'];
    $halaman = (int) ($meta['page'] ?? 1);
    $jumlahHalaman = (int) ($meta['pages'] ?? 0);
    $urlHalaman = fn ($h) => route($rute . 'databps', array_filter(['tab' => 'publikasi', 'q' => $kataKunci, 'page' => $h]));
@endphp

{{-- Ambil & latih semua publikasi baru: browser memproses satu publikasi per permintaan. --}}
<div class="bg-white rounded-xl shadow-lg p-4 md:p-6 space-y-4 mb-6"
    x-data="publikasiOtomatisBps({{ Js::from(['mulai' => route($rute . 'databps.publikasi.otomatis'), 'satu' => route($rute . 'databps.publikasi.otomatis.satu', ['id' => '__ID__']), 'sejak' => (string) $publikasiOtomatis['sejak'], 'kata' => $publikasiOtomatis['kata']]) }})">
    <div>
        <h3 class="text-lg md:text-xl font-semibold text-gray-900">Latih AI dengan publikasi BPS secara otomatis</h3>
        <p class="text-sm text-gray-500">
            Link PDF publikasi dari WebAPI BPS dikirim ke layanan AI, lalu server AI sendiri yang mengunduh dan mengekstraknya
            ke basis pengetahuan (dipakai narasi AI): tanpa menyimpan PDF di server ini, tanpa unggah manual, dan tanpa klik
            Ingest. Bila server AI tidak bisa mengunduh dari BPS, PDF diunduh di sini lalu dikirim. Publikasi yang sudah dilatih dilewati.
            Penjadwal Laravel juga menjalankannya setiap malam pukul 03.00 WIB.
        </p>
        @unless ($publikasiOtomatis['aiSiap'])
            <p class="text-sm text-orange-700 mt-1">Alamat layanan AI (HUGGINGFACE_API_URL) belum diisi di .env, jadi PDF hanya bisa diunduh.</p>
        @endunless
    </div>
    <div class="flex flex-col sm:flex-row sm:items-end gap-3">
        <div>
            <label for="publikasi-sejak" class="block text-sm font-medium text-gray-700 mb-1">Rilis sejak tahun</label>
            <select id="publikasi-sejak" x-model="sejak" :disabled="berjalan"
                class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none">
                @foreach (range(now()->year, 2010) as $th)
                    <option value="{{ $th }}">{{ $th }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1">
            <label for="publikasi-kata" class="block text-sm font-medium text-gray-700 mb-1">Kata kunci judul (opsional, pisahkan koma)</label>
            <input id="publikasi-kata" type="text" x-model="kata" :disabled="berjalan" placeholder="mis. dalam angka, statistik daerah"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none">
        </div>
        <button type="button" @click="mulai()" :disabled="berjalan"
            class="bg-[#002D72] text-white hover:bg-[#001f52] px-5 py-2.5 rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
            <span x-show="!berjalan">Ambil &amp; Latih Publikasi Baru</span>
            <span x-show="berjalan" x-cloak>Memproses...</span>
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

<div class="bg-white rounded-xl shadow-lg">
    <div class="p-4 md:p-6 border-b border-gray-200 space-y-4">
        <div>
            <h3 class="text-lg md:text-xl font-semibold text-gray-900">Publikasi BPS</h3>
            <p class="text-sm text-gray-500">
                "Latih AI" mengirim publikasi ke basis pengetahuan AI (server AI mengambil PDF-nya langsung dari BPS).
                Daftar dokumen yang sudah dilatih ada di
                <a href="{{ route($rute . 'pengetahuan') }}" class="text-[#002D72] font-medium underline">Manajemen Pengetahuan</a>.
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
                        @if ($pub['dilatih'])
                            <span class="px-3 py-1.5 rounded-lg text-xs font-medium bg-green-100 text-green-800"
                                title="{{ $pub['tersimpan'] ?? '' }}">Sudah ada di basis pengetahuan AI</span>
                        @elseif ($pub['tersimpan'] || !empty($pub['pdf']))
                            @if ($pub['tersimpan'])
                                <span class="px-3 py-1.5 rounded-lg text-xs font-medium bg-blue-100 text-blue-800"
                                    title="{{ $pub['tersimpan'] }}">PDF tersimpan, belum dilatih</span>
                            @endif
                            <form method="POST" action="{{ route($rute . 'databps.publikasi.simpan', $pub['pub_id']) }}"
                                x-data="{ menyimpan: false }" @submit="menyimpan = true">
                                @csrf
                                <button type="submit" :disabled="menyimpan"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-[#002D72] text-white hover:bg-[#001f52] disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span x-show="!menyimpan">{{ $pub['tersimpan'] ? 'Latih ke AI' : 'Latih AI' }}</span>
                                    <span x-show="menyimpan" x-cloak>Memproses PDF...</span>
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

@push('scripts')
    <script>
        // Tombol "Ambil & Latih Publikasi Baru": daftar publikasi yang belum dilatih, lalu tiap publikasi
        // diunduh & dikirim ke layanan AI dalam permintaan terpisah agar tidak terkena batas waktu halaman.
        window.publikasiOtomatisBps = function (konfig) {
            const token = '{{ csrf_token() }}';
            const kirim = async (alamat, isi) => {
                const res = await fetch(alamat, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(isi || {}),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const validasi = json.errors ? Object.values(json.errors).flat()[0] : null;
                    throw Object.assign(new Error(json.galat || validasi || json.message || `Permintaan gagal (HTTP ${res.status}).`), { berhenti: !!json.berhenti });
                }
                return json;
            };
            const cegahTutup = e => { e.preventDefault(); e.returnValue = ''; };

            return {
                sejak: konfig.sejak, kata: konfig.kata,
                berjalan: false, berhenti: false, selesai: 0, berhasil: 0, total: 0, status: '', log: [],

                async mulai() {
                    Object.assign(this, { berjalan: true, berhenti: false, selesai: 0, berhasil: 0, total: 0, log: [], status: 'Mengambil daftar publikasi dari WebAPI BPS...' });
                    window.addEventListener('beforeunload', cegahTutup);
                    try {
                        const awal = await kirim(konfig.mulai, { sejak: this.sejak, kata: this.kata });
                        this.total = awal.publikasi.length;
                        if (this.total === 0) {
                            this.log.push({ galat: false, teks: `Semua ${awal.jumlah} publikasi yang cocok sudah ada di basis pengetahuan AI.` });
                        }
                        for (const p of awal.publikasi) {
                            if (this.berhenti) { this.log.unshift({ galat: true, teks: 'Dihentikan.' }); break; }
                            this.status = `Memproses ${this.selesai + 1} dari ${this.total}: ${p.judul}`;
                            try {
                                this.log.unshift({ galat: false, teks: (await kirim(konfig.satu.replace('__ID__', encodeURIComponent(p.id)))).pesan });
                                this.berhasil++;
                            } catch (e) {
                                this.log.unshift({ galat: true, teks: e.message });
                                if (e.berhenti) { this.log.unshift({ galat: true, teks: 'Dihentikan karena kesalahan di atas. Coba lagi nanti.' }); break; }
                            }
                            this.selesai++;
                        }
                    } catch (e) {
                        this.log.unshift({ galat: true, teks: e.message });
                    }
                    this.status = `Selesai: ${this.berhasil} dari ${this.total} publikasi dilatihkan ke AI.`;
                    this.berjalan = false;
                    window.removeEventListener('beforeunload', cegahTutup);
                },
            };
        };
    </script>
@endpush
