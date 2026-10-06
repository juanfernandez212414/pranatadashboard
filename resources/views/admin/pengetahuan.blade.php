{{-- resources/views/admin/pengetahuan.blade.php --}}
<x-adminlayout title="Manajemen Pengetahuan - PRANATA">
    <div x-data="{ showUploadModal: false, showIngestModal: false, showDeleteModal: false, showDeleteAllModal: false, selectedDeleteFile: '', pesanGagalUnggah: '', batasUnggah: @js($batasUnggah) }" class="space-y-6 relative">

        {{-- ========================================== --}}
        {{-- TOAST NOTIFICATION (TENGAH, ELEGAN & RAPI) --}}
        {{-- ========================================== --}}

        {{-- POP-UP KHUSUS INGEST RESULT (Visual Progress) --}}
        @if (session('ingest_result'))
            @php $ir = json_decode(session('ingest_result'), true); @endphp
            <div x-data="{ showToast: true }" x-show="showToast"
                class="fixed inset-0 z-[150] flex items-center justify-center p-4">

                <div x-show="showToast" @click="showToast = false" x-transition.opacity.duration.300ms
                    class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm cursor-pointer"></div>

                <div x-show="showToast" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-90 translate-y-4"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-90 translate-y-4"
                    class="relative w-full max-w-md transform overflow-hidden rounded-3xl bg-white p-8 text-center shadow-2xl border border-gray-100 flex flex-col items-center z-50">

                    {{-- Ikon Status --}}
                    @if ($ir['status'] === 'complete')
                        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-50 text-green-500 ring-4 ring-green-100/50">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    @elseif ($ir['status'] === 'progress')
                        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-50 text-blue-500 ring-4 ring-blue-100/50">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                        </div>
                    @else
                        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-red-500 ring-4 ring-red-100/50">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                    @endif

                    {{-- Judul --}}
                    <h3 class="text-xl font-bold text-gray-900 mb-1">{{ $ir['pesan'] }}</h3>

                    {{-- Progress Bar (hanya jika total > 0) --}}
                    @if (($ir['total'] ?? 0) > 0)
                        @php
                            $persen = ($ir['total'] > 0) ? round(($ir['selesai'] / $ir['total']) * 100) : 0;
                            $barColor = match($ir['status']) {
                                'complete' => 'bg-green-500',
                                'progress' => 'bg-blue-500',
                                default => 'bg-red-400',
                            };
                        @endphp
                        <div class="w-full mt-4 mb-1">
                            <div class="flex justify-between text-xs text-gray-500 mb-1.5 font-medium">
                                <span>Progres Penyimpanan</span>
                                <span class="font-bold text-gray-700">{{ $persen }}%</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                                <div class="{{ $barColor }} h-3 rounded-full transition-all duration-700 ease-out" style="width: {{ $persen }}%"></div>
                            </div>
                        </div>

                        {{-- Statistik Grid --}}
                        <div class="flex flex-row w-full justify-between items-stretch gap-2 mt-4 mb-3">
                            <div class="flex-1 w-1/3 bg-gray-50 rounded-xl px-2 py-2.5 flex flex-col justify-center items-center">
                                <div class="text-lg font-bold text-gray-800">{{ $ir['total'] }}</div>
                                <div class="text-[10px] text-gray-400 uppercase tracking-wide font-semibold mt-0.5">Total</div>
                            </div>
                            <div class="flex-1 w-1/3 rounded-xl px-2 py-2.5 flex flex-col justify-center items-center {{ $ir['status'] === 'complete' ? 'bg-green-50' : 'bg-blue-50' }}">
                                <div class="text-lg font-bold {{ $ir['status'] === 'complete' ? 'text-green-600' : 'text-blue-600' }}">{{ $ir['selesai'] }}</div>
                                <div class="text-[10px] uppercase tracking-wide font-semibold mt-0.5 {{ $ir['status'] === 'complete' ? 'text-green-400' : 'text-blue-400' }}">Selesai</div>
                            </div>
                            <div class="flex-1 w-1/3 rounded-xl px-2 py-2.5 flex flex-col justify-center items-center {{ $ir['sisa'] > 0 ? 'bg-amber-50' : 'bg-gray-50' }}">
                                <div class="text-lg font-bold {{ $ir['sisa'] > 0 ? 'text-amber-600' : 'text-gray-400' }}">{{ $ir['sisa'] }}</div>
                                <div class="text-[10px] uppercase tracking-wide font-semibold mt-0.5 {{ $ir['sisa'] > 0 ? 'text-amber-400' : 'text-gray-400' }}">Antrian</div>
                            </div>
                        </div>

                        {{-- Info Baru Diproses --}}
                        @if (($ir['baru_diproses'] ?? 0) > 0)
                            <div class="w-full bg-green-50 border border-green-100 rounded-xl px-4 py-2.5 flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                <span class="text-xs text-green-700 font-medium">{{ $ir['baru_diproses'] }} dokumen baru berhasil diproses sesi ini</span>
                            </div>
                        @endif

                        {{-- File Gagal --}}
                        @if (!empty($ir['gagal_files']))
                            <div class="w-full bg-red-50 border border-red-100 rounded-xl px-4 py-2.5 flex items-start gap-2 mb-2">
                                <svg class="w-4 h-4 text-red-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" />
                                </svg>
                                <span class="text-xs text-red-600 text-left font-medium">Gagal: {{ implode(', ', $ir['gagal_files']) }}</span>
                            </div>
                        @endif
                    @endif

                    {{-- Detail Pesan --}}
                    <p class="text-sm text-gray-500 leading-relaxed mt-2 mb-5 px-2 w-full">{{ $ir['detail'] }}</p>

                    {{-- Tombol --}}
                    @if ($ir['status'] === 'progress' && ($ir['sisa'] ?? 0) > 0)
                        <div class="w-full flex gap-2">
                            <button @click="showToast = false" type="button"
                                class="flex-1 inline-flex justify-center rounded-xl px-5 py-3 text-sm font-bold shadow-sm hover:opacity-90 transition-all cursor-pointer bg-gray-100 text-gray-600 hover:bg-gray-200">
                                Tutup
                            </button>
                            <form action="{{ route('admin.pengetahuan.ingest') }}" method="POST" class="flex-1 m-0">
                                @csrf
                                <button type="submit"
                                    class="w-full inline-flex justify-center rounded-xl px-5 py-3 text-sm font-bold shadow-sm hover:opacity-90 transition-all cursor-pointer"
                                    style="background-color: #002D72; color: #ffffff; border: none;">
                                    Lanjutkan Ingest
                                </button>
                            </form>
                        </div>
                    @else
                        @php
                            $btnColor = match($ir['status']) {
                                'complete' => '#22c55e',
                                'progress' => '#3b82f6',
                                default => '#ef4444',
                            };
                        @endphp
                        <button @click="showToast = false" type="button"
                            class="w-full inline-flex justify-center rounded-xl px-5 py-3 text-sm font-bold shadow-sm hover:opacity-90 transition-all cursor-pointer relative z-50"
                            style="background-color: {{ $btnColor }}; color: #ffffff; border: none;">
                            OK
                        </button>
                    @endif
                </div>
            </div>
        @endif

        {{-- POP-UP SUCCESS/ERROR GENERIK (untuk upload, delete, dll) --}}
        @if (session('success') || session('error') || $errors->any())
            <div x-data="{ showToast: true }" x-show="showToast"
                class="fixed inset-0 z-[150] flex items-center justify-center p-4">

                <div x-show="showToast" @click="showToast = false" x-transition.opacity.duration.300ms
                    class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm cursor-pointer"></div>

                @if (session('success'))
                    <div x-show="showToast" x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 scale-90 translate-y-4"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                        x-transition:leave-end="opacity-0 scale-90 translate-y-4"
                        class="relative w-full max-w-sm transform overflow-hidden rounded-3xl bg-white p-8 text-center shadow-2xl border border-gray-100 flex flex-col items-center z-50">

                        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-50 text-green-500 ring-4 ring-green-50/50">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-1">Berhasil!</h3>
                        <p class="text-sm text-gray-500 leading-relaxed mb-6 whitespace-normal px-2 w-full" style="overflow-wrap: anywhere;">{{ session('success') }}</p>

                        <button @click="showToast = false" type="button"
                            class="w-full inline-flex justify-center rounded-xl px-5 py-3 text-sm font-bold shadow-sm hover:opacity-90 transition-all cursor-pointer relative z-50"
                            style="background-color: #22c55e; color: #ffffff; border: none;">
                            OK
                        </button>
                    </div>
                @elseif(session('error') || $errors->any())
                    <div x-show="showToast" x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 scale-90 translate-y-4"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                        x-transition:leave-end="opacity-0 scale-90 translate-y-4"
                        class="relative w-full max-w-sm transform overflow-hidden rounded-3xl bg-white p-8 text-center shadow-2xl border border-gray-100 flex flex-col items-center z-50">

                        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-red-500 ring-4 ring-red-50/50">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-1">Gagal!</h3>
                        <p class="text-sm text-gray-500 leading-relaxed mb-6 whitespace-normal px-2 w-full" style="overflow-wrap: anywhere;">{{ session('error') ?? $errors->first() }}</p>

                        <button @click="showToast = false" type="button"
                            class="w-full inline-flex justify-center rounded-xl px-5 py-3 text-sm font-bold shadow-sm hover:opacity-90 transition-all cursor-pointer relative z-50"
                            style="background-color: #ef4444; color: #ffffff; border: none;">
                            OK
                        </button>
                    </div>
                @endif
            </div>
        @endif

        {{-- POP-UP GAGAL DARI PEMERIKSAAN BERKAS DI BROWSER (sebelum form unggah dikirim) --}}
        <div x-show="pesanGagalUnggah" style="display: none;"
            class="fixed inset-0 z-[150] flex items-center justify-center p-4">
            <div x-show="pesanGagalUnggah" @click="pesanGagalUnggah = ''" x-transition.opacity.duration.300ms
                class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm cursor-pointer"></div>

            <div x-show="pesanGagalUnggah" x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-90 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-90 translate-y-4"
                class="relative w-full max-w-sm transform overflow-hidden rounded-3xl bg-white p-8 text-center shadow-2xl border border-gray-100 flex flex-col items-center z-50">

                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-red-500 ring-4 ring-red-50/50">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-1">Gagal!</h3>
                <p class="text-sm text-gray-500 leading-relaxed mb-6 whitespace-normal px-2 w-full" style="overflow-wrap: anywhere;" x-text="pesanGagalUnggah"></p>

                <button @click="pesanGagalUnggah = ''" type="button"
                    class="w-full inline-flex justify-center rounded-xl px-5 py-3 text-sm font-bold shadow-sm hover:opacity-90 transition-all cursor-pointer relative z-50"
                    style="background-color: #ef4444; color: #ffffff; border: none;">
                    OK
                </button>
            </div>
        </div>

        {{-- Header Judul --}}
        <div>
            <h2 class="text-2xl font-bold text-[#002D72]">Manajemen Pengetahuan AI</h2>
            <p class="text-gray-600 mt-1 text-sm">Kelola sinkronisasi publikasi BPS dan proses ekstraksi pengetahuan
                untuk AI PRANATA.</p>
        </div>

        {{-- Grid Konten Utama --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Komponen 1: Upload Publikasi --}}
            <div
                class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 flex flex-col h-full hover:shadow-lg transition-all duration-300 group">
                <div class="flex items-center gap-4 mb-5">
                    <div
                        class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-[#002D72] group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">1. Unggah Publikasi</h3>
                        <p class="text-sm text-gray-500 font-medium mt-0.5">Upload PDF Manual</p>
                    </div>
                </div>
                <p class="text-gray-600 flex-grow mb-8 leading-relaxed">
                    Unggah dokumen publikasi BPS (berformat PDF) secara manual dari perangkat Anda ke dalam server. Anda
                    dapat memilih beberapa file sekaligus.
                </p>

                <button type="button" @click="showUploadModal = true"
                    class="w-full bg-[#002D72] hover:bg-[#001d4a] text-white font-bold py-3.5 px-4 rounded-xl transition-all duration-300 flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Pilih & Unggah Dokumen
                </button>
            </div>

            {{-- Komponen 2: Penyimpanan Pengetahuan --}}
            <div
                class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 flex flex-col h-full hover:shadow-lg transition-all duration-300 group">
                <div class="flex items-center gap-4 mb-5">
                    <div
                        class="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center text-green-600 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">2. Perbaharui Pengetahuan</h3>
                        <p class="text-sm text-gray-500 font-medium mt-0.5">Pembentukan Vektor & Penyimpanan Pengetahuan</p>
                    </div>
                </div>
                <p class="text-gray-600 flex-grow mb-8 leading-relaxed">
                    Sistem akan mengekstrak teks dari publikasi yang telah diunduh, memecahnya, mengubahnya menjadi
                    vektor (BGE-M3), dan menyimpannya ke dalam database Qdrant.
                </p>

                <button type="button" @click="showIngestModal = true"
                    class="w-full bg-[#002D72] hover:bg-[#001d4a] text-white font-bold py-3.5 px-4 rounded-xl transition-all duration-300 flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4">
                        </path>
                    </svg>
                    Mulai Penyimpanan Pengetahuan
                </button>
            </div>

        </div>

        {{-- ============================================== --}}
        {{-- Komponen 3: Hapus Pengetahuan Berdasarkan File --}}
        {{-- ============================================== --}}
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 hover:shadow-lg transition-all duration-300 group">
            <div class="flex items-center gap-4 mb-5">
                <div
                    class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-red-500 group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                        </path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">3. Hapus Pengetahuan</h3>
                    <p class="text-sm text-gray-500 font-medium mt-0.5">Hapus Berdasarkan File yang Sudah Di-ingest</p>
                </div>
            </div>
            <p class="text-gray-600 mb-6 leading-relaxed">
                Pilih salah satu file dari daftar dokumen yang telah berhasil di-ingest ke database Qdrant, kemudian hapus
                seluruh vektor pengetahuannya. Gunakan fitur ini jika dokumen sudah tidak relevan atau perlu diperbarui.
            </p>

            @php
                $logPath = storage_path('app/processed_log_bge_m3.txt');
                $ingestedFiles = [];
                if (file_exists($logPath)) {
                    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $ingestedFiles = array_values(array_unique(array_map('trim', $lines)));
                    sort($ingestedFiles);
                }
            @endphp

            @if (count($ingestedFiles) === 0)
                <div class="flex items-center gap-3 bg-gray-50 border border-dashed border-gray-200 rounded-2xl p-5 text-sm text-gray-400">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Belum ada dokumen yang ter-ingest ke database Qdrant.</span>
                </div>
            @else
                {{-- Alpine.js: Pencarian + Tabel 10 baris scrollable --}}
                <div
                    x-data="{
                        search: '',
                        allFiles: @js($ingestedFiles),
                        get filtered() {
                            if (!this.search.trim()) return this.allFiles;
                            const q = this.search.toLowerCase();
                            return this.allFiles.filter(f => f.toLowerCase().includes(q));
                        }
                    }">

                    {{-- Search Bar & Hapus Semua --}}
                    <div class="mb-3 flex flex-col sm:flex-row gap-3 items-center w-full">
                        <div class="relative w-full">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-4.35-4.35M17 11A6 6 0 1 0 5 11a6 6 0 0 0 12 0z" />
                                </svg>
                            </div>
                            <input
                                type="text"
                                x-model="search"
                                placeholder="Cari nama file..."
                                class="w-full pl-10 pr-10 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-[#002D72] focus:ring-2 focus:ring-[#002D72]/10 outline-none transition-all placeholder-gray-400 text-gray-700"
                            >
                            {{-- Clear button --}}
                            <button
                                type="button"
                                x-show="search.length > 0"
                                @click="search = ''"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <button type="button" @click="showDeleteAllModal = true" class="w-full sm:w-auto shrink-0 inline-flex items-center justify-center gap-2 bg-red-50 text-red-600 hover:bg-red-100 border border-red-100 hover:border-red-200 font-semibold text-sm px-4 py-2.5 rounded-xl transition-all duration-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Hapus Semua
                        </button>
                    </div>

                    {{-- Info jumlah hasil --}}
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs text-gray-400">
                            Menampilkan <span class="font-semibold text-gray-600" x-text="filtered.length"></span>
                            dari <span class="font-semibold text-gray-600">{{ count($ingestedFiles) }}</span> dokumen ter-ingest
                        </p>
                        <p class="text-xs text-gray-400 italic" x-show="filtered.length === 0 && search.length > 0">
                            Tidak ada file yang cocok.
                        </p>
                    </div>

                    {{-- Tabel dengan scroll, max 10 baris (tinggi ~440px) --}}
                    <div class="overflow-hidden rounded-2xl border border-gray-100">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th scope="col" class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-10">#</th>
                                    <th scope="col" class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama File</th>
                                    <th scope="col" class="px-5 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                        </table>
                        {{-- Scrollable tbody wrapper — max 10 rows (~440px), custom scrollbar --}}
                        <div class="overflow-y-auto" style="max-height: 220px; scrollbar-width: thin; scrollbar-color: #e5e7eb transparent;">
                            <table class="min-w-full">
                                <tbody class="bg-white divide-y divide-gray-50">
                                    <template x-for="(filename, idx) in filtered" :key="filename">
                                        <tr class="hover:bg-red-50/40 transition-colors duration-150">
                                            <td class="px-5 py-3.5 text-sm text-gray-400 w-10" x-text="idx + 1"></td>
                                            <td class="px-5 py-3.5">
                                                <div class="flex items-center gap-2.5">
                                                    <svg class="w-4 h-4 text-red-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span class="text-sm font-medium text-gray-700 break-all" x-text="filename"></span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3.5 text-right">
                                                <button type="button"
                                                    @click="selectedDeleteFile = filename; showDeleteModal = true"
                                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 border border-red-100 hover:border-red-200 rounded-lg px-3 py-1.5 transition-all duration-200">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                    {{-- Empty state saat hasil pencarian kosong --}}
                                    <tr x-show="filtered.length === 0">
                                        <td colspan="3" class="px-5 py-8 text-center text-sm text-gray-400">
                                            <div class="flex flex-col items-center gap-2">
                                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                        d="M21 21l-4.35-4.35M17 11A6 6 0 1 0 5 11a6 6 0 0 0 12 0z" />
                                                </svg>
                                                <span>Tidak ada file yang cocok dengan pencarian.</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Kotak Catatan --}}
        <div class="mt-6 bg-blue-50/80 border border-blue-100/50 rounded-2xl p-6 flex gap-4 items-start shadow-sm">
            <svg class="w-6 h-6 text-[#002D72] shrink-0 mt-0.5" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm text-gray-700 leading-relaxed">
                <strong class="text-[#002D72]">Catatan Alur:</strong> Pastikan Anda menjalankan <b
                    class="font-semibold">Upload Publikasi</b> terlebih dahulu. Setelah proses upload selesai, baru
                jalankan <b class="font-semibold">Penyimpanan Pengetahuan</b> agar AI memiliki data vektor yang paling
                mutakhir.
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- MODAL UPLOAD DOKUMEN                       --}}
        {{-- ========================================== --}}
        <div x-show="showUploadModal" style="display: none;" class="fixed inset-0 z-[100] overflow-y-auto"
            aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div x-show="showUploadModal" x-transition.opacity.duration.300ms
                class="fixed inset-0 bg-gray-900/40 backdrop-blur-md transition-opacity"></div>
            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <div x-show="showUploadModal" @click.away="showUploadModal = false"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                    class="relative transform overflow-hidden rounded-[2rem] bg-white p-8 text-left shadow-[0_20px_50px_rgba(0,0,0,0.2)] transition-all w-full max-w-md border border-gray-100">

                    <div class="flex flex-col items-center text-center">
                        <div
                            class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-blue-50 text-[#002D72] ring-8 ring-blue-50/50">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900" id="modal-title">Unggah File PDF</h3>
                        <p class="mt-3 text-gray-500 leading-relaxed text-sm">Pilih satu atau beberapa dokumen PDF
                            publikasi BPS dari perangkat Anda.</p>
                    </div>

                    <form action="{{ route('admin.pengetahuan.upload') }}" method="POST"
                        enctype="multipart/form-data" class="mt-6"
                        @submit="pesanGagalUnggah = periksaBerkasUnggahan($refs.berkasUnggahan, batasUnggah); pesanGagalUnggah && $event.preventDefault()">
                        @csrf

                        {{-- Area Input File Custom Tailwind --}}
                        <div class="mb-6">
                            <input type="file" name="dokumen[]" multiple accept=".pdf" required x-ref="berkasUnggahan"
                                class="block w-full text-sm text-gray-500 
                                          file:mr-4 file:py-3 file:px-4 file:rounded-xl 
                                          file:border-0 file:text-sm file:font-bold 
                                          file:bg-blue-50 file:text-[#002D72] 
                                          hover:file:bg-blue-100 cursor-pointer border border-gray-200 rounded-xl">
                            <p class="mt-2 text-xs text-gray-400">Anda dapat memblok/memilih banyak file sekaligus.</p>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                            <button type="button" @click="showUploadModal = false"
                                class="w-full justify-center rounded-xl bg-gray-50 px-5 py-3.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-all sm:w-1/2">Batal</button>
                            <button type="submit" @click="showUploadModal = false"
                                class="w-full justify-center rounded-xl bg-[#002D72] px-5 py-3.5 text-sm font-semibold text-white shadow-md hover:bg-[#001d4a] hover:shadow-lg transition-all sm:w-1/2">Mulai
                                Unggah</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- MODAL KONFIRMASI PENYIMPANAN (CENTER, ELEGAN)   --}}
        {{-- ========================================== --}}
        <div x-show="showIngestModal" style="display: none;" class="fixed inset-0 z-[100] overflow-y-auto"
            aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div x-show="showIngestModal" x-transition.opacity.duration.300ms
                class="fixed inset-0 bg-gray-900/40 backdrop-blur-md transition-opacity"></div>
            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <div x-show="showIngestModal" @click.away="showIngestModal = false"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                    class="relative transform overflow-hidden rounded-[2rem] bg-white p-8 text-left shadow-[0_20px_50px_rgba(0,0,0,0.2)] transition-all w-full max-w-md border border-gray-100">

                    <div class="flex flex-col items-center text-center">
                        <div
                            class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-green-50 text-green-600 ring-8 ring-green-50/50">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900" id="modal-title">Konfirmasi Penyimpanan</h3>
                        <p class="mt-3 text-gray-500 leading-relaxed">Apakah Anda yakin ingin mengekstrak pengetahuan
                            ke database vektor? AI mungkin tidak bisa menjawab maksimal selama proses ini.</p>
                    </div>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                        <button type="button" @click="showIngestModal = false"
                            class="w-full justify-center rounded-xl bg-gray-50 px-5 py-3.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-all sm:w-1/2">Batal</button>
                        <form action="{{ route('admin.pengetahuan.ingest') }}" method="POST"
                            class="w-full sm:w-1/2 m-0">
                            @csrf
                            <button type="submit" @click="showIngestModal = false"
                                class="w-full justify-center rounded-xl bg-green-600 px-5 py-3.5 text-sm font-semibold text-white shadow-md hover:bg-green-700 hover:shadow-lg transition-all">Ya,
                                Ekstrak</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- MODAL KONFIRMASI HAPUS PENGETAHUAN          --}}
        {{-- ========================================== --}}
        <div x-show="showDeleteModal" style="display: none;" class="fixed inset-0 z-[100] overflow-y-auto"
            aria-labelledby="modal-delete-title" role="dialog" aria-modal="true">
            <div x-show="showDeleteModal" x-transition.opacity.duration.300ms
                class="fixed inset-0 bg-gray-900/40 backdrop-blur-md transition-opacity"></div>
            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <div x-show="showDeleteModal" @click.away="showDeleteModal = false"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                    class="relative transform overflow-hidden rounded-[2rem] bg-white p-8 text-left shadow-[0_20px_50px_rgba(0,0,0,0.2)] transition-all w-full max-w-md border border-gray-100">

                    <div class="flex flex-col items-center text-center">
                        <div
                            class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-red-50 text-red-500 ring-8 ring-red-50/50">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900" id="modal-delete-title">Konfirmasi Penghapusan</h3>
                        <p class="mt-3 text-gray-500 leading-relaxed text-sm">Anda akan menghapus seluruh vektor pengetahuan untuk file:</p>
                        <div class="mt-3 w-full bg-red-50 border border-red-100 rounded-xl px-4 py-3 flex items-start gap-2 text-left">
                            <svg class="w-4 h-4 text-red-400 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-sm font-semibold text-red-700 break-all" x-text="selectedDeleteFile"></span>
                        </div>
                        <p class="mt-3 text-xs text-gray-400 leading-relaxed">Tindakan ini akan menghapus semua chunk vektor terkait dari Qdrant dan menghapus entri dari log. Proses ini <strong class="text-red-500">tidak dapat dibatalkan</strong>.</p>
                    </div>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                        <button type="button" @click="showDeleteModal = false"
                            class="w-full justify-center rounded-xl bg-gray-50 px-5 py-3.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-all sm:w-1/2">Batal</button>
                        <form action="{{ url('/admin/pengetahuan/delete-by-file') }}" method="POST"
                            class="w-full sm:w-1/2 m-0">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="filename" :value="selectedDeleteFile">
                            <button type="submit" @click="showDeleteModal = false"
                                class="w-full justify-center rounded-xl bg-red-500 px-5 py-3.5 text-sm font-semibold text-white shadow-md hover:bg-red-600 hover:shadow-lg transition-all">Ya,
                                Hapus</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- MODAL KONFIRMASI HAPUS SEMUA PENGETAHUAN    --}}
        {{-- ========================================== --}}
        <div x-show="showDeleteAllModal" style="display: none;" class="fixed inset-0 z-[100] overflow-y-auto"
            aria-labelledby="modal-delete-all-title" role="dialog" aria-modal="true">
            <div x-show="showDeleteAllModal" x-transition.opacity.duration.300ms
                class="fixed inset-0 bg-gray-900/40 backdrop-blur-md transition-opacity"></div>
            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <div x-show="showDeleteAllModal" @click.away="showDeleteAllModal = false"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                    class="relative transform overflow-hidden rounded-[2rem] bg-white p-8 text-left shadow-[0_20px_50px_rgba(0,0,0,0.2)] transition-all w-full max-w-md border border-gray-100">

                    <div class="flex flex-col items-center text-center">
                        <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-red-50 text-red-500 ring-8 ring-red-50/50">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900" id="modal-delete-all-title">Hapus Semua Pengetahuan?</h3>
                        <p class="mt-3 text-gray-500 leading-relaxed text-sm">Anda akan menghapus <strong>seluruh dokumen</strong> yang sudah di-ingest dari database Qdrant.</p>
                        <p class="mt-3 text-xs text-red-500 font-semibold leading-relaxed">Tindakan ini sangat berbahaya dan tidak dapat dibatalkan!</p>
                    </div>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                        <button type="button" @click="showDeleteAllModal = false"
                            class="w-full justify-center rounded-xl bg-gray-50 px-5 py-3.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-all sm:w-1/2">Batal</button>
                        <form action="{{ route('admin.pengetahuan.delete-all') }}" method="POST" class="w-full sm:w-1/2 m-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" @click="showDeleteAllModal = false"
                                class="w-full justify-center rounded-xl bg-red-600 px-5 py-3.5 text-sm font-semibold text-white shadow-md hover:bg-red-700 hover:shadow-lg transition-all">Ya, Hapus Semua</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
    @push('scripts')
        <script>
            // Periksa berkas unggahan di browser sebelum form dikirim. Berkas yang melebihi batas server
            // ditolak nginx/PHP sebelum sampai ke Laravel, sehingga yang tampil halaman "413" polos, bukan
            // pop-up. Batasnya dari PengetahuanController::batasUnggah(); validasi di server tetap berlaku.
            function periksaBerkasUnggahan(input, batas) {
                const berkas = Array.from(input.files);
                const mb = (byte) => (byte / 1048576).toLocaleString('id-ID', { maximumFractionDigits: 1 });

                if (berkas.some((f) => !f.name.toLowerCase().endsWith('.pdf'))) {
                    return 'Semua file yang diunggah harus berformat PDF.';
                }

                const terlaluBesar = berkas.filter((f) => f.size > batas.per_file);
                if (terlaluBesar.length) {
                    return `Ukuran maksimal setiap file adalah ${mb(batas.per_file)}MB. File terlalu besar: `
                        + terlaluBesar.map((f) => `${f.name} (${mb(f.size)} MB)`).join(', ') + '.';
                }

                const total = berkas.reduce((jumlah, f) => jumlah + f.size, 0);
                if (total > batas.total) {
                    return `Total ukuran file yang dipilih ${mb(total)} MB, melebihi batas sekali unggah ${mb(batas.total)} MB. Unggah file secara bertahap.`;
                }

                return '';
            }
        </script>
    @endpush
</x-adminlayout>
