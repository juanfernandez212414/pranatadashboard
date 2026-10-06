{{-- resources/views/penanggungjawab/model.blade.php --}}

<x-penanggungjawablayout title="Manajemen Model - penanggungjawab PRANATA">

    {{-- Header Halaman --}}
    <div class="mb-8">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2 flex items-center gap-3">
            {{-- Icon AI/Brain --}}
            <svg class="w-8 h-8 text-[#002D72]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 0v2m0-2h2m-2 0H10" />
            </svg>
            Manajemen Model AI
        </h1>
        <p class="text-gray-500 text-sm md:text-base">Pilih "Otak AI" yang akan digunakan untuk menghasilkan narasi
            statistik di dalam sistem PRANATA.</p>
    </div>

    {{-- Form Utama --}}
    <form action="{{ route('penanggungjawab.model.update') }}" method="POST">
        @csrf
        @method('PATCH')

        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 md:p-8" x-data="{
            isChecking: false,
            statusMsg: '',
            statusType: '',
            polling: null,
            retryCount: 0,
            maxRetries: 24, // 24 * 5 detik = 120 detik (2 menit)
            checkStatus(isRetry = false) {
                if(!isRetry) {
                    this.isChecking = true;
                    this.statusMsg = 'Menghubungi server...';
                    this.statusType = 'info';
                    this.retryCount = 0;
                }
                
                fetch('{{ route('penanggungjawab.model.checkStatus') }}')
                    .then(res => res.json())
                    .then(data => {
                        if(data.status === 'starting') {
                            this.handleRetry();
                        } else if(data.status === 'active') {
                            this.isChecking = false;
                            this.statusMsg = data.message;
                            this.statusType = 'success';
                            if(this.polling) clearTimeout(this.polling);
                        } else {
                            this.isChecking = false;
                            this.statusMsg = data.message;
                            this.statusType = 'error';
                            if(this.polling) clearTimeout(this.polling);
                        }
                    })
                    .catch(err => {
                        this.handleRetry();
                    });
            },
            handleRetry() {
                this.retryCount++;
                if (this.retryCount <= this.maxRetries) {
                    this.statusType = 'warning';
                    this.statusMsg = `AI Server sedang dipanaskan (waking up). Mengecek otomatis... (${this.retryCount}/${this.maxRetries})`;
                    if(this.polling) clearTimeout(this.polling);
                    this.polling = setTimeout(() => this.checkStatus(true), 5000); // Tunggu 5 detik lalu cek lagi
                } else {
                    this.isChecking = false;
                    this.statusType = 'error';
                    this.statusMsg = 'Waktu habis. Server gagal merespons setelah 2 menit. Silakan cek status di Hugging Face.';
                }
            }
        }">
            <div
                class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 border-b border-gray-100 pb-5 gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#002D72]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01">
                            </path>
                        </svg>
                        Pilih Model Aktif
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Satu model terpilih akan menangani seluruh pemrosesan dan
                        generasi narasi.</p>
                </div>
                {{-- Tombol-tombol Desktop --}}
                <div class="hidden md:flex items-center gap-3">
                    <button type="button" @click="checkStatus()" :disabled="isChecking"
                        class="inline-flex items-center justify-center gap-2 bg-[#002D72] hover:bg-[#001f52] text-white px-6 py-2.5 rounded-xl font-bold transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 disabled:opacity-70 disabled:hover:translate-y-0">
                        <svg x-show="!isChecking" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <svg x-show="isChecking" x-cloak class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="isChecking ? 'Waking up...' : 'Bangunkan AI Server'"></span>
                    </button>

                    <button type="submit"
                        class="inline-flex items-center gap-2 bg-gradient-to-r from-[#002D72] to-[#004B9C] hover:from-[#004B9C] hover:to-[#002D72] text-white px-6 py-2.5 rounded-xl font-bold transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        Simpan Konfigurasi
                    </button>
                </div>

                {{-- Tombol-tombol Mobile --}}
                <div class="flex md:hidden w-full items-center gap-3 mt-2">
                    <button type="button" @click="checkStatus()" :disabled="isChecking"
                        class="w-full inline-flex items-center justify-center gap-2 bg-[#002D72] hover:bg-[#001f52] text-white px-6 py-2.5 rounded-xl font-bold transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 disabled:opacity-70 disabled:hover:translate-y-0">
                        <svg x-show="!isChecking" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <svg x-show="isChecking" x-cloak class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="isChecking ? 'Waking up...' : 'Bangunkan AI Server'"></span>
                    </button>
                </div>
            </div>

            <!-- Notifikasi Sukses Simpan -->
            @if (session('success'))
                <div
                    class="mb-6 bg-green-50 border border-green-200 text-green-800 px-5 py-4 rounded-xl shadow-sm flex items-center gap-4 animate-fade-in-down">
                    <div class="bg-green-100 p-2 rounded-full shrink-0">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <p class="font-bold">Berhasil Diperbarui!</p>
                        <p class="text-sm mt-0.5 text-green-700">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            <!-- Notifikasi Error Simpan -->
            @if ($errors->any())
                <div
                    class="mb-6 bg-red-50 border border-red-200 text-red-800 px-5 py-4 rounded-xl shadow-sm flex items-start gap-4">
                    <div class="bg-red-100 p-2 rounded-full shrink-0 mt-0.5">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="font-bold">Oops! Terjadi kesalahan:</p>
                        <ul class="mt-1 list-disc list-inside text-sm text-red-700">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <!-- Pesan Status Server -->
            <div x-show="statusMsg" x-cloak 
                class="mb-6 px-4 py-3 rounded-xl border flex items-center gap-3 transition-all"
                :class="{
                    'bg-green-50 text-green-800 border-green-200': statusType === 'success',
                    'bg-yellow-50 text-yellow-800 border-yellow-200': statusType === 'warning',
                    'bg-red-50 text-red-800 border-red-200': statusType === 'error',
                    'bg-blue-50 text-blue-800 border-blue-200': statusType === 'info'
                }">
                <svg x-show="statusType === 'success'" class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <svg x-show="statusType === 'warning'" class="w-5 h-5 text-yellow-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 0v2m0-2h2m-2 0H10"></path></svg>
                <svg x-show="statusType === 'info'" class="w-5 h-5 text-blue-600 shrink-0 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <svg x-show="statusType === 'error'" class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                
                <span class="font-medium text-sm" x-text="statusMsg"></span>
            </div>

            {{-- Grid Pilihan Model --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
                @foreach ($models as $key => $model)
                    <div class="relative group">
                        {{-- Input Radio Hidden --}}
                        <input type="radio" name="active_model" id="{{ $key }}" value="{{ $key }}"
                            class="hidden model-card-radio" {{ $activeModel == $key ? 'checked' : '' }}>

                        {{-- Label Card --}}
                        <label for="{{ $key }}"
                            class="block h-full p-6 bg-white rounded-2xl border-2 border-gray-200 hover:border-blue-300 transition-all duration-300 cursor-pointer relative flex flex-col shadow-sm hover:shadow-md">

                            {{-- Badge "Aktif" (Ping Animation) --}}
                            @if ($activeModel == $key)
                                <div class="absolute -top-3 -right-3 z-10">
                                    <span class="relative flex h-8 w-8">
                                        <span
                                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                        <span
                                            class="relative inline-flex rounded-full h-8 w-8 bg-gradient-to-br from-green-400 to-green-600 justify-center items-center text-white shadow-lg border-2 border-white">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    </span>
                                </div>
                            @endif

                            {{-- Header Card: Logo & Nama --}}
                            <div class="flex items-center gap-4 mb-4">
                                <div
                                    class="w-14 h-14 flex-shrink-0 flex items-center justify-center bg-gray-50 rounded-xl p-2 border border-gray-100 shadow-inner">
                                    @if (isset($model['logo']) && $model['logo'])
                                        <img src="{{ $model['logo'] }}" alt="{{ $model['name'] }}"
                                            class="w-full h-full object-contain drop-shadow-sm">
                                    @else
                                        {{-- Icon Default jika logo tidak ada --}}
                                        <svg class="w-8 h-8 text-[#002D72]" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5" />
                                        </svg>
                                    @endif
                                </div>
                                <div>
                                    <h3
                                        class="font-bold text-gray-900 text-lg leading-tight group-hover:text-[#002D72] transition-colors">
                                        {{ $model['name'] }}</h3>
                                    @if (isset($model['provider']))
                                        <span
                                            class="text-[10px] uppercase tracking-wider font-bold text-[#002D72] bg-blue-50 border border-blue-100 px-2.5 py-1 rounded-md mt-1.5 inline-block">
                                            {{ $model['provider'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Deskripsi --}}
                            <p class="text-sm text-gray-600 leading-relaxed mb-6 flex-grow">
                                {{ $model['description'] }}
                            </p>

                            {{-- Indikator Seleksi Bawah --}}
                            <div
                                class="pt-4 border-t border-gray-100 flex items-center text-sm font-medium text-gray-400 transition-colors mt-auto selection-text group-hover:text-[#002D72]">
                                {{-- Lingkaran Kosong (Tidak Terpilih) --}}
                                <svg class="w-6 h-6 mr-2 radio-unchecked" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{-- Lingkaran Centang (Terpilih) --}}
                                <svg class="w-6 h-6 mr-2 radio-checked hidden text-[#002D72]" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <span>Gunakan Model Ini</span>
                            </div>
                        </label>
                    </div>
                @endforeach
            </div>

            {{-- Tombol Simpan (Mobile / Muncul di bawah pada layar kecil) --}}
            <div class="mt-8 flex justify-end md:hidden">
                <button type="submit"
                    class="w-full flex justify-center items-center gap-2 bg-gradient-to-r from-[#002D72] to-[#004B9C] text-white px-6 py-3.5 rounded-xl font-bold transition-all shadow-lg hover:shadow-xl active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    Simpan Konfigurasi
                </button>
            </div>
        </div>
    </form>

</x-penanggungjawablayout>
