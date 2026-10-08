{{-- Form "Simpan sebagai indikator" untuk satu tabel dinamis di halaman hasil Tabel Dinamis (bisa 2 form
     sekaligus).
     Variabel: $rute, $var (ID tabel dinamis), $tabel (judul, satuan, tahunDipilih), $saring (pilihan turunan
     tahun, karakteristik, judul baris), $kategori, $indikator. --}}
@php
    // Nilai lama (setelah gagal validasi) hanya untuk form yang tadi dikirim.
    $pakaiOld = (string) old('var') === (string) $var;
    $lama = fn (string $kunci, $bawaan) => $pakaiOld ? old($kunci, $bawaan) : $bawaan;

    $sama = $indikator->first(fn ($i) => mb_strtolower(trim($i->name)) === mb_strtolower(trim($tabel['judul'])));
    $subjekKe = $kategori->flatMap->subjects->pluck('category_id', 'id');
    $petaIndikator = $indikator->mapWithKeys(fn ($i) => [$i->id => ['subjek' => $i->subject_id, 'kategori' => $subjekKe[$i->subject_id] ?? null]]);
    $indikatorAwal = $pakaiOld ? old('indicator_id') : $sama?->id;
    $subjekAwal = $lama('subject_id', $sama?->subject_id);
@endphp
<form method="POST" action="{{ route($rute . 'databps.tabel.simpan') }}"
    class="bg-white rounded-xl shadow-lg p-4 md:p-6 space-y-5"
    x-data="{
        daftarSubjek: {{ Js::from($kategori->mapWithKeys(fn ($k) => [$k->id => $k->subjects->map->only(['id', 'name'])->values()])) }},
        petaIndikator: {{ Js::from($petaIndikator) }},
        kategori: '{{ $subjekAwal ? ($subjekKe[$subjekAwal] ?? '') : '' }}',
        subjek: '{{ $subjekAwal }}',
        mode: '{{ $indikatorAwal ? 'perbarui' : 'baru' }}',
        indikatorId: '{{ $indikatorAwal }}',
        menyimpan: false,
        pilihIndikator() {
            const i = this.petaIndikator[this.indikatorId];
            if (i) { this.kategori = String(i.kategori ?? ''); this.subjek = String(i.subjek); }
        },
    }" @submit="menyimpan = true">
    @csrf
    <input type="hidden" name="var" value="{{ $var }}">
    @foreach ($tabel['tahunDipilih'] as $th)
        <input type="hidden" name="tahun[]" value="{{ $th }}">
    @endforeach
    @foreach ($saring as $kunci => $daftarId)
        @foreach ($daftarId as $id)
            <input type="hidden" name="{{ $kunci }}[]" value="{{ $id }}">
        @endforeach
    @endforeach

    <div>
        <h3 class="font-semibold text-gray-900">Simpan sebagai indikator</h3>
        <p class="text-sm text-gray-500">Indikator muncul di Kelola Data dan Lihat Data, dan bisa langsung dipakai dashboard serta narasi AI.
            Indikator ini tertaut ke API: setiap kali dibuka, datanya diperbarui dari WebAPI BPS dengan seluruh tahun yang tersedia.</p>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 text-sm">
        <label class="flex items-center gap-2 px-4 py-2.5 border rounded-lg cursor-pointer"
            :class="mode === 'baru' ? 'border-[#002D72] bg-blue-50' : 'border-gray-300'">
            <input type="radio" value="baru" x-model="mode" class="text-[#002D72]"> Buat indikator baru
        </label>
        <label class="flex items-center gap-2 px-4 py-2.5 border rounded-lg cursor-pointer"
            :class="mode === 'perbarui' ? 'border-[#002D72] bg-blue-50' : 'border-gray-300'">
            <input type="radio" value="perbarui" x-model="mode" class="text-[#002D72]"> Perbarui indikator yang sudah ada
        </label>
    </div>

    <div x-show="mode === 'perbarui'" x-cloak class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">Indikator yang diperbarui <span class="text-red-500">*</span></label>
        <select name="indicator_id" x-model="indikatorId" @change="pilihIndikator()" :disabled="mode !== 'perbarui'" :required="mode === 'perbarui'"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent">
            <option value="">Pilih indikator...</option>
            @foreach ($indikator as $i)
                <option value="{{ $i->id }}" @selected((string) $indikatorAwal === (string) $i->id)>
                    {{ $i->name }}{{ $i->subject ? ' — ' . $i->subject->name : '' }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-orange-700">Data lama indikator ini akan diganti dengan data di atas.</p>
        @if ($sama)
            <p class="text-xs text-gray-500">Indikator dengan nama yang sama sudah ada, jadi pilihan ini disarankan agar tidak terjadi duplikat.</p>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Kategori <span class="text-red-500">*</span></label>
            <select x-model="kategori" @change="subjek = ''"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent truncate">
                <option value="">Pilih Kategori...</option>
                @foreach ($kategori as $k)
                    <option value="{{ $k->id }}">{{ Str::limit($k->name, 35) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Subjek <span class="text-red-500">*</span></label>
            <select name="subject_id" x-model="subjek" required :disabled="!kategori"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent truncate">
                <option value="">Pilih Subjek...</option>
                <template x-for="s in (daftarSubjek[kategori] || [])" :key="s.id">
                    <option :value="String(s.id)" x-text="s.name" :selected="String(s.id) === subjek"></option>
                </template>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Indikator <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ $lama('name', $tabel['judul']) }}" required maxlength="255"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Satuan</label>
            <input type="text" name="unit" value="{{ $lama('unit', $tabel['satuan']) }}" maxlength="50" placeholder="Jiwa, Persen..."
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent">
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" :disabled="menyimpan"
            class="bg-[#002D72] hover:bg-[#001f52] text-white px-6 py-3 rounded-lg text-sm font-semibold shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
            <span x-show="!menyimpan" x-text="mode === 'perbarui' ? 'Perbarui Indikator' : 'Simpan ke Kelola Data'"></span>
            <span x-show="menyimpan" x-cloak>Menyimpan...</span>
        </button>
    </div>
</form>
