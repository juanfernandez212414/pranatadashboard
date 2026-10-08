{{-- Tab Sinkronisasi: impor banyak tabel BPS sekaligus (tabel publikasi SIMDASI, tabel dinamis, tabel statis)
     dengan SELURUH tahunnya menjadi indikator, dan perbarui semua indikator yang tertaut ke API.
     Browser memproses tabel satu per satu (satu permintaan per tabel), jadi tidak terkena batas waktu
     halaman walau tabelnya ratusan. --}}
@php
    $konfigurasi = [
        'urlKatalog' => route($rute . 'databps.sinkron.katalog'),
        'urlImpor' => route($rute . 'databps.sinkron.impor'),
        'urlPerbarui' => route($rute . 'databps.sinkron.perbarui', ['indicator' => 0]),
        'urlLihat' => route($rute . 'lihatdata.show', ['id' => 0]),
        'sumber' => \App\Services\Bps\SinkronisasiBps::SUMBER,
        'indikatorApi' => $sinkron['indikator'],
        'siap' => !$galat,
    ];
    $kelasTombol = 'bg-[#002D72] text-white hover:bg-[#001f52] px-5 py-2.5 rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed';
    $kelasTombolGaris = 'px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed';
@endphp

<div class="space-y-6" x-data="sinkronBps({{ Js::from($konfigurasi) }})">
    {{-- Cara kerja & cara menghubungkan --}}
    <div class="bg-white rounded-xl shadow-lg p-4 md:p-6 space-y-3">
        <h3 class="text-lg md:text-xl font-semibold text-gray-900">Sinkronisasi Data dari WebAPI BPS</h3>
        <p class="text-sm text-gray-600">
            Ambil tabel BPS Kota Pematangsiantar langsung dari WebAPI BPS, <span class="font-semibold">lengkap dengan seluruh tahun yang tersedia</span>,
            lalu simpan sebagai indikator. Indikator ini langsung dipakai Dashboard, Lihat Data, ekspor, dan narasi AI, dan dapat
            diperbarui kapan saja dengan tombol <span class="font-semibold">Perbarui Semua dari API</span>.
        </p>
        <ul class="text-sm text-gray-600 list-disc list-inside space-y-1">
            <li><span class="font-semibold">Tabel Publikasi (SIMDASI)</span>: tabel-tabel publikasi "Kota Pematangsiantar Dalam Angka" (wilayah {{ $sinkron['wilayahSimdasi'] }}); semua tahun digabung dalam satu indikator.</li>
            <li><span class="font-semibold">Tabel Dinamis</span>: tabel dinamis di situs BPS, diambil untuk semua tahun, karakteristik, dan judul baris.</li>
            <li><span class="font-semibold">Tabel Statis</span>: tabel statis (HTML) di situs BPS, satu tabel menjadi satu indikator.</li>
        </ul>
        @if ($galat)
            <div class="border border-gray-200 rounded-lg p-4 text-sm text-gray-700 space-y-2">
                <p class="font-semibold text-gray-900">Cara menghubungkan PRANATA ke WebAPI BPS</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Buka file <code>.env</code> di folder proyek PRANATA.</li>
                    <li>Isi baris <code>BPS_API_KEY=</code> dengan kunci (key) dari akun Anda di webapi.bps.go.id, dan pastikan <code>BPS_DOMAIN=1273</code>.</li>
                    <li>Jalankan <code>php artisan config:clear</code>, lalu <code>php artisan bps:cek</code> untuk menguji koneksi.</li>
                    <li>Muat ulang halaman ini.</li>
                </ol>
            </div>
        @endif
    </div>

    {{-- Indikator yang sudah tertaut ke API --}}
    <div class="bg-white rounded-xl shadow-lg">
        <div class="p-4 md:p-6 border-b border-gray-200 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-900">
                    Indikator dari API BPS <span class="font-normal text-gray-500" x-text="`(${indikatorApi.length})`"></span>
                </h3>
                <p class="text-sm text-gray-500">
                    @if ($sinkron['terakhir'])
                        Terakhir disinkronkan {{ $sinkron['terakhir'] }}.
                    @else
                        Belum ada indikator yang diambil dari API.
                    @endif
                    Pembaruan otomatis harian berjalan bila penjadwal Laravel aktif (<code>php artisan schedule:work</code>).
                </p>
            </div>
            <div class="flex flex-wrap gap-2 flex-shrink-0">
                <button type="button" class="{{ $kelasTombolGaris }}" @click="tampilIndikator = !tampilIndikator" x-show="indikatorApi.length > 0"
                    x-text="tampilIndikator ? 'Sembunyikan Daftar' : 'Lihat Daftar'"></button>
                <button type="button" class="{{ $kelasTombol }}" @click="perbaruiSemua()" :disabled="berjalan || !konfig.siap || indikatorApi.length === 0">
                    Perbarui Semua dari API
                </button>
            </div>
        </div>
        <div x-show="tampilIndikator" x-cloak class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left font-bold text-gray-900">Indikator</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-900">Sumber</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-900">Disinkronkan</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="i in indikatorApi" :key="i.id">
                        <tr>
                            <td class="px-4 py-2.5">
                                <a :href="urlLihat(i.id)" class="font-medium text-[#002D72] hover:underline" x-text="i.nama"></a>
                                <p class="text-xs text-gray-500" x-text="i.subjek"></p>
                            </td>
                            <td class="px-4 py-2.5 text-gray-600" x-text="i.sumber"></td>
                            <td class="px-4 py-2.5 text-gray-600" x-text="i.sinkron || '-'"></td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button" class="text-sm text-[#002D72] hover:underline disabled:opacity-50" :disabled="berjalan" @click="perbarui([i])">Perbarui</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Kemajuan proses & catatan hasil --}}
    <div x-show="log.length > 0 || berjalan" x-cloak class="bg-white rounded-xl shadow-lg p-4 md:p-6 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-semibold text-gray-900" x-text="berjalan ? `Memproses ${kemajuan.selesai + 1} dari ${kemajuan.total}: ${kemajuan.judul}` : `Selesai: ${ringkasanLog}`"></p>
            <button type="button" x-show="berjalan" @click="berhenti = true" class="text-sm text-red-600 hover:underline">Hentikan</button>
            <button type="button" x-show="!berjalan" @click="log = []" class="text-sm text-gray-500 hover:underline">Tutup</button>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
            <div class="bg-[#002D72] h-2 rounded-full" :style="`width: ${kemajuan.total ? Math.round(kemajuan.selesai / kemajuan.total * 100) : 0}%`"></div>
        </div>
        <ul class="text-sm space-y-1 overflow-y-auto" style="max-height: 14rem">
            <template x-for="(l, n) in log" :key="n">
                <li :class="l.galat ? 'text-red-700' : 'text-gray-700'" x-text="(l.galat ? '✗ ' : '✓ ') + l.teks"></li>
            </template>
        </ul>
    </div>

    {{-- Katalog tabel per sumber --}}
    <div class="bg-white rounded-xl shadow-lg">
        <div class="p-4 md:p-6 border-b border-gray-200 space-y-4">
            <div class="flex flex-wrap gap-2">
                <template x-for="(nama, kunci) in konfig.sumber" :key="kunci">
                    <button type="button" @click="gantiSumber(kunci)"
                        class="px-4 py-2 border rounded-lg text-sm font-medium"
                        :class="sumber === kunci ? 'border-[#002D72] bg-blue-50 text-[#002D72]' : 'border-gray-300 text-gray-600 hover:bg-gray-50'">
                        <span x-text="nama"></span>
                        <span x-show="katalog[kunci]" class="font-normal" x-text="katalog[kunci] ? `(${katalog[kunci].length})` : ''"></span>
                    </button>
                </template>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="cari-sinkron" class="block text-sm font-medium text-gray-700 mb-2">Cari tabel</label>
                    <input id="cari-sinkron" type="text" x-model="cari" placeholder="Judul, kode tabel, atau subjek"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none">
                </div>
                <div>
                    <label for="subjek-tujuan" class="block text-sm font-medium text-gray-700 mb-2">Subjek tujuan indikator baru</label>
                    <select id="subjek-tujuan" x-model="subjekTujuan"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none">
                        <option value="">Otomatis mengikuti kategori &amp; subjek BPS</option>
                        @foreach ($sinkron['kategori'] as $k)
                            <optgroup label="{{ $k->name }}">
                                @foreach ($k->subjects as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="{{ $kelasTombol }}" @click="imporDipilih()" :disabled="berjalan || dipilih.length === 0"
                    x-text="`Impor yang Dipilih (${dipilih.length})`"></button>
                <button type="button" class="{{ $kelasTombolGaris }}" @click="pilihSemuaTampil()" :disabled="berjalan || tabelTampil.length === 0">Pilih Semua yang Tampil</button>
                <button type="button" class="{{ $kelasTombolGaris }}" @click="dipilih = []" :disabled="berjalan || dipilih.length === 0">Kosongkan Pilihan</button>
                <button type="button" class="{{ $kelasTombolGaris }}" @click="muatKatalog(sumber, true)" :disabled="berjalan || memuat[sumber]">Muat Ulang Daftar</button>
            </div>
            <p class="text-xs text-gray-500">
                Tabel yang sudah menjadi indikator diperbarui (tidak dibuat ganda). Tabel yang namanya sama dengan indikator yang sudah ada
                akan menautkan indikator tersebut ke API dan mengganti datanya.
            </p>
        </div>

        <div class="p-4 md:p-6 bg-slate-50/50">
            <p x-show="memuat[sumber]" class="text-center text-sm text-gray-500 py-8">Memuat daftar tabel dari WebAPI BPS...</p>
            <p x-show="galatKatalog[sumber]" x-text="galatKatalog[sumber]" class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></p>

            <div x-show="katalog[sumber]" class="space-y-2">
                <p class="text-xs text-gray-500" x-text="`${tabelTampil.length} dari ${(katalog[sumber] || []).length} tabel`"></p>
                <div class="bg-white border border-gray-200 rounded-lg divide-y divide-gray-100 overflow-y-auto" style="max-height: 32rem">
                    <template x-for="t in tabelTampil" :key="kunci(t)">
                        <label class="flex items-start gap-3 px-4 py-3 text-sm cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" class="mt-1 rounded" :value="kunci(t)" x-model="dipilih" :disabled="berjalan">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-gray-900">
                                    <span x-show="t.kode" class="text-gray-500" x-text="t.kode + ' '"></span><span x-text="t.judul"></span>
                                </span>
                                <span class="block text-xs text-gray-500" x-text="[t.kategori, t.subjek].filter(Boolean).join(' › ')"></span>
                                <span class="block text-xs text-gray-500" x-text="teksTahun(t)"></span>
                                <span x-show="t.indikator" class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
                                    x-text="t.indikator ? `Sudah menjadi indikator: ${t.indikator.name}` : ''"></span>
                                <span x-show="!t.indikator && t.namaSama" class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                    x-text="t.namaSama ? `Akan menautkan indikator yang sudah ada: ${t.namaSama.name}` : ''"></span>
                            </span>
                        </label>
                    </template>
                    <p x-show="tabelTampil.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">Tidak ada tabel yang cocok.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        // Komponen Alpine tab Sinkronisasi. Didefinisikan global agar bisa dipakai x-data.
        window.sinkronBps = function (konfig) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

            async function kirim(url, metode, isi) {
                const res = await fetch(url, {
                    method: metode,
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                    body: isi ? JSON.stringify(isi) : undefined,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const validasi = json.errors ? Object.values(json.errors).flat()[0] : null;
                    throw new Error(json.galat || validasi || json.message || `Permintaan gagal (HTTP ${res.status}).`);
                }
                return json;
            }

            return {
                konfig,
                sumber: 'simdasi',
                katalog: { dinamis: null, simdasi: null, statis: null },
                memuat: { dinamis: false, simdasi: false, statis: false },
                galatKatalog: { dinamis: '', simdasi: '', statis: '' },
                cari: '',
                subjekTujuan: '',
                dipilih: [],
                indikatorApi: konfig.indikatorApi,
                tampilIndikator: false,
                berjalan: false,
                berhenti: false,
                kemajuan: { selesai: 0, total: 0, judul: '' },
                log: [],

                init() {
                    if (konfig.siap) this.muatKatalog(this.sumber);
                    window.addEventListener('beforeunload', e => { if (this.berjalan) { e.preventDefault(); e.returnValue = ''; } });
                },

                kunci: t => `${t.sumber}:${t.id}`,
                urlLihat: id => konfig.urlLihat.replace(/\/0$/, `/${id}`),

                gantiSumber(sumber) {
                    this.sumber = sumber;
                    if (!this.katalog[sumber] && konfig.siap) this.muatKatalog(sumber);
                },

                async muatKatalog(sumber, paksa = false) {
                    if (this.memuat[sumber] || (this.katalog[sumber] && !paksa)) return;
                    this.memuat[sumber] = true;
                    this.galatKatalog[sumber] = '';
                    try {
                        this.katalog[sumber] = (await kirim(`${konfig.urlKatalog}?sumber=${sumber}`, 'GET')).tabel;
                    } catch (e) {
                        this.galatKatalog[sumber] = e.message;
                    } finally {
                        this.memuat[sumber] = false;
                    }
                },

                get tabelTampil() {
                    const q = this.cari.trim().toLowerCase();
                    return (this.katalog[this.sumber] || []).filter(t => q === '' ||
                        [t.judul, t.kode, t.kategori, t.subjek].join(' ').toLowerCase().includes(q));
                },

                teksTahun(t) {
                    if (t.sumber === 'dinamis') return 'Seluruh tahun yang tersedia diambil';
                    if (!t.tahun || t.tahun.length === 0) return '';
                    const awal = t.tahun[0], akhir = t.tahun[t.tahun.length - 1];
                    return `Tahun ${awal === akhir ? awal : `${awal}–${akhir}`} (${t.tahun.length} tahun)`;
                },

                pilihSemuaTampil() {
                    this.dipilih = [...new Set([...this.dipilih, ...this.tabelTampil.map(this.kunci)])];
                },

                get ringkasanLog() {
                    const gagal = this.log.filter(l => l.galat).length;
                    return `${this.log.length - gagal} berhasil, ${gagal} gagal`;
                },

                // Menjalankan tugas satu per satu (satu permintaan per tabel/indikator) dengan kemajuan.
                async jalankan(daftar, judul, tugas) {
                    this.berjalan = true;
                    this.berhenti = false;
                    this.log = [];
                    this.kemajuan = { selesai: 0, total: daftar.length, judul: '' };
                    for (const butir of daftar) {
                        if (this.berhenti) {
                            this.log.push({ galat: true, teks: 'Dihentikan oleh pengguna.' });
                            break;
                        }
                        this.kemajuan.judul = judul(butir);
                        try {
                            this.log.push({ galat: false, teks: (await tugas(butir)).pesan });
                        } catch (e) {
                            this.log.push({ galat: true, teks: `${judul(butir)}: ${e.message}` });
                        }
                        this.kemajuan.selesai++;
                    }
                    this.berjalan = false;
                },

                async imporDipilih() {
                    const semua = Object.values(this.katalog).flatMap(k => k || []);
                    const daftar = this.dipilih.map(k => semua.find(t => this.kunci(t) === k)).filter(Boolean);
                    const timpa = daftar.filter(t => !t.indikator && t.namaSama).length;
                    if (timpa > 0 && !confirm(`${timpa} tabel bernama sama dengan indikator yang sudah ada. Data indikator tersebut akan diganti dengan data dari API BPS. Lanjutkan?`)) return;

                    await this.jalankan(daftar, t => t.judul, async t => {
                        const hasil = await kirim(konfig.urlImpor, 'POST', { sumber: t.sumber, id: t.id, subject_id: this.subjekTujuan || null });
                        t.indikator = hasil.indikator;
                        t.namaSama = null;
                        if (!this.indikatorApi.some(i => i.id === hasil.indikator.id)) {
                            this.indikatorApi.push({ id: hasil.indikator.id, nama: hasil.indikator.name, subjek: '', sumber: konfig.sumber[t.sumber], sinkron: 'baru saja' });
                        }
                        return hasil;
                    });
                    // Tabel yang gagal tercatat di log beserta alasannya; pilihan dikosongkan agar tidak
                    // ikut terimpor ulang tanpa terlihat saat pengguna pindah ke sumber lain.
                    this.dipilih = [];
                },

                perbaruiSemua() {
                    return this.perbarui(this.indikatorApi);
                },

                async perbarui(daftar) {
                    await this.jalankan([...daftar], i => i.nama, async i => {
                        const hasil = await kirim(konfig.urlPerbarui.replace(/\/0$/, `/${i.id}`), 'POST');
                        i.sinkron = 'baru saja';
                        return hasil;
                    });
                },
            };
        };
    </script>
@endpush
