<x-penanggungjawablayout title="Kelola Data - penanggungjawab PRANATA">
    <div x-data="kelolaData()" x-init="
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('search') || urlParams.has('category_id') || urlParams.has('subject_id')) {
            activeTab = 'indicators';
            localStorage.setItem('pranata_tab', 'indicators');
        } else if (localStorage.getItem('pranata_tab')) {
            activeTab = localStorage.getItem('pranata_tab');
        }
        $watch('activeTab', value => localStorage.setItem('pranata_tab', value));
    ">

        <div class="space-y-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1">Kelola Data</h1>
                <p class="text-sm md:text-base text-gray-500">Kelola kategori, subjek, dan indikator data statistik</p>
            </div>

            @if (session('success'))
                <div class="bg-green-100 border border-green-300 text-green-700 px-4 py-3 rounded-lg" role="alert">
                    <span class="font-medium">{{ session('success') }}</span>
                </div>
            @endif
            @if ($errors->any())
                <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-lg" role="alert">
                    <span class="font-bold">Error Validasi!</span>
                    <ul class="mt-2 list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div
                class="flex gap-2 border-b border-gray-200 bg-white rounded-t-xl px-2 md:px-4 overflow-x-auto whitespace-nowrap scrollbar-hide">
                <button @click="activeTab = 'categories'"
                    :class="activeTab === 'categories' ? 'text-[#002D72] font-semibold border-[#002D72]' :
                        'text-gray-500 hover:text-gray-700 border-transparent'"
                    class="px-4 md:px-6 py-4 font-medium text-sm transition-all duration-200 relative border-b-2">
                    Kategori
                </button>
                <button @click="activeTab = 'subjects'"
                    :class="activeTab === 'subjects' ? 'text-[#002D72] font-semibold border-[#002D72]' :
                        'text-gray-500 hover:text-gray-700 border-transparent'"
                    class="px-4 md:px-6 py-4 font-medium text-sm transition-all duration-200 relative border-b-2">
                    Subjek
                </button>
                <button @click="activeTab = 'indicators'"
                    :class="activeTab === 'indicators' ? 'text-[#002D72] font-semibold border-[#002D72]' :
                        'text-gray-500 hover:text-gray-700 border-transparent'"
                    class="px-4 md:px-6 py-4 font-medium text-sm transition-all duration-200 relative border-b-2">
                    Indikator Data
                </button>
            </div>

            <div x-show="activeTab === 'categories'" x-cloak x-data="{
                catSearch: '',
                allCatData: {{ Js::from($categories->map(fn($c) => ['name' => strtolower($c->name)])) }},
                get hasVisibleCats() {
                    return this.catSearch === '' || this.allCatData.some(c => c.name.includes(this.catSearch.toLowerCase()));
                }
            }">
                <div class="bg-white rounded-xl shadow-lg">
                    <div class="p-4 md:p-6 border-b border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h3 class="text-lg md:text-xl font-semibold text-gray-900">Kelola Kategori</h3>
                                <p class="text-sm text-gray-500">Buat dan atur kategori utama data statistik</p>
                            </div>
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                <div class="relative">
                                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                                    </svg>
                                    <input type="text" x-model="catSearch" placeholder="Cari kategori..."
                                        class="pl-9 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none w-full sm:w-52 transition-all">
                                </div>
                                <button @click="showCategoryModal = true"
                                    class="bg-[#002D72] text-white hover:bg-[#001f52] px-5 py-2.5 rounded-lg text-sm font-medium flex items-center justify-center gap-2 transition-all shadow-md hover:shadow-lg w-max">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    Tambah Kategori
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 p-4 md:p-6 bg-slate-50/50">
                        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-gray-100 border-b border-gray-200">
                                    <tr>
                                        <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider w-16 text-center">Ikon</th>
                                        <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider">Kategori Statistik</th>
                                        <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider w-32 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($categories as $category)
                                        <tr class="hover:bg-blue-50/30 transition-colors group"
                                            x-show="catSearch === '' || '{{ strtolower($category->name) }}'.includes(catSearch.toLowerCase())"
                                            x-cloak>
                                            <td class="p-4 text-center align-middle">
                                                @php $catName = strtolower($category->name); @endphp
                                                @if (Str::contains($catName, ['ekonomi', 'pdrb', 'perdagangan', 'keuangan', 'harga']))
                                                    <div class="w-10 h-10 mx-auto rounded-full bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                                                    </div>
                                                @elseif(Str::contains($catName, ['sosial', 'demografi', 'penduduk', 'kemiskinan', 'kesehatan']))
                                                    <div class="w-10 h-10 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                                    </div>
                                                @elseif(Str::contains($catName, ['lingkungan', 'multi', 'domain', 'pertanian']))
                                                    <div class="w-10 h-10 mx-auto rounded-full bg-orange-50 text-orange-600 flex items-center justify-center border border-orange-100">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                                                    </div>
                                                @else
                                                    <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 text-slate-500 flex items-center justify-center border border-slate-200">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="p-4 align-middle">
                                                <h3 class="font-bold text-sm md:text-base text-slate-800">{{ $category->name }}</h3>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="inline-flex items-center bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] font-semibold tracking-wide">
                                                        {{ $category->subjects->count() }} SUBJEK
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="p-4 align-middle text-center">
                                                <div class="flex items-center justify-center gap-2 opacity-100">
                                                    <button @click="editingCategory = { id: {{ $category->id }}, name: '{{ $category->name }}' }; showEditCategoryModal = true"
                                                        class="p-2 text-slate-500 hover:text-[#002D72] bg-white hover:bg-blue-50 border border-slate-200 hover:border-blue-200 rounded-lg transition-colors shadow-sm" title="Edit Kategori">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg></button>
                                                    <button @click="$dispatch('open-delete-modal', { action: '{{ route('penanggungjawab.categories.destroy', $category) }}', msg: 'Yakin ingin menghapus kategori ini? SEMUA subjek & indikator di dalamnya akan terhapus permanen.' })"
                                                        class="p-2 text-red-500 hover:text-red-600 bg-white hover:bg-red-50 border border-slate-200 hover:border-red-200 rounded-lg transition-colors shadow-sm" title="Hapus Kategori">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg></button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center py-12 text-slate-500">Belum ada kategori.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                            </div>
                            <div x-show="catSearch !== '' && !hasVisibleCats" x-cloak
                                class="text-center py-10 text-slate-400" style="display:none">
                                <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm">Kategori tidak ditemukan.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="activeTab === 'subjects'" x-cloak x-data="{
                subSearch: '',
                subCatFilter: '',
                allSubData: {{ Js::from($allSubjects->map(fn($s) => ['name' => strtolower($s->name), 'category_id' => (string) $s->category_id])) }},
                get hasVisibleSubs() {
                    return this.allSubData.some(s =>
                        (this.subCatFilter === '' || s.category_id === this.subCatFilter) &&
                        (this.subSearch === '' || s.name.includes(this.subSearch.toLowerCase()))
                    );
                }
            }">
                <div class="bg-white rounded-xl shadow-lg">
                    <div class="p-4 md:p-6 border-b border-gray-200">
                        <div class="flex flex-col gap-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-lg md:text-xl font-semibold text-gray-900">Kelola Subjek</h3>
                                    <p class="text-sm text-gray-500">Daftar seluruh subjek dari semua kategori</p>
                                </div>
                                <div class="flex">
                                    <button @click="showSubjectModal = true; selectedCategoryId = ''"
                                        class="bg-[#002D72] text-white hover:bg-[#001f52] px-5 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition-all shadow-md hover:shadow-lg w-max">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v16m8-8H4" />
                                        </svg>
                                        Tambah Subjek
                                    </button>
                                </div>
                            </div>
                            <!-- Filter & Search bar -->
                            <div class="flex flex-col sm:flex-row gap-2">
                                <div class="relative flex-1">
                                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                                    </svg>
                                    <input type="text" x-model="subSearch" placeholder="Cari subjek..."
                                        class="pl-9 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none w-full transition-all">
                                </div>
                                <select x-model="subCatFilter"
                                    class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none sm:w-56 bg-white">
                                    <option value="">Semua Kategori</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ Str::limit($category->name, 40) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 md:p-6 bg-slate-50/50 border-t border-slate-200 overflow-hidden rounded-b-xl">
                        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                            @if ($allSubjects->isEmpty())
                                <div class="p-12 text-center text-slate-500">
                                    <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    <p class="text-sm font-medium">Belum ada subjek data</p>
                                </div>
                            @else
                                <table class="w-full min-w-[600px] text-left">
                                    <thead class="bg-gray-100 border-b border-gray-200">
                                        <tr>
                                            <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider">
                                                Kategori Induk</th>
                                            <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider">
                                                Nama Subjek</th>
                                            <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider text-center w-28">
                                                Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($allSubjects as $subject)
                                            <tr class="hover:bg-blue-50/30 transition-colors group"
                                                x-show="
                                                    (subCatFilter === '' || subCatFilter === '{{ $subject->category_id }}') &&
                                                    (subSearch === '' || '{{ addslashes(strtolower($subject->name)) }}'.includes(subSearch.toLowerCase()))
                                                "
                                                x-cloak>
                                                <td class="p-4 text-sm text-gray-800 whitespace-nowrap">
                                                    
                                                        {{ $subject->category->name ?? 'Tanpa Kategori' }}
                                                    
                                                </td>
                                                <td class="p-4 text-sm font-medium text-gray-800">
                                                    {{ $subject->name }}
                                                </td>
                                                <td class="p-4 text-center">
                                                    <div class="flex items-center justify-center gap-2 opacity-100">
                                                        <button
                                                            @click="editingSubject = { id: {{ $subject->id }}, category_id: '{{ $subject->category_id }}', name: '{{ $subject->name }}' }; showEditSubjectModal = true"
                                                            class="p-2 text-slate-500 hover:text-[#002D72] bg-white hover:bg-blue-50 border border-slate-200 hover:border-blue-200 rounded-lg transition-colors shadow-sm" title="Edit Subjek">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                            </svg></button>
                                                        <button
                                                            @click="$dispatch('open-delete-modal', { action: '{{ route('penanggungjawab.subjects.destroy', $subject) }}', msg: 'Yakin ingin menghapus subjek ini? Semua data indikator di dalamnya akan ikut terhapus.' })"
                                                            class="p-2 text-red-500 hover:text-red-600 bg-white hover:bg-red-50 border border-slate-200 hover:border-red-200 rounded-lg transition-colors shadow-sm" title="Hapus Subjek">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                                <div x-show="(subSearch !== '' || subCatFilter !== '') && !hasVisibleSubs" x-cloak
                                    class="py-12 text-center text-slate-400" style="display:none">
                                    <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p class="text-sm">Subjek tidak ditemukan.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="activeTab === 'indicators'" x-cloak x-data="{
                indSearch: new URLSearchParams(window.location.search).get('search') || '',
                indCatFilter: new URLSearchParams(window.location.search).get('category_id') || '',
                indSubFilter: new URLSearchParams(window.location.search).get('subject_id') || '',
                searchTimeout: null,
                isSearching: false,
                subjects: {{ Js::from($allSubjects->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'category_id' => $s->category_id])) }},
                applyFilters() {
                    const params = new URLSearchParams();
                    if (this.indSearch) params.set('search', this.indSearch);
                    if (this.indCatFilter) params.set('category_id', this.indCatFilter);
                    if (this.indSubFilter) params.set('subject_id', this.indSubFilter);
                    const query = params.toString();
                    window.location.href = '{{ route('penanggungjawab.keloladata') }}' + (query ? '?' + query : '');
                },
                liveSearch() {
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        this.isSearching = true;
                        const params = new URLSearchParams();
                        if (this.indSearch) params.set('search', this.indSearch);
                        if (this.indCatFilter) params.set('category_id', this.indCatFilter);
                        if (this.indSubFilter) params.set('subject_id', this.indSubFilter);
                        const query = params.toString();
                        const url = '{{ route('penanggungjawab.keloladata') }}' + (query ? '?' + query : '');

                        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(r => r.text())
                            .then(html => {
                                const doc = new DOMParser().parseFromString(html, 'text/html');
                                const newContainer = doc.getElementById('indicator-table-container');
                                if (newContainer) {
                                    document.getElementById('indicator-table-container').innerHTML = newContainer.innerHTML;
                                }
                                window.history.replaceState({}, '', url);
                                this.isSearching = false;
                            })
                            .catch(() => {
                                this.isSearching = false;
                            });
                    }, 400);
                }
            }" x-init="
                $watch('indCatFilter', (val, oldVal) => { if (val !== oldVal) { indSubFilter = ''; applyFilters(); } });
                $watch('indSubFilter', (val, oldVal) => { if (val !== oldVal) applyFilters(); });
            ">
                <div class="bg-white rounded-xl shadow-lg">
                    <div class="p-4 md:p-6 border-b border-gray-200">
                        <div class="flex flex-col gap-3">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div>
                                    <h3 class="text-lg md:text-xl font-semibold text-gray-900">Input Data Indikator
                                    </h3>
                                    <p class="text-sm text-gray-500">Kelola data indikator untuk setiap subjek</p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button @click="showImportModal = true"
                                        class="bg-green-600 text-white hover:bg-green-700 px-5 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition-all shadow-md hover:shadow-lg w-max">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Impor Excel
                                    </button>
                                    <button
                                        @click="indicatorFormData = { selected_category: '', subject_id: '', name: '', unit: '', tableData: { headers: [{ value: '' }, { value: '' }, { value: '' }], rows: [ [{ value: '' }, { value: '' }, { value: '' }] ] } }; showIndicatorModal = true"
                                        class="bg-[#002D72] text-white hover:bg-[#001f52] px-5 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition-all shadow-md hover:shadow-lg w-max">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v16m8-8H4" />
                                        </svg>
                                        Input Manual
                                    </button>
                                </div>
                            </div>
                            <!-- Filter & Search bar -->
                            <div class="flex flex-col sm:flex-row gap-2">
                                <div class="relative flex-1">
                                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" x-show="!isSearching"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                                    </svg>
                                    <svg x-show="isSearching" x-cloak class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#002D72] animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <input type="text" x-model="indSearch" @input="liveSearch()" placeholder="Cari indikator..."
                                        class="pl-9 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none w-full transition-all">
                                </div>
                                <select x-model="indCatFilter"
                                    class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none sm:w-48 bg-white">
                                    <option value="">Semua Kategori</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ Str::limit($category->name, 35) }}
                                        </option>
                                    @endforeach
                                </select>
                                <select x-model="indSubFilter" :disabled="indCatFilter === ''"
                                    class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#002D72] focus:border-transparent outline-none sm:w-48 bg-white disabled:opacity-50 disabled:cursor-not-allowed">
                                    <option value="">Semua Subjek</option>
                                    <template
                                        x-for="s in subjects.filter(s => indCatFilter === '' || String(s.category_id) === indCatFilter)"
                                        :key="s.id">
                                        <option :value="String(s.id)" x-text="s.name" :selected="String(s.id) === indSubFilter"></option>
                                    </template>
                                </select>
                            </div>

                        </div>
                    </div>

                    <div id="indicator-table-container" class="p-4 md:p-6 bg-slate-50/50 border-t border-slate-200 overflow-hidden">
                        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                            @if ($indicators->isEmpty())
                                <div class="p-12 text-center text-slate-500">
                                    <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    <p class="text-sm font-medium">Belum ada data indikator</p>
                                </div>
                            @else
                                <table class="w-full min-w-[700px] text-left">
                                    <thead class="bg-gray-100 border-b border-gray-200">
                                        <tr>
                                            <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider">
                                                Subjek Induk</th>
                                            <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider">
                                                Indikator</th>
                                            <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider">
                                                Satuan</th>
                                            <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider text-center w-28">
                                                Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($indicators as $indicator)
                                            <tr class="hover:bg-blue-50/30 transition-colors group">
                                                <td class="p-4 text-sm font-medium text-gray-800 whitespace-nowrap">
                                                    
                                                        {{ $indicator->subject->name ?? '-' }}
                                                    
                                                </td>
                                                <td class="p-4 text-sm font-medium text-gray-800">
                                                    {{ $indicator->name }}
                                                    @if ($indicator->bps_source)
                                                        <span class="ml-1 inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                                            title="Data diambil otomatis dari tabel dinamis WebAPI BPS saat dibuka. Isian tabel tidak bisa diedit manual.">API BPS</span>
                                                    @endif
                                                </td>
                                                <td class="p-4 text-sm text-gray-500 whitespace-nowrap">
                                                    {{ $indicator->unit ?? '-' }}</td>
                                                <td class="p-4 text-center">
                                                    <div class="flex items-center justify-center gap-2 opacity-100">
                                                        <button
                                                            @click.stop="
                                                                initEditIndicator({{ Js::from($indicator->load('subject')->toArray()) }});
                                                                $dispatch('set-edit-category', {{ $indicator->subject->category_id ?? 'null' }});
                                                            "
                                                            class="p-2 text-slate-500 hover:text-[#002D72] bg-white hover:bg-blue-50 border border-slate-200 hover:border-blue-200 rounded-lg transition-colors shadow-sm" title="Edit Indikator">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                            </svg></button>
                                                        <button
                                                            @click="$dispatch('open-delete-modal', { action: '{{ route('penanggungjawab.indicators.destroy', $indicator) }}', msg: {{ Js::from($indicator->bps_source ? 'Yakin ingin menghapus indikator ini? Tabel dinamis BPS-nya tidak akan dibuat ulang otomatis (bisa ditampilkan lagi dari halaman Data API BPS).' : 'Yakin ingin menghapus data matriks tabel indikator ini?') }} })"
                                                            class="p-2 text-red-500 hover:text-red-600 bg-white hover:bg-red-50 border border-slate-200 hover:border-red-200 rounded-lg transition-colors shadow-sm" title="Hapus Indikator">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif
                        </div>
                    {{-- ======================== --}}
                    {{--    Navigasi Pagination   --}}
                    {{-- ======================== --}}
                    <div class="p-4 md:p-6 border-t border-gray-200">
                        {{ $indicators->links() }}
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- MODAL TAMBAH KATEGORI -->
        <div x-show="showCategoryModal" @keydown.escape.window="showCategoryModal = false" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-auto"
                @click.away="showCategoryModal = false">
                <form action="{{ route('penanggungjawab.categories.store') }}" method="POST">
                    @csrf
                    <div class="p-4 sm:p-6 border-b  border-gray-200 bg-gradient-to-r from-[#002D72] to-[#004B9C]">
                        <h3 class="text-lg md:text-xl font-bold text-white">Tambah Kategori Baru</h3>
                    </div>
                    <div class="p-4 sm:p-6 space-y-4">
                        <div>
                            <label for="category-name-add" class="block text-sm font-medium text-gray-700 mb-2">Nama
                                Kategori</label>
                            <input type="text" id="category-name-add" name="name"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent"
                                placeholder="contoh: Ekonomi..." required>
                        </div>
                    </div>
                    <div
                        class="px-4 sm:px-6 py-4 bg-gray-50 rounded-b-xl flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3">
                        <button type="button" @click="showCategoryModal = false"
                            class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">Batal</button>
                        <button type="submit"
                            class="w-full sm:w-auto px-4 py-2.5 sm:py-2 bg-[#002D72] text-white rounded-lg hover:bg-[#001f52] transition-colors">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL TAMBAH SUBJEK -->
        <div x-show="showSubjectModal" @keydown.escape.window="showSubjectModal = false" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-auto"
                @click.away="showSubjectModal = false">
                <form action="{{ route('penanggungjawab.subjects.store') }}" method="POST">
                    @csrf
                    <div class="p-4 sm:p-6 border-b  border-gray-200 bg-gradient-to-r from-[#002D72] to-[#004B9C]">
                        <h3 class="text-lg md:text-xl font-bold text-white">Tambah Subjek Baru</h3>
                    </div>
                    <div class="p-4 sm:p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                            <select name="category_id"
                                class="w-full max-w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent truncate"
                                :value="selectedCategoryId" required>
                                <option value="">Pilih Kategori...</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" title="{{ $category->name }}">
                                        {{ Str::limit($category->name, 35) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Subjek</label>
                            <input type="text" name="name"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent"
                                placeholder="contoh: PDRB..." required>
                        </div>
                    </div>
                    <div
                        class="px-4 sm:px-6 py-4 bg-gray-50 rounded-b-xl flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3">
                        <button type="button" @click="showSubjectModal = false"
                            class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">Batal</button>
                        <button type="submit"
                            class="w-full sm:w-auto px-4 py-2.5 sm:py-2 bg-[#002D72] text-white rounded-lg hover:bg-[#001f52] transition-colors">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL EDIT KATEGORI -->
        <div x-show="showEditCategoryModal" @keydown.escape.window="showEditCategoryModal = false" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-auto"
                @click.away="showEditCategoryModal = false">
                <form :action="'{{ url('/penanggungjawab/categories') }}/' + editingCategory.id" method="POST">
                    @csrf @method('PATCH')
                    <div class="p-4 sm:p-6 border-b  border-gray-200 bg-gradient-to-r from-[#002D72] to-[#004B9C]">
                        <h3 class="text-lg md:text-xl font-bold text-white">Edit Kategori</h3>
                    </div>
                    <div class="p-4 sm:p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Kategori</label>
                            <input type="text" x-model="editingCategory.name" name="name"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent"
                                required>
                        </div>
                    </div>
                    <div
                        class="px-4 sm:px-6 py-4 bg-gray-50 rounded-b-xl flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3">
                        <button type="button" @click="showEditCategoryModal = false"
                            class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">Batal</button>
                        <button type="submit"
                            class="w-full sm:w-auto px-4 py-2.5 sm:py-2 bg-[#002D72] text-white rounded-lg hover:bg-[#001f52] transition-colors">Update</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL EDIT SUBJEK -->
        <div x-show="showEditSubjectModal" @keydown.escape.window="showEditSubjectModal = false" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-auto"
                @click.away="showEditSubjectModal = false">
                <form :action="'{{ url('/penanggungjawab/subjects') }}/' + editingSubject.id" method="POST">
                    @csrf @method('PATCH')
                    <div class="p-4 sm:p-6 border-b  border-gray-200 bg-gradient-to-r from-[#002D72] to-[#004B9C]">
                        <h3 class="text-lg md:text-xl font-bold text-white">Edit Subjek</h3>
                    </div>
                    <div class="p-4 sm:p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                            <select x-model="editingSubject.category_id" name="category_id"
                                class="w-full max-w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent truncate"
                                required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" title="{{ $category->name }}">
                                        {{ Str::limit($category->name, 35) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Subjek</label>
                            <input type="text" x-model="editingSubject.name" name="name"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent"
                                required>
                        </div>
                    </div>
                    <div
                        class="px-4 sm:px-6 py-4 bg-gray-50 rounded-b-xl flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3">
                        <button type="button" @click="showEditSubjectModal = false"
                            class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">Batal</button>
                        <button type="submit"
                            class="w-full sm:w-auto px-4 py-2.5 sm:py-2 bg-[#002D72] text-white rounded-lg hover:bg-[#001f52] transition-colors">Update</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL TAMBAH INDIKATOR -->
        <div x-show="showIndicatorModal" @keydown.escape.window="showIndicatorModal = false" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col"
                @click.away="showIndicatorModal = false; clearActiveCell()">
                <div class="p-4 md:p-6 border-b border-gray-200 bg-gradient-to-r from-[#002D72] to-[#004B9C]">
                    <h3 class="text-xl md:text-2xl font-bold text-white">Tambah Indikator Baru</h3>
                </div>
                <form action="{{ route('penanggungjawab.indicators.store') }}" method="POST"
                    class="flex-1 overflow-hidden flex flex-col">
                    @csrf
                    <div class="p-4 md:p-6 space-y-5 overflow-y-auto flex-1 bg-gray-50">
                        <div class="bg-white rounded-xl p-4 md:p-5 shadow-sm border border-gray-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori <span
                                            class="text-red-500">*</span></label>
                                    <select x-model="indicatorFormData.selected_category"
                                        class="w-full max-w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent transition-all truncate">
                                        <option value="">Pilih Kategori...</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" title="{{ $category->name }}">
                                                {{ Str::limit($category->name, 35) }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Subjek <span
                                            class="text-red-500">*</span></label>
                                    <select x-model="indicatorFormData.subject_id" name="subject_id"
                                        class="w-full max-w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent transition-all truncate"
                                        required :disabled="!indicatorFormData.selected_category">
                                        <option value="">Pilih Subjek...</option>
                                        @foreach ($allSubjects as $subject)
                                            <option value="{{ $subject->id }}"
                                                x-show="indicatorFormData.selected_category == {{ $subject->category_id }}"
                                                style="display: none;" title="{{ $subject->name }}">
                                                {{ Str::limit($subject->name, 35) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div><label class="block text-sm font-medium text-gray-700 mb-2">Nama Indikator <span
                                            class="text-red-500">*</span></label><input
                                        x-model="indicatorFormData.name" type="text" name="name"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent transition-all"
                                        placeholder="contoh: Jumlah Penduduk" required></div>
                                <div><label class="block text-sm font-medium text-gray-700 mb-2">Satuan</label><input
                                        x-model="indicatorFormData.unit" type="text" name="unit"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] focus:border-transparent transition-all"
                                        placeholder="Jiwa, Persen..."></div>
                            </div>
                        </div>
                        <input type="hidden" name="matrix_data"
                            :value="JSON.stringify(indicatorFormData.tableData)">
                        <div class="bg-white rounded-xl p-4 md:p-5 shadow-sm border border-gray-200">
                            <div class="flex flex-wrap justify-between items-center gap-4 mb-4">
                                <h4 class="text-sm font-semibold text-gray-700">Data Spreadsheet</h4>
                                <div class="flex gap-2"><button type="button" @click="addIndicatorColumn()"
                                        class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium">+
                                        Kolom</button><button type="button" @click="addIndicatorRow()"
                                        class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium">+
                                        Baris</button></div>
                            </div>
                            <div class="border-2 border-gray-300 rounded-lg overflow-hidden shadow-inner">
                                <div class="overflow-auto max-h-[40vh] w-full" style="overscroll-behavior: contain;"
                                    @click.away="clearActiveCell()">
                                    <table class="border-collapse" style="width: max-content; min-width: 100%;">
                                        <tbody class="bg-white">
                                            <tr class="bg-gray-100">
                                                <template
                                                    x-for="(header, colIndex) in indicatorFormData.tableData.headers"
                                                    :key="colIndex">
                                                    <template x-if="!header.hidden">
                                                        <th class="relative border border-gray-300 p-0 min-w-[160px] group"
                                                            :colspan="header.colspan || 1"
                                                            :rowspan="header.rowspan || 1"
                                                            @mouseenter="setActiveCell('create','header',-1,colIndex)"
                                                            @mouseleave="clearActiveCell()"
                                                            :class="isActiveCell('create', 'header', -1, colIndex) ?
                                                                'ring-2 ring-blue-300 bg-blue-50/40' : ''">
                                                            <input type="text" x-model="header.value"
                                                                placeholder="Nama Kolom..."
                                                                :class="colIndex === 0 ? 'text-left' : 'text-center'"
                                                                class="w-full px-3 pt-2 pb-7 bg-transparent border-0 font-bold text-gray-900 text-sm focus:ring-2 focus:ring-inset focus:ring-[#002D72]">
                                                            <div
                                                                class="absolute bottom-1 left-0 right-0 flex items-center justify-between px-1 opacity-0 group-hover:opacity-100 transition">
                                                                <div class="flex gap-0.5">
                                                                    <button type="button"
                                                                        @click="mergeRight('create', 'header', null, colIndex)"
                                                                        title="Merge kanan"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-blue-100 text-blue-700 rounded hover:bg-blue-200">→</button>
                                                                    <button type="button"
                                                                        @click="unmergeRight('create', 'header', null, colIndex)"
                                                                        title="Unmerge kanan"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-orange-100 text-orange-700 rounded hover:bg-orange-200">←</button>
                                                                    <button type="button"
                                                                        @click="mergeDown('create', 'header', null, colIndex)"
                                                                        title="Merge bawah"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-green-100 text-green-700 rounded hover:bg-green-200">↓</button>
                                                                    <button type="button"
                                                                        @click="unmergeDown('create', 'header', null, colIndex)"
                                                                        title="Unmerge bawah"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200">↑</button>
                                                                </div>
                                                                <button type="button"
                                                                    @click="removeIndicatorColumn(colIndex)"
                                                                    class="p-0.5 text-gray-400 hover:text-red-600 font-bold">×</button>
                                                            </div>
                                                        </th>
                                                    </template>
                                                </template>
                                                <th
                                                    class="border border-gray-300 bg-gray-50 p-0 w-12 text-xs text-gray-500">
                                                    Aksi</th>
                                            </tr>

                                            <template x-for="(row, rowIndex) in indicatorFormData.tableData.rows"
                                                :key="rowIndex">
                                                <tr class="hover:bg-blue-50/30">
                                                    <template x-for="(cell, cellIndex) in row" :key="cellIndex">
                                                        <template x-if="!cell.hidden">
                                                            <td class="relative border border-gray-300 p-0 bg-white group"
                                                                :colspan="cell.colspan || 1"
                                                                :rowspan="cell.rowspan || 1"
                                                                @mouseenter="setActiveCell('create','body',rowIndex,cellIndex)"
                                                                @mouseleave="clearActiveCell()"
                                                                :class="isActiveCell('create', 'body', rowIndex, cellIndex) ?
                                                                    'ring-2 ring-blue-300 bg-blue-50/40' : ''">
                                                                <input type="text" x-model="cell.value"
                                                                    :class="cellIndex === 0 ? 'font-bold text-left' : (isHeaderRow(indicatorFormData.tableData, rowIndex) ? 'font-bold text-center' : 'text-right')"
                                                                    class="w-full px-3 pt-2 pb-7 bg-transparent border-0 text-sm focus:ring-2 focus:ring-inset focus:ring-[#002D72]">
                                                                <div
                                                                    class="absolute bottom-1 left-1 flex gap-0.5 opacity-0 group-hover:opacity-100 transition">
                                                                    <button type="button"
                                                                        @click="mergeRight('create', 'body', rowIndex, cellIndex)"
                                                                        title="Merge kanan"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-blue-100 text-blue-700 rounded hover:bg-blue-200">→</button>
                                                                    <button type="button"
                                                                        @click="unmergeRight('create', 'body', rowIndex, cellIndex)"
                                                                        title="Unmerge kanan"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-orange-100 text-orange-700 rounded hover:bg-orange-200">←</button>
                                                                    <button type="button"
                                                                        @click="mergeDown('create', 'body', rowIndex, cellIndex)"
                                                                        title="Merge bawah"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-green-100 text-green-700 rounded hover:bg-green-200">↓</button>
                                                                    <button type="button"
                                                                        @click="unmergeDown('create', 'body', rowIndex, cellIndex)"
                                                                        title="Unmerge bawah"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200">↑</button>
                                                                </div>
                                                            </td>
                                                        </template>
                                                    </template>
                                                    <td class="border border-gray-300 bg-gray-50 p-0">
                                                        <button type="button" @click="removeIndicatorRow(rowIndex)"
                                                            class="w-full h-full py-2.5 text-gray-400 hover:text-red-600">×</button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="px-4 sm:px-6 py-4 bg-white border-t border-gray-200 rounded-b-2xl flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3">
                        <button type="button" @click="showIndicatorModal = false"
                            class="w-full sm:w-auto px-6 py-2.5 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium">Batal</button>
                        <button type="submit"
                            class="w-full sm:w-auto px-6 py-2.5 bg-[#002D72] text-white rounded-lg hover:bg-[#001f52] font-medium shadow-lg">Simpan
                            Indikator</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL EDIT INDIKATOR -->
        <div x-show="showEditIndicatorModal" x-data="{ editCategoryId: '' }"
            @set-edit-category.window="editCategoryId = $event.detail"
            @keydown.escape.window="showEditIndicatorModal = false" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col"
                @click.away="showEditIndicatorModal = false; clearActiveCell()">
                <div class="p-4 md:p-6 border-b  border-gray-200 bg-gradient-to-r from-[#002D72] to-[#004B9C]">
                    <h3 class="text-xl md:text-2xl font-bold text-white">Edit Indikator</h3>
                </div>
                <form :action="'{{ url('/penanggungjawab/indicators') }}/' + editingIndicator.id" method="POST"
                    class="flex-1 overflow-hidden flex flex-col">
                    @csrf @method('PATCH')
                    <div class="p-4 md:p-6 space-y-5 overflow-y-auto flex-1 bg-gray-50">
                        <div class="bg-white rounded-xl p-4 md:p-5 shadow-sm border border-gray-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori <span
                                            class="text-red-500">*</span></label>
                                    <select x-model="editCategoryId" @change="editingIndicator.subject_id = ''"
                                        class="w-full max-w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] outline-none truncate"
                                        required>
                                        <option value="">Pilih Kategori...</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" title="{{ $category->name }}">
                                                {{ Str::limit($category->name, 35) }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Subjek <span
                                            class="text-red-500">*</span></label>
                                    <select x-model="editingIndicator.subject_id" name="subject_id"
                                        class="w-full max-w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] outline-none truncate"
                                        required :disabled="!editCategoryId">
                                        <option value="">Pilih Subjek...</option>
                                        @foreach ($allSubjects as $subject)
                                            <option value="{{ $subject->id }}"
                                                x-show="editCategoryId == {{ $subject->category_id }}"
                                                style="display: none;" title="{{ $subject->name }}">
                                                {{ Str::limit($subject->name, 35) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div><label class="block text-sm font-medium text-gray-700 mb-2">Nama Indikator <span
                                            class="text-red-500">*</span></label><input
                                        x-model="editingIndicator.name" type="text" name="name"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] outline-none"
                                        required></div>
                                <div><label class="block text-sm font-medium text-gray-700 mb-2">Satuan</label><input
                                        x-model="editingIndicator.unit" type="text" name="unit"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] outline-none">
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="matrix_data" :value="JSON.stringify(editingIndicator.tableData)">
                        <div x-show="editingIndicator.bps_source" x-cloak
                            class="bg-blue-50 border border-blue-200 text-blue-800 rounded-xl p-4 text-sm">
                            Data tabel indikator ini diambil otomatis dari tabel dinamis WebAPI BPS, jadi tidak bisa diedit di sini.
                            Yang disimpan hanya kategori, subjek, nama, dan satuan.
                        </div>
                        <div x-show="!editingIndicator.bps_source" class="bg-white rounded-xl p-4 md:p-5 shadow-sm border border-gray-200">
                            <div class="flex flex-wrap justify-between items-center gap-4 mb-4">
                                <h4 class="text-sm font-semibold text-gray-700">Data Spreadsheet</h4>
                                <div class="flex gap-2"><button type="button" @click="addEditingColumn()"
                                        class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium">+
                                        Kolom</button><button type="button" @click="addEditingRow()"
                                        class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium">+
                                        Baris</button></div>
                            </div>
                            <div class="border-2 border-gray-300 rounded-lg overflow-hidden shadow-inner">
                                <div class="overflow-auto max-h-[40vh] w-full" style="overscroll-behavior: contain;"
                                    @click.away="clearActiveCell()">
                                    <table class="border-collapse" style="width: max-content; min-width: 100%;">
                                        <tbody class="bg-white">
                                            <tr class="bg-gray-100">
                                                <template
                                                    x-for="(header, colIndex) in editingIndicator.tableData.headers"
                                                    :key="colIndex">
                                                    <template x-if="!header.hidden">
                                                        <th class="relative border border-gray-300 p-0 min-w-[160px] group"
                                                            :colspan="header.colspan || 1"
                                                            :rowspan="header.rowspan || 1"
                                                            @mouseenter="setActiveCell('edit','header',-1,colIndex)"
                                                            @mouseleave="clearActiveCell()"
                                                            :class="isActiveCell('edit', 'header', -1, colIndex) ?
                                                                'ring-2 ring-orange-300 bg-orange-50/40' : ''">
                                                            <input type="text" x-model="header.value"
                                                                :class="colIndex === 0 ? 'text-left' : 'text-center'"
                                                                class="w-full px-3 pt-2 pb-7 bg-transparent border-0 font-bold text-gray-900 text-sm focus:ring-2 focus:ring-inset focus:ring-[#002D72]">
                                                            <div
                                                                class="absolute bottom-1 left-0 right-0 flex items-center justify-between px-1 opacity-0 group-hover:opacity-100 transition">
                                                                <div class="flex gap-0.5">
                                                                    <button type="button"
                                                                        @click="mergeRight('edit', 'header', null, colIndex)"
                                                                        title="Merge kanan"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-blue-100 text-blue-700 rounded hover:bg-blue-200">→</button>
                                                                    <button type="button"
                                                                        @click="unmergeRight('edit', 'header', null, colIndex)"
                                                                        title="Unmerge kanan"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-orange-100 text-orange-700 rounded hover:bg-orange-200">←</button>
                                                                    <button type="button"
                                                                        @click="mergeDown('edit', 'header', null, colIndex)"
                                                                        title="Merge bawah"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-green-100 text-green-700 rounded hover:bg-green-200">↓</button>
                                                                    <button type="button"
                                                                        @click="unmergeDown('edit', 'header', null, colIndex)"
                                                                        title="Unmerge bawah"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200">↑</button>
                                                                </div>
                                                                <button type="button"
                                                                    @click="removeEditingColumn(colIndex)"
                                                                    class="p-0.5 text-gray-400 hover:text-red-600 font-bold">×</button>
                                                            </div>
                                                        </th>
                                                    </template>
                                                </template>
                                                <th
                                                    class="border border-gray-300 bg-gray-50 p-0 w-12 text-xs text-gray-500">
                                                    Aksi</th>
                                            </tr>

                                            <template x-for="(row, rowIndex) in editingIndicator.tableData.rows"
                                                :key="rowIndex">
                                                <tr class="hover:bg-orange-50/30">
                                                    <template x-for="(cell, cellIndex) in row" :key="cellIndex">
                                                        <template x-if="!cell.hidden">
                                                            <td class="relative border border-gray-300 p-0 bg-white group"
                                                                :colspan="cell.colspan || 1"
                                                                :rowspan="cell.rowspan || 1"
                                                                @mouseenter="setActiveCell('edit','body',rowIndex,cellIndex)"
                                                                @mouseleave="clearActiveCell()"
                                                                :class="isActiveCell('edit', 'body', rowIndex, cellIndex) ?
                                                                    'ring-2 ring-orange-300 bg-orange-50/40' : ''">
                                                                <input type="text" x-model="cell.value"
                                                                    :class="cellIndex === 0 ? 'font-bold text-left' : (isHeaderRow(editingIndicator.tableData, rowIndex) ? 'font-bold text-center' : 'text-right')"
                                                                    class="w-full px-3 pt-2 pb-7 bg-transparent border-0 text-sm focus:ring-2 focus:ring-inset focus:ring-[#002D72]">
                                                                <div
                                                                    class="absolute bottom-1 left-1 flex gap-0.5 opacity-0 group-hover:opacity-100 transition">
                                                                    <button type="button"
                                                                        @click="mergeRight('edit', 'body', rowIndex, cellIndex)"
                                                                        title="Merge kanan"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-blue-100 text-blue-700 rounded hover:bg-blue-200">→</button>
                                                                    <button type="button"
                                                                        @click="unmergeRight('edit', 'body', rowIndex, cellIndex)"
                                                                        title="Unmerge kanan"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-orange-100 text-orange-700 rounded hover:bg-orange-200">←</button>
                                                                    <button type="button"
                                                                        @click="mergeDown('edit', 'body', rowIndex, cellIndex)"
                                                                        title="Merge bawah"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-green-100 text-green-700 rounded hover:bg-green-200">↓</button>
                                                                    <button type="button"
                                                                        @click="unmergeDown('edit', 'body', rowIndex, cellIndex)"
                                                                        title="Unmerge bawah"
                                                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200">↑</button>
                                                                </div>
                                                            </td>
                                                        </template>
                                                    </template>
                                                    <td class="border border-gray-300 bg-gray-50 p-0">
                                                        <button type="button" @click="removeEditingRow(rowIndex)"
                                                            class="w-full h-full py-2.5 text-gray-400 hover:text-red-600">×</button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="px-4 sm:px-6 py-4 bg-white border-t border-gray-200 rounded-b-2xl flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3">
                        <button type="button" @click="showEditIndicatorModal = false"
                            class="w-full sm:w-auto px-6 py-2.5 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium">Batal</button>
                        <button type="submit"
                            class="w-full sm:w-auto px-6 py-2.5 bg-[#002D72] text-white rounded-lg hover:bg-[#001f52] transition-colors">Update
                            Indikator</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-data="{ showDelete: false, deleteAction: '', deleteMsg: '' }"
            @open-delete-modal.window="showDelete = true; deleteAction = $event.detail.action; deleteMsg = $event.detail.msg"
            x-show="showDelete" x-cloak
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto overflow-hidden"
                @click.away="showDelete = false" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                <form :action="deleteAction" method="POST">
                    @csrf @method('DELETE')
                    <div class="p-6 md:p-8 text-center">
                        <div
                            class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-5 shadow-inner">
                            <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Konfirmasi Hapus</h3>
                        <p class="text-gray-600 text-sm md:text-base mb-8" x-text="deleteMsg"></p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <button type="button" @click="showDelete = false"
                                class="w-full sm:w-auto px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition-colors">Batal</button>
                            <button type="submit"
                                class="w-full sm:w-auto px-6 py-3 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 shadow-lg transition-colors">Ya,
                                Hapus Data</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showImportModal" @keydown.escape.window="showImportModal = false" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-auto" @click.away="showImportModal = false">
                <form action="{{ route('penanggungjawab.indicators.import') }}" method="POST"
                    enctype="multipart/form-data" x-data="{ selectedImportCategory: '' }">
                    @csrf
                    <div class="p-4 sm:p-6 border-b  border-gray-200 bg-gradient-to-r from-[#002D72] to-[#004B9C]">
                        <h3 class="text-xl font-bold text-white">Impor Data Excel</h3>
                    </div>
                    <div class="p-4 sm:p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Kategori <span
                                    class="text-red-500">*</span></label>
                            <select x-model="selectedImportCategory"
                                class="w-full max-w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] outline-none truncate"
                                required>
                                <option value="">-- Pilih Kategori --</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" title="{{ $category->name }}">
                                        {{ Str::limit($category->name, 35) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Subjek <span
                                    class="text-red-500">*</span></label>
                            <select name="subject_id"
                                class="w-full max-w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] outline-none truncate"
                                required :disabled="!selectedImportCategory">
                                <option value="">-- Pilih Subjek --</option>
                                @foreach ($allSubjects as $subject)
                                    <option value="{{ $subject->id }}"
                                        x-show="selectedImportCategory == {{ $subject->category_id }}"
                                        style="display: none;" title="{{ $subject->name }}">
                                        {{ Str::limit($subject->name, 35) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Indikator <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="name"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] outline-none"
                                required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Satuan</label>
                            <input type="text" name="unit"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002D72] outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">File Excel <span
                                    class="text-red-500">*</span></label>
                            <input type="file" name="excel_file"
                                class="w-full border border-gray-300 rounded-lg p-2 text-sm"
                                accept=".xlsx, .xls, .csv" required>
                        </div>
                    </div>
                    <div
                        class="px-4 sm:px-6 py-4 bg-gray-50 rounded-b-xl flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3">
                        <button type="button" @click="showImportModal = false"
                            class="w-full sm:w-auto px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100">Batal</button>
                        <button type="submit"
                            class="w-full sm:w-auto px-4 py-2 bg-[#002D72] text-white rounded-lg hover:bg-[#001f52] font-medium shadow-lg">Proses
                            Impor</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    @push('scripts')
        @vite('resources/js/pj.js')
    @endpush

</x-penanggungjawablayout>
