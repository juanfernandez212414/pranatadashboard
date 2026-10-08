{{-- resources/views/admin/dashboard.blade.php --}}

<x-adminlayout :title="$title">

    @push('styles')
        {{-- Hanya simpan CDN eksternal di sini --}}
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    @endpush

    <div id="dashboard-ajax-container">

        {{-- Header Halaman Dinamis --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-1">{{ $title }}</h1>
                @if ($selectedCategory ?? null)
                    <p class="text-gray-500">Menampilkan statistik yang relevan dengan kategori ini.</p>
                @else
                    <p class="text-gray-500">Menampilkan ringkasan statistik dari semua kategori.</p>
                @endif
            </div>
        </div>

        {{-- 1. Kotak Statistik Ringkasan --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            {{-- Total Kategori --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 shrink-0 rounded-xl bg-[#0B6FB8]/10 text-[#0B6FB8] flex items-center justify-center">
                    <i class="fas fa-th-large text-lg"></i>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500">Total Kategori</span>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($totalCategories ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
            {{-- Total Subjek --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 shrink-0 rounded-xl bg-[#4C9A2A]/10 text-[#4C9A2A] flex items-center justify-center">
                    <i class="fas fa-layer-group text-lg"></i>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500">Total Subjek</span>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($totalSubjects ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
            {{-- Total Indikator --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 shrink-0 rounded-xl bg-[#E8850C]/10 text-[#E8850C] flex items-center justify-center">
                    <i class="fas fa-chart-pie text-lg"></i>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500">Total Indikator</span>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($totalIndicators ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <hr class="my-8 border-gray-200">

        {{-- 2. FILTER SUBJEK & INDIKATOR --}}
        <div class="mb-6 bg-white rounded-2xl border border-gray-200 shadow-sm">
            <form action="{{ route('admin.dashboard') }}" method="GET" id="filterForm">
                @if ($selectedCategoryId ?? null)
                    <input type="hidden" name="category_id" value="{{ $selectedCategoryId }}">
                @endif
                <div class="p-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                        {{-- Dropdown Subjek --}}
                        <div>
                            <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-2">Filter
                                Subjek</label>

                            {{-- PERHATIAN: onchange="this.form.submit()" TELAH DIHAPUS DI SINI --}}
                            <select name="subject_id" id="subject_id"
                                class="block w-full bg-gray-50 border border-gray-300 text-sm rounded-xl focus:ring-[#002D72] focus:border-[#002D72] p-2.5 {{ !$selectedCategoryId ? 'cursor-not-allowed text-gray-400' : 'text-gray-900' }}"
                                {{ !$selectedCategoryId ? 'disabled' : '' }}>
                                <option value="">--
                                    {{ !$selectedCategoryId ? 'Pilih Kategori di Sidebar Dulu' : 'Tampilkan Semua Subjek' }}
                                    --</option>
                                @foreach ($subjectsForFilter ?? [] as $subject)
                                    <option value="{{ $subject->id }}"
                                        {{ $subject->id == $selectedSubjectId ? 'selected' : '' }}>
                                        {{ $subject->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Dropdown Indikator --}}
                        <div>
                            <label for="indicator_id" class="block text-sm font-medium text-gray-700 mb-2">Filter
                                Indikator</label>

                            {{-- PERHATIAN: onchange="this.form.submit()" TELAH DIHAPUS DI SINI --}}
                            <select name="indicator_id" id="indicator_id"
                                class="block w-full bg-gray-50 border border-gray-300 text-sm rounded-xl focus:ring-[#002D72] focus:border-[#002D72] p-2.5 {{ !$selectedSubjectId ? 'cursor-not-allowed text-gray-400' : 'text-gray-900' }}"
                                {{ !$selectedSubjectId ? 'disabled' : '' }}>
                                <option value="">--
                                    {{ !$selectedSubjectId ? 'Pilih Subjek Dulu' : 'Tampilkan Semua Indikator' }} --
                                </option>
                                @foreach ($indicatorsForFilter ?? [] as $indicator)
                                    <option value="{{ $indicator->id }}"
                                        {{ $indicator->id == $selectedIndicatorId ? 'selected' : '' }}>
                                        {{ $indicator->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Tombol Reset --}}
                        <div class="flex items-end">
                            <a href="{{ $selectedCategoryId ? route('admin.dashboard', ['category_id' => $selectedCategoryId]) : route('admin.dashboard') }}"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#002D72] to-[#003d8f] hover:from-[#003d8f] hover:to-[#0050b3] rounded-xl shadow-sm transition-all duration-200">
                                <i class="fas fa-undo text-xs"></i> Reset Filter
                            </a>
                        </div>

                    </div>
                </div>
            </form>
        </div>

        {{-- 3. GALERI VISUALISASI OTOMATIS --}}
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-chart-bar text-[#002D72]"></i> Galeri Indikator Otomatis
            </h2>
        </div>

        @if ($galatApiBps ?? null)
            <div class="bg-orange-50 border border-orange-200 text-orange-800 px-4 py-3 rounded-lg mb-4 text-sm" role="alert">
                @if ($dataApiKosong ?? false)
                    Data indikator ini belum bisa diambil dari WebAPI BPS. Coba lagi nanti, atau jalankan "Impor Semua Tabel Dinamis" di menu Data API BPS.
                @else
                    Data terbaru dari WebAPI BPS gagal diambil, jadi yang tampil adalah data terakhir yang tersimpan.
                @endif
                <span class="block text-xs mt-1">{{ $galatApiBps }}</span>
            </div>
        @endif

        <div class="flex flex-col gap-8">
            @forelse ($indicatorsWithVisualization as $vis)
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 md:p-6" id="vis-card-{{ $vis['id'] }}">

                    {{-- HEADER KARTU --}}
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-1">{{ $vis['name'] }}</h3>
                            <p class="text-md text-gray-500">Subjek: {{ $vis['subject'] }}</p>
                            @if (!empty($vis['sumber_bps']))
                                <p class="text-sm text-gray-500">
                                    Sumber: Tabel Dinamis WebAPI BPS{{ $vis['sumber_bps']['diperbarui'] ? ' · diambil ' . $vis['sumber_bps']['diperbarui'] . ' WIB' : '' }}{{ $vis['sumber_bps']['grafik'] ? ' · grafik yang disarankan BPS: ' . $vis['sumber_bps']['grafik'] : '' }}
                                </p>
                            @endif
                        </div>

                        {{-- TOMBOL KHUSUS ADMIN (Role 1) & PENANGGUNG JAWAB (Role 3) --}}
                        @if (auth()->check() && in_array(auth()->user()->role_id, [1, 3]))
                            <button onclick="toggleEditor({{ $vis['id'] }})"
                                class="flex items-center gap-2 bg-gradient-to-r from-[#002D72] to-[#003d8f] hover:from-[#003d8f] hover:to-[#0050b3] text-white px-5 py-2.5 rounded-xl shadow-sm transition-all duration-200 font-semibold text-sm focus:ring-2 focus:ring-offset-2 focus:ring-[#002D72]">
                                <i class="fas fa-edit"></i>
                                <span class="hidden md:inline">Kelola Narasi</span>
                            </button>
                        @endif
                    </div>

                    {{-- AREA NARASI --}}
                    {{-- VIEW MODE --}}
                    <div id="narrative-view-{{ $vis['id'] }}"
                        class="mb-6 {{ empty($vis['narrative']) ? 'hidden' : '' }}">
                        <div class="bg-slate-50 border border-gray-200 border-l-4 border-l-[#002D72] rounded-xl p-6">
                            <h4 class="text-lg font-bold text-[#002D72] mb-3 flex items-center gap-2">
                                <i class="fas fa-align-left"></i> Analisis Data
                            </h4>
                            <div class="max-w-none text-gray-700 text-[15px] leading-relaxed whitespace-pre-line"
                                id="narrative-text-{{ $vis['id'] }}">{{ $vis['narrative'] }}</div>
                        </div>
                    </div>

                    {{-- EDIT MODE --}}
                    @if (auth()->check() && in_array(auth()->user()->role_id, [1, 3]))
                        <div id="narrative-editor-container-{{ $vis['id'] }}" class="hidden mb-6">
                            <div class="bg-white border border-gray-200 rounded-xl p-4 md:p-5 shadow-sm">
                                <h4 class="text-md font-semibold text-gray-800 mb-3">Editor Narasi
                                </h4>
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-3 gap-2">
                                    <button onclick="generateAiNarrative({{ $vis['id'] }})"
                                        id="btn-generate-{{ $vis['id'] }}"
                                        class="text-sm bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl shadow-sm transition-colors duration-200 font-semibold flex items-center justify-center gap-2 focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                                        <i class="fas fa-robot"></i> Generate Draft dengan AI
                                    </button>
                                    <span id="loading-msg-{{ $vis['id'] }}"
                                        class="hidden text-sm font-medium text-emerald-700 animate-pulse">
                                        <i class="fas fa-spinner fa-spin"></i> Sedang membuat narasi...
                                    </span>
                                </div>
                                <textarea id="editor-{{ $vis['id'] }}" rows="8"
                                    class="w-full p-3 bg-gray-50 border border-gray-300 rounded-xl text-sm leading-relaxed text-gray-800 focus:ring-2 focus:ring-[#002D72]/20 focus:border-[#002D72] focus:bg-white outline-none mb-3"
                                    placeholder="Klik 'Generate Draft' atau tulis analisis Anda di sini...">{{ $vis['narrative'] }}</textarea>
                                <div class="flex justify-end gap-2">
                                    <button onclick="toggleEditor({{ $vis['id'] }})"
                                        class="px-5 py-2.5 text-sm text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 rounded-xl font-semibold transition-colors duration-200">Batal</button>
                                    <button onclick="saveNarrative({{ $vis['id'] }})"
                                        id="btn-save-{{ $vis['id'] }}"
                                        class="px-5 py-2.5 text-sm text-white bg-gradient-to-r from-[#002D72] to-[#003d8f] hover:from-[#003d8f] hover:to-[#0050b3] rounded-xl shadow-sm transition-all duration-200 font-semibold flex items-center gap-2 focus:ring-2 focus:ring-offset-2 focus:ring-[#002D72]">
                                        <i class="fas fa-save"></i> Simpan & Terbitkan
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Area Filter Internal --}}
                    @if (!empty($vis['filters']))
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                            @foreach ($vis['filters'] as $columnName => $filter)
                                <div>
                                    <label for="filter-{{ $vis['id'] }}-{{ $loop->index }}"
                                        class="block text-sm font-medium text-gray-700 capitalize">
                                        {{ $columnName }}
                                    </label>
                                    <select id="filter-{{ $vis['id'] }}-{{ $loop->index }}"
                                        name="{{ $columnName }}"
                                        class="auto-filter-select mt-1 block w-full bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#002D72] focus:border-[#002D72] p-2"
                                        onchange="window.dashboardApp.updateVisualization({{ $vis['id'] }})">
                                        <option value="">Semua</option>
                                        @foreach ($filter['options'] as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- LAYOUT GRID VISUALISASI --}}
                    <div class="vis-layout-container" id="vis-placeholder-{{ $vis['id'] }}"></div>
                </div>
            @empty
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-12 text-center">
                    @if (!$selectedCategoryId)
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-[#002D72]/[0.06] text-[#002D72] flex items-center justify-center">
                            <i class="fas fa-folder-open text-2xl"></i></div>
                        <h3 class="text-xl font-bold text-gray-900">Pilih Kategori Data</h3>
                        <p class="mt-2 text-gray-500 max-w-md mx-auto">Silakan pilih <b>Kategori</b> pada menu sidebar
                            di
                            sebelah kiri untuk memulai.</p>
                    @elseif (!$selectedSubjectId)
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-[#002D72]/[0.06] text-[#002D72] flex items-center justify-center">
                            <i class="fas fa-layer-group text-2xl"></i></div>
                        <h3 class="text-xl font-bold text-gray-900">Pilih Subjek Statistik</h3>
                        <p class="mt-2 text-gray-500 max-w-md mx-auto">Kategori sudah terpilih. Silakan pilih
                            <b>Subjek</b>
                            dari dropdown filter di atas.
                        </p>
                    @elseif (!$selectedIndicatorId)
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-[#002D72]/[0.06] text-[#002D72] flex items-center justify-center">
                            <i class="fas fa-hand-pointer text-2xl"></i></div>
                        <h3 class="text-xl font-bold text-gray-900">Pilih Indikator Spesifik</h3>
                        <p class="mt-2 text-gray-500 max-w-md mx-auto">Subjek sudah terpilih. Sekarang silakan pilih
                            spesifik <b>Indikator</b> dari dropdown di atas untuk menampilkan grafik dan mulai
                            menggunakan
                            AI.</p>
                    @else
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center">
                            <i class="fas fa-chart-bar text-2xl"></i></div>
                        <h3 class="text-lg font-bold text-gray-900">Visualisasi Tidak Tersedia</h3>
                        <p class="mt-1 text-gray-500">{{ ($dataApiKosong ?? false) ? 'Data indikator ini belum tersedia dari WebAPI BPS.' : 'Format tabel data pada indikator ini belum mendukung untuk dibuatkan grafik secara otomatis.' }}</p>
                    @endif
                </div>
            @endforelse
        </div>

    </div>
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        {{-- Jembatan Konfigurasi untuk admin.js --}}
        <script>
            window.DashboardConfig = {
                visualizations: @json($indicatorsWithVisualization ?? []),
                csrfToken: "{{ csrf_token() }}",
                routes: {
                    generateNarrative: "{{ route('admin.dashboard.generateNarrative') }}",
                    saveNarrative: "{{ route('admin.dashboard.saveNarrative') }}",
                    getFilteredData: "{{ route('admin.dashboard.getFilteredData') }}"
                }
            };
        </script>
    @endpush

</x-adminlayout>
