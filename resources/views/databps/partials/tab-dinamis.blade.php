{{-- Tab Tabel Dinamis: alurnya sama dengan halaman "Produk - Tabel Dinamis" situs BPS.
     Pilih kategori subjek, subjek, dan tabel; tentukan tahun, turunan tahun, karakteristik, dan judul baris;
     klik Tambah (maksimal 2 data), lalu Submit untuk melihat tabelnya dan menyimpannya sebagai indikator. --}}
@php
    $konfigurasi = [
        'kategori' => $katalog['kategori'],
        'subjek' => $katalog['subjek'],
        'variabel' => $katalog['variabel'],
        'urlPilihan' => route($rute . 'databps.dinamis.pilihan', ['var' => 0]),
        'maks' => 2,
        'maksTahun' => 16,
    ];
    $kelasSelect = 'w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none';
@endphp

<div class="bg-white rounded-xl shadow-lg p-4 md:p-6 space-y-5" x-data="tabelDinamisBps({{ Js::from($konfigurasi) }})">
    {{-- Warna kuning disamakan dengan peringatan di situs BPS (kelas Tailwind-nya belum ada di CSS build). --}}
    <div class="flex items-center gap-2 px-4 py-3 rounded-lg text-sm font-medium text-gray-900" style="background-color: #facc15">
        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z" />
        </svg>
        Hanya dapat memilih maksimal 2 data.
    </div>

    <div>
        <label for="kategori-subjek" class="block text-sm font-medium text-gray-700 mb-2">Kategori Subjek</label>
        <select id="kategori-subjek" x-model="kategori" @change="subjek = ''" class="{{ $kelasSelect }}">
            <option value="">Semua</option>
            <template x-for="k in konfig.kategori" :key="k.id">
                <option :value="String(k.id)" x-text="k.nama"></option>
            </template>
        </select>
    </div>

    <div>
        <label for="subjek" class="block text-sm font-medium text-gray-700 mb-2">Subjek</label>
        <select id="subjek" x-model="subjek" class="{{ $kelasSelect }}">
            <option value="">Semua</option>
            <template x-for="s in subjekTampil" :key="s.id">
                <option :value="String(s.id)" x-text="s.nama"></option>
            </template>
        </select>
    </div>

    <div>
        <label for="cari-tabel" class="block text-sm font-medium text-gray-700 mb-2">Tabel / Indikator</label>
        <div class="relative mb-2">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
            </svg>
            <input id="cari-tabel" type="text" x-model="cari" placeholder="Cari judul tabel"
                class="pl-9 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none w-full">
        </div>
        <div class="border border-gray-200 rounded-lg overflow-y-auto divide-y divide-gray-100" style="max-height: 18rem">
            <template x-for="v in tabelTampil" :key="v.id">
                <button type="button" @click="pilihTabel(v)" :aria-pressed="dipilih !== null && dipilih.id === v.id"
                    class="w-full text-left px-4 py-2.5 text-sm transition-colors"
                    :class="dipilih !== null && dipilih.id === v.id ? 'bg-blue-50 text-[#002D72] font-semibold' : 'text-gray-700 hover:bg-gray-50'"
                    x-text="v.judul"></button>
            </template>
            <p x-show="tabelTampil.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">
                {{ $galat ? 'Daftar tabel belum dapat dimuat.' : 'Tidak ada tabel yang cocok.' }}
            </p>
        </div>
        <p class="mt-1 text-xs text-gray-500" x-text="`${tabelTampil.length} dari ${konfig.variabel.length} tabel`"></p>
    </div>

    {{-- Isian untuk tabel yang dipilih: Tahun, Turunan Tahun, Karakteristik, Judul Baris --}}
    <div x-show="dipilih !== null" x-cloak class="border border-gray-200 rounded-lg p-4 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-semibold text-gray-900" x-text="dipilih ? dipilih.judul : ''"></p>
            <span x-show="memuat" class="text-sm text-gray-500">Memuat pilihan dari BPS...</span>
        </div>
        <p x-show="galat" x-text="galat" class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></p>
        <template x-if="pilihan">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <template x-for="grup in grupPilihan" :key="grup.kunci">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-gray-700" x-text="grup.judul"></span>
                            <span x-show="grup.opsi.length > 1" class="text-xs flex gap-3">
                                <button type="button" class="text-[#002D72] hover:underline" @click="pilihSemua(grup.kunci)">Semua</button>
                                <button type="button" class="text-gray-500 hover:underline" @click="sel[grup.kunci] = []">Kosongkan</button>
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-2 overflow-y-auto" style="max-height: 10rem">
                            <template x-for="o in grup.opsi" :key="o.id">
                                <label class="flex items-center gap-1.5 px-3 py-1.5 border rounded-lg text-sm cursor-pointer"
                                    :class="sel[grup.kunci].includes(o.id) ? 'border-[#002D72] bg-blue-50 text-[#002D72] font-medium' : 'border-gray-300 text-gray-600'">
                                    <input type="checkbox" :value="o.id" x-model="sel[grup.kunci]" class="rounded">
                                    <span x-text="o.label"></span>
                                </label>
                            </template>
                            <span x-show="grup.opsi.length === 0" class="text-sm text-gray-500">Tidak ada untuk tabel ini.</span>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" @click="tambah()" :disabled="!bisaTambah"
            class="bg-[#002D72] text-white hover:bg-[#001f52] px-5 py-2.5 rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
            Tambah
        </button>
        <button type="button" @click="aturUlang()"
            class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
            Atur Ulang
        </button>
        <span x-show="alasanTidakBisaTambah" x-text="alasanTidakBisaTambah" class="text-xs text-gray-500"></span>
    </div>

    {{-- Data Terpilih (maksimal 2) --}}
    <div class="space-y-4">
        <h4 class="font-semibold text-gray-900">
            Data Terpilih <span class="font-normal text-gray-500" x-text="`(${terpilih.length}/${konfig.maks})`"></span>
        </h4>
        <p x-show="terpilih.length === 0" class="text-sm text-gray-500">
            Belum ada data. Pilih tabel dan isiannya, lalu klik Tambah.
        </p>
        <template x-for="(d, i) in terpilih" :key="d.kunci">
            <div class="flex items-start justify-between gap-3 border border-gray-200 rounded-lg px-4 py-3">
                <div class="min-w-0">
                    <p class="font-medium text-gray-900" x-text="`${i + 1}. ${d.judul}`"></p>
                    <template x-for="r in d.ringkasan" :key="r">
                        <p class="text-xs text-gray-500" x-text="r"></p>
                    </template>
                </div>
                <button type="button" @click="hapus(i)" class="text-sm text-red-600 hover:underline flex-shrink-0">Hapus</button>
            </div>
        </template>
        <p x-show="terpilih.length >= konfig.maks" class="text-sm text-orange-700">
            Anda telah mencapai batas maksimum 2 data.
        </p>
    </div>

    <form method="GET" action="{{ route($rute . 'databps.dinamis.hasil') }}" @submit="mengirim = true" class="flex justify-end">
        <template x-for="(d, i) in terpilih" :key="d.kunci">
            <div>
                <input type="hidden" :name="`data[${i}][var]`" :value="d.var">
                <template x-for="v in d.tahun" :key="'t' + v">
                    <input type="hidden" :name="`data[${i}][tahun][]`" :value="v">
                </template>
                <template x-for="v in d.turtahun" :key="'u' + v">
                    <input type="hidden" :name="`data[${i}][turtahun][]`" :value="v">
                </template>
                <template x-for="v in d.karakteristik" :key="'k' + v">
                    <input type="hidden" :name="`data[${i}][karakteristik][]`" :value="v">
                </template>
                <template x-for="v in d.baris" :key="'b' + v">
                    <input type="hidden" :name="`data[${i}][baris][]`" :value="v">
                </template>
            </div>
        </template>
        <button type="submit" :disabled="terpilih.length === 0 || mengirim"
            class="bg-[#002D72] text-white hover:bg-[#001f52] px-6 py-3 rounded-lg text-sm font-semibold shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
            <span x-show="!mengirim">Submit</span>
            <span x-show="mengirim" x-cloak>Mengambil data dari BPS...</span>
        </button>
    </form>
</div>

@push('scripts')
    <script>
        // Komponen Alpine tab Tabel Dinamis. Didefinisikan global agar bisa dipakai x-data.
        window.tabelDinamisBps = function (konfig) {
            const isianKosong = () => ({ tahun: [], turtahun: [], karakteristik: [], baris: [] });

            return {
                konfig,
                kategori: '',
                subjek: '',
                cari: '',
                dipilih: null,  // tabel yang sedang diisi
                pilihan: null,  // isian dari WebAPI BPS untuk tabel itu
                sel: isianKosong(),
                memuat: false,
                galat: '',
                urutan: 0,      // abaikan balasan lama bila pengguna sudah memilih tabel lain
                terpilih: [],
                mengirim: false,

                get subjekTampil() {
                    return this.kategori === '' ? konfig.subjek : konfig.subjek.filter(s => String(s.kategori) === this.kategori);
                },

                get tabelTampil() {
                    const q = this.cari.trim().toLowerCase();
                    return konfig.variabel.filter(v =>
                        (this.kategori === '' || String(v.kategori) === this.kategori) &&
                        (this.subjek === '' || String(v.subjek) === this.subjek) &&
                        (q === '' || v.judul.toLowerCase().includes(q)));
                },

                get grupPilihan() {
                    const p = this.pilihan;
                    return [
                        { kunci: 'tahun', judul: 'Tahun', opsi: p.tahun.map(t => ({ id: t, label: t })) },
                        { kunci: 'turtahun', judul: 'Turunan Tahun', opsi: p.turtahun },
                        { kunci: 'karakteristik', judul: 'Karakteristik' + (p.labelKarakteristik ? ` (${p.labelKarakteristik})` : ''), opsi: p.karakteristik },
                        { kunci: 'baris', judul: 'Judul Baris' + (p.labelBaris ? ` (${p.labelBaris})` : ''), opsi: p.baris },
                    ];
                },

                async pilihTabel(v) {
                    const ke = ++this.urutan;
                    this.dipilih = v;
                    this.pilihan = null;
                    this.galat = '';
                    this.memuat = true;
                    try {
                        const res = await fetch(konfig.urlPilihan.replace('/0/pilihan', `/${v.id}/pilihan`), {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const json = await res.json().catch(() => ({}));
                        if (ke !== this.urutan) return;
                        if (!res.ok) throw new Error(json.galat || 'Pilihan tabel gagal dimuat dari WebAPI BPS.');

                        // Semua ID dijadikan teks agar cocok dengan nilai checkbox.
                        const teks = daftar => daftar.map(o => ({ id: String(o.id), label: o.label }));
                        this.pilihan = { ...json, turtahun: teks(json.turtahun), karakteristik: teks(json.karakteristik), baris: teks(json.baris) };
                        this.sel = {
                            tahun: json.tahun.slice(0, 2),
                            turtahun: this.pilihan.turtahun.map(o => o.id),
                            karakteristik: this.pilihan.karakteristik.map(o => o.id),
                            baris: this.pilihan.baris.map(o => o.id),
                        };
                    } catch (e) {
                        if (ke === this.urutan) this.galat = e.message;
                    } finally {
                        if (ke === this.urutan) this.memuat = false;
                    }
                },

                pilihSemua(kunci) {
                    const opsi = this.grupPilihan.find(g => g.kunci === kunci).opsi.map(o => o.id);
                    this.sel[kunci] = kunci === 'tahun' ? opsi.slice(0, konfig.maksTahun) : opsi;
                },

                // Teks alasan tombol Tambah tidak aktif; null berarti bisa ditambah.
                get alasanTidakBisaTambah() {
                    if (this.terpilih.length >= konfig.maks) return `Anda telah mencapai batas maksimum ${konfig.maks} data.`;
                    if (this.dipilih === null) return 'Pilih tabel terlebih dahulu.';
                    if (this.memuat || this.pilihan === null) return '';
                    if (this.sel.tahun.length === 0) return 'Pilih minimal 1 tahun.';
                    if (this.sel.tahun.length > konfig.maksTahun) return `Paling banyak ${konfig.maksTahun} tahun.`;
                    const kosong = this.grupPilihan.find(g => g.kunci !== 'tahun' && g.opsi.length > 0 && this.sel[g.kunci].length === 0);
                    return kosong ? `Pilih minimal 1 isian ${kosong.judul}.` : null;
                },

                get bisaTambah() {
                    return this.alasanTidakBisaTambah === null;
                },

                tambah() {
                    if (!this.bisaTambah) return;
                    const p = this.pilihan;
                    // Semua isian terpilih = tanpa saringan, agar alamat halaman hasil tetap pendek.
                    const saring = kunci => (this.sel[kunci].length === p[kunci].length ? [] : [...this.sel[kunci]]);
                    const data = {
                        var: this.dipilih.id,
                        judul: this.dipilih.judul,
                        tahun: [...this.sel.tahun].sort(),
                        turtahun: saring('turtahun'),
                        karakteristik: saring('karakteristik'),
                        baris: saring('baris'),
                    };
                    data.kunci = JSON.stringify([data.var, data.tahun, data.turtahun, data.karakteristik, data.baris]);
                    if (this.terpilih.some(d => d.kunci === data.kunci)) return; // data yang sama tidak ditambah dua kali
                    data.ringkasan = this.ringkasan(data);
                    this.terpilih.push(data);
                    this.dipilih = null;
                    this.pilihan = null;
                },

                ringkasan(data) {
                    const p = this.pilihan;
                    const baris = [`Tahun: ${data.tahun.join(', ')}`];
                    const tulis = (kunci, judul) => {
                        if (p[kunci].length <= 1) return; // isian tunggal (misalnya "Tahun") tidak perlu dirangkum
                        const dipilih = p[kunci].filter(o => data[kunci].includes(o.id));
                        const teks = data[kunci].length === 0 ? `semua (${p[kunci].length})`
                            : dipilih.length <= 4 ? dipilih.map(o => o.label).join(', ')
                            : `${dipilih.length} dari ${p[kunci].length}`;
                        baris.push(`${judul}: ${teks}`);
                    };
                    tulis('turtahun', 'Turunan Tahun');
                    tulis('karakteristik', p.labelKarakteristik || 'Karakteristik');
                    tulis('baris', p.labelBaris || 'Judul Baris');
                    return baris;
                },

                hapus(i) {
                    this.terpilih.splice(i, 1);
                },

                aturUlang() {
                    this.urutan++;
                    Object.assign(this, { kategori: '', subjek: '', cari: '', dipilih: null, pilihan: null, memuat: false, galat: '', terpilih: [] });
                    this.sel = isianKosong();
                },
            };
        };
    </script>
@endpush
