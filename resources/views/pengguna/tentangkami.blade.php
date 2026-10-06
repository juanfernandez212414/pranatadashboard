{{-- resources/views/pengguna/tentangkami.blade.php --}}

<x-penggunalayout title="Tentang Kami - pengguna PRANATA">

    <div class="space-y-6">
        {{-- Header Halaman --}}
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1">Tentang Kami</h1>
            <p class="text-sm md:text-base text-gray-500">Mengenal lebih jauh tentang visi, filosofi, dan sistem PRANATA
            </p>
        </div>

        {{-- Kontainer Utama --}}
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">

            {{-- Bagian 1: Hero Section (Logo & Judul) --}}
            <div class="bg-gradient-to-r from-[#002D72] to-[#004B9C] p-8 md:p-12">
                <div
                    class="flex flex-col md:flex-row items-center justify-center gap-6 md:gap-10 text-center md:text-left">
                    <div class="bg-white p-4 rounded-2xl shadow-xl shrink-0">
                        <img src="{{ asset('images/LogoPRANATA.png') }}" alt="Logo PRANATA"
                            class="w-32 h-32 md:w-40 md:h-40 object-contain">
                    </div>
                    <div class="text-white">
                        <h1 class="text-4xl md:text-5xl font-black mb-2 tracking-tight">PRANATA</h1>
                        <p class="text-xl md:text-2xl font-medium text-blue-100 italic font-serif">
                            "Menata Data, Mengukir Makna"
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-6 md:p-12 space-y-10 md:space-y-12">

                {{-- Bagian 2: Apa itu PRANATA & Penjabaran Akronim --}}
                <div>
                    <div class="flex items-center gap-3 mb-4 md:mb-6">
                        <svg class="w-7 h-7 md:w-8 md:h-8 text-[#002D72]" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h2 class="text-xl md:text-3xl font-bold text-gray-900">Apa itu PRANATA?</h2>
                    </div>
                    <p class="text-base md:text-lg text-gray-700 leading-relaxed mb-6 md:mb-8">
                        <strong>PRANATA</strong> merupakan sebuah inovasi sistem cerdas yang dibangun khusus untuk Badan
                        Pusat Statistik (BPS) Kota Pematangsiantar. Nama ini bukan sekadar sebutan, melainkan sebuah
                        akronim penuh makna yang mendeskripsikan fungsi utamanya:
                    </p>

                    {{-- Grid Kartu Akronim (SUDAH DIPERBAIKI UNTUK TAMPILAN HP) --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-6">
                        <div
                            class="bg-blue-50 border-l-4 border-[#002D72] p-3 md:p-5 rounded-r-xl shadow-sm hover:shadow-md transition-shadow flex flex-col justify-center">
                            <span class="text-2xl md:text-3xl font-black text-[#002D72] block mb-1">PR</span>
                            <span
                                class="text-gray-800 font-bold text-xs sm:text-sm md:text-lg uppercase tracking-wide break-words">Portal</span>
                        </div>
                        <div
                            class="bg-blue-50 border-l-4 border-[#002D72] p-3 md:p-5 rounded-r-xl shadow-sm hover:shadow-md transition-shadow flex flex-col justify-center">
                            <span class="text-2xl md:text-3xl font-black text-[#002D72] block mb-1">A</span>
                            <span
                                class="text-gray-800 font-bold text-xs sm:text-sm md:text-lg uppercase tracking-wide break-words">Otomatisasi</span>
                        </div>
                        <div
                            class="bg-blue-50 border-l-4 border-[#002D72] p-3 md:p-5 rounded-r-xl shadow-sm hover:shadow-md transition-shadow flex flex-col justify-center">
                            <span class="text-2xl md:text-3xl font-black text-[#002D72] block mb-1">NA</span>
                            <span
                                class="text-gray-800 font-bold text-xs sm:text-sm md:text-lg uppercase tracking-wide break-words">Narasi</span>
                        </div>
                        <div
                            class="bg-blue-50 border-l-4 border-[#002D72] p-3 md:p-5 rounded-r-xl shadow-sm hover:shadow-md transition-shadow flex flex-col justify-center">
                            <span class="text-2xl md:text-3xl font-black text-[#002D72] block mb-1">TA</span>
                            <span
                                class="text-gray-800 font-bold text-xs sm:text-sm md:text-lg uppercase tracking-wide break-words">Statistik</span>
                        </div>
                    </div>
                </div>

                <hr class="border-gray-200">

                {{-- Bagian 3: Filosofi --}}
                <div>
                    <div class="flex items-center gap-3 mb-4 md:mb-6">
                        <svg class="w-7 h-7 md:w-8 md:h-8 text-[#002D72]" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <h2 class="text-xl md:text-3xl font-bold text-gray-900">Arti & Filosofi</h2>
                    </div>
                    <div class="prose max-w-none text-gray-700 text-base md:text-lg leading-relaxed space-y-4">
                        <p>
                            Secara harfiah dalam Bahasa Indonesia, kata <strong>PRANATA</strong> memiliki arti sebagai
                            <strong> sistem, institusi, atau tata aturan</strong>. Makna ini mengakar kuat dan
                            sangat relevan dengan peran sentral Badan Pusat Statistik (BPS) sebagai lembaga penyedia
                            data yang sah dan tepercaya.
                        </p>
                        <p>
                            Filosofi utama di balik penamaan ini menegaskan bahwa aplikasi PRANATA bukanlah sekadar alat
                            visualisasi angka biasa. Ia hadir sebagai sebuah <strong> Sistem PRANATA Baru </strong> di
                            bidang diseminasi data. Layaknya sebuah institusi digital, PRANATA bekerja di balik layar
                            untuk mengatur, menata, dan menerjemahkan barisan data statistik yang rumit menjadi narasi
                            yang mudah dipahami oleh masyarakat luas secara otomatis.
                        </p>
                        <p>
                            Nama ini membawa semangat <strong>keteraturan dan integritas</strong> sebuah cerminan dari
                            sistem yang mapan, yang memberikan otoritas, transparansi, serta kepercayaan tinggi terhadap
                            setiap data yang disajikan kepada publik.
                        </p>
                    </div>
                </div>

                <hr class="border-gray-200">

                {{-- Bagian 4: Hubungi Kami --}}
                <div>
                    <div class="flex items-center gap-3 mb-4 md:mb-6">
                        <svg class="w-7 h-7 md:w-8 md:h-8 text-[#002D72]" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <h2 class="text-xl md:text-3xl font-bold text-gray-900">Hubungi Kami</h2>
                    </div>

                    <div
                        class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 bg-gray-50 p-6 md:p-8 rounded-xl border border-gray-100">
                        {{-- Alamat --}}
                        <div>
                            <h3 class="text-base md:text-lg font-bold text-[#002D72] mb-4 uppercase tracking-wide">
                                Alamat Kantor</h3>
                            <div class="space-y-4 text-gray-600">
                                <div class="flex items-start gap-3 md:gap-4">
                                    <div class="bg-white p-2 rounded-lg shadow-sm shrink-0">
                                        <svg class="w-5 h-5 md:w-6 md:h-6 text-[#002D72]" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                            </path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                    </div>
                                    <span class="leading-relaxed text-sm md:text-base">
                                        <strong class="text-gray-900">Badan Pusat Statistik Kota
                                            Pematangsiantar</strong><br>
                                        Jl. Sisingamangaraja No. 258A<br>
                                        Pematangsiantar, Sumatera Utara 21137
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 md:gap-4">
                                    <div class="bg-white p-2 rounded-lg shadow-sm shrink-0">
                                        <svg class="w-5 h-5 md:w-6 md:h-6 text-[#002D72]" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                            </path>
                                        </svg>
                                    </div>
                                    <a href="mailto:bps1273@bps.go.id"
                                        class="text-sm md:text-base hover:text-[#002D72] hover:underline font-medium transition-colors">bps1273@bps.go.id</a>
                                </div>
                            </div>
                        </div>
			
			{{-- Media Sosial --}}
                        <div>
                            <h3 class="text-base md:text-lg font-bold text-[#002D72] mb-4 uppercase tracking-wide">Media
                                Sosial</h3>
                            <div class="flex flex-wrap gap-3 md:gap-4">
                                {{-- Facebook --}}
                                <a href="https://www.facebook.com/bpssiantar" target="_blank" rel="noopener noreferrer"
                                    class="bg-white p-2 md:p-3 rounded-xl shadow-sm text-gray-400 hover:text-blue-600 hover:shadow-md transition-all"
                                    title="Facebook">
                                    <svg class="w-7 h-7 md:w-8 md:h-8" fill="currentColor" viewBox="0 0 24 24"
                                        aria-hidden="true">
                                        <path fill-rule="evenodd"
                                            d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                </a>
                                
                                {{-- Instagram --}}
                                <a href="https://www.instagram.com/bpskotapematangsiantar" target="_blank" rel="noopener noreferrer"
                                    class="bg-white text-gray-400 hover:text-pink-600 p-2 md:p-3 rounded-xl shadow-sm hover:shadow-md transition-all"
                                    title="Instagram">
                                    <svg class="w-7 h-7 md:w-8 md:h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm0 4.865a5.135 5.135 0 100 10.27 5.135 5.135 0 000-10.27zm0 8.468a3.333 3.333 0 110-6.666 3.333 3.333 0 010 6.666zm5.338-9.873a1.2 1.2 0 100 2.4 1.2 1.2 0 000-2.4z">
                                        </path>
                                    </svg>
                                </a>
                                
                                {{-- YouTube --}}
                                <a href="https://www.youtube.com/@bpskotapematangsiantar6980" target="_blank"
                                    rel="noopener noreferrer"
                                    class="bg-white p-2 md:p-3 rounded-xl shadow-sm text-gray-400 hover:text-red-600 hover:shadow-md transition-all"
                                    title="YouTube">
                                    <svg class="w-7 h-7 md:w-8 md:h-8" fill="currentColor" viewBox="0 0 24 24"
                                        aria-hidden="true">
                                        <path fill-rule="evenodd"
                                            d="M19.812 5.418c.861.23 1.538.907 1.768 1.768C21.998 8.749 22 12 22 12s0 3.251-.418 4.814a2.506 2.506 0 01-1.768 1.768c-1.562.419-7.814.419-7.814.419s-6.252 0-7.814-.419a2.505 2.505 0 01-1.768-1.768C2 15.251 2 12 2 12s0-3.251.418-4.814a2.506 2.506 0 011.768-1.768C5.75 5 12 5 12 5s6.252 0 7.812.418zM15.324 12L9.805 8.814v6.372L15.324 12z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
			
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</x-penggunalayout>
