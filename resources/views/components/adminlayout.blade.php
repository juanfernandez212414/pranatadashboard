{{-- resources/views/components/adminlayout.blade.php --}}

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>

    <link rel="icon" type="image/png" href="{{ asset('images/LogoPRANATA.png') }}">

    @vite(['resources/css/app.css', 'resources/css/adminstyle.css', 'resources/js/app.js', 'resources/js/admin.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('styles')
</head>

<body class="bg-gray-100 font-['Inter']">

    <div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden relative">

        <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-20 bg-black/50 lg:hidden"
            @click="sidebarOpen = false" x-cloak></div>

        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-30 w-[280px] md:w-[306px] bg-white shadow-xl flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-auto flex-shrink-0">

            <button @click="sidebarOpen = false"
                class="absolute top-4 right-4 text-gray-500 hover:text-red-500 lg:hidden focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>

            <div class="pt-4 pb-1 text-center bg-white">
                <img src="{{ asset('images/LogoPRANATA.png') }}" alt="PRANATA Logo"
                    class="w-[150px] md:w-[205px] mx-auto object-contain -mt-3">
                <div class="-mt-4 md:-mt-11">
                    <h1 class="text-[#002D72] text-[20px] md:text-[24px] font-bold leading-[30px]">PRANATA</h1>
                    <p class="text-[#002D72] text-[15px] md:text-[19px] font-bold leading-[24px] md:leading-[30px]">
                        Menata Data, Mengukir Makna</p>
                </div>
            </div>

            <nav class="flex-1 px-3 overflow-y-auto mt-2">
                <ul class="space-y-1">
                    {{-- Dashboard dengan Dropdown --}}
                    <li>
                        @php
                            $isDashboardActive = request()->routeIs('admin.dashboard');
                            $hasActiveCategory =
                                request()->filled('category_id') &&
                                $categories->contains('id', request()->query('category_id'));
                        @endphp

                        <button onclick="toggleDropdown('dashboard-dropdown')"
                            class="w-full flex items-center justify-between p-3 rounded-xl {{ $isDashboardActive ? 'bg-[#002D72] text-white' : 'bg-gray-50 text-gray-600' }} hover:bg-[#002D72] hover:text-white transition-all duration-200">
                            <div class="flex items-center gap-3">
                                <svg class="w-[19px] h-[19px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <rect x="3" y="3" width="7" height="7" stroke-width="1.5" rx="1" />
                                    <rect x="3" y="14" width="7" height="7" stroke-width="1.5"
                                        rx="1" />
                                    <rect x="14" y="3" width="7" height="7" stroke-width="1.5"
                                        rx="1" />
                                    <rect x="14" y="14" width="7" height="7" stroke-width="1.5"
                                        rx="1" />
                                </svg>
                                <span class="font-medium text-[12px]">Dashboard</span>
                            </div>
                            <svg id="dashboard-arrow"
                                class="w-[19px] h-[19px] transition-transform duration-300 {{ $hasActiveCategory ? 'rotate-180' : '' }}"
                                fill="currentColor" viewBox="0 0 24 24">
                                <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z" />
                            </svg>
                        </button>

                        <div id="dashboard-dropdown" class="{{ $hasActiveCategory ? '' : 'hidden' }} mt-2 ml-4">
                            <div class="bg-gray-50 rounded-lg p-2 shadow-sm">
                                <ul class="space-y-0.5">
                                    @forelse($categories as $category)
                                        @php $isCategoryActive = request()->query('category_id') == $category->id; @endphp
                                        <li>
                                            <a href="{{ route('admin.dashboard') }}?category_id={{ $category->id }}"
                                                class="group py-2 px-3 text-[10.5px] rounded-lg cursor-pointer transition-all duration-200 flex items-center gap-2 {{ $isCategoryActive ? 'font-medium text-white bg-[#002D72] shadow-sm' : 'text-gray-600 hover:bg-[#002D72] hover:text-white' }}">
                                                <span
                                                    class="w-1.5 h-1.5 rounded-full {{ $isCategoryActive ? 'bg-white' : 'bg-gray-400 group-hover:bg-white' }}"></span>
                                                {{ $category->name }}
                                            </a>
                                        </li>
                                    @empty
                                        <li class="px-3 py-2 text-[10.5px] text-gray-400">Belum ada kategori.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </li>

                    {{-- Manajemen Data dengan Dropdown --}}
                    @php
                        $isManajemenData = request()->routeIs('admin.keloladata');
                        $isDataView = request()->routeIs('admin.lihatdata*');
                        $isManajemenActive = $isManajemenData || $isDataView;
                    @endphp
                    <li>
                        <button onclick="toggleDropdown('manajemen-dropdown')"
                            class="w-full flex items-center justify-between p-3 rounded-xl {{ $isManajemenActive ? 'bg-[#002D72] text-white' : 'bg-gray-50 text-gray-600' }} hover:bg-[#002D72] hover:text-white transition-all duration-200">
                            <div class="flex items-center gap-3">
                                <svg class="w-[19px] h-[19px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="font-medium text-[12px]">Manajemen Data</span>
                            </div>
                            <svg id="manajemen-arrow"
                                class="w-[19px] h-[19px] transition-transform duration-300 {{ $isManajemenActive ? 'rotate-180' : '' }}"
                                fill="currentColor" viewBox="0 0 24 24">
                                <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z" />
                            </svg>
                        </button>
                        <div id="manajemen-dropdown" class="{{ $isManajemenActive ? '' : 'hidden' }} mt-2 ml-4">
                            <div class="bg-gray-50 rounded-lg p-2 shadow-sm">
                                <ul class="space-y-0.5">
                                    <li>
                                        <a href="{{ route('admin.keloladata') }}"
                                            class="group py-2 px-3 text-[10.5px] rounded-lg cursor-pointer transition-all duration-200 flex items-center gap-2 {{ $isManajemenData ? 'font-medium text-white bg-[#002D72] shadow-sm' : 'text-gray-600 hover:bg-[#002D72] hover:text-white' }}">
                                            <span
                                                class="w-1.5 h-1.5 rounded-full {{ $isManajemenData ? 'bg-white' : 'bg-gray-400 group-hover:bg-white' }}"></span>
                                            Kelola Data
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('admin.lihatdata') }}"
                                            class="group py-2 px-3 text-[10.5px] rounded-lg cursor-pointer transition-all duration-200 flex items-center gap-2 {{ $isDataView ? 'font-medium text-white bg-[#002D72] shadow-sm' : 'text-gray-600 hover:bg-[#002D72] hover:text-white' }}">
                                            <span
                                                class="w-1.5 h-1.5 rounded-full {{ $isDataView ? 'bg-white' : 'bg-gray-400 group-hover:bg-white' }}"></span>
                                            Lihat Data
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </li>

                    {{-- Data API BPS --}}
                    <li>
                        @php $isDataBps = request()->routeIs('admin.databps*'); @endphp
                        <a href="{{ route('admin.databps') }}"
                            class="w-full flex items-center gap-3 p-3 rounded-xl {{ $isDataBps ? 'bg-[#002D72] text-white' : 'bg-gray-50 text-gray-600' }} hover:bg-[#002D72] hover:text-white transition-all duration-200 cursor-pointer">
                            <svg class="w-[19px] h-[19px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                            <span class="font-medium text-[12px]">Data API BPS</span>
                        </a>
                    </li>

                    {{-- Pengguna --}}
                    <li>
                        @php $isPengguna = request()->routeIs('admin.pengguna'); @endphp
                        <a href="{{ route('admin.pengguna') }}"
                            class="w-full flex items-center gap-3 p-3 rounded-xl {{ $isPengguna ? 'bg-[#002D72] text-white' : 'bg-gray-50 text-gray-600' }} hover:bg-[#002D72] hover:text-white transition-all duration-200 cursor-pointer">
                            <svg class="w-[19px] h-[19px]" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                            </svg>
                            <span class="font-medium text-[12px]">Pengguna</span>
                        </a>
                    </li>

                    {{-- Model --}}
                    <li>
                        @php $isModel = request()->routeIs('admin.model'); @endphp
                        <a href="{{ route('admin.model') }}"
                            class="w-full flex items-center gap-3 p-3 rounded-xl {{ $isModel ? 'bg-[#002D72] text-white' : 'bg-gray-50 text-gray-600' }} hover:bg-[#002D72] hover:text-white transition-all duration-200 cursor-pointer">
                            <svg class="w-[19px] h-[19px]" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z" />
                            </svg>
                            <span class="font-medium text-[12px]">Model</span>
                        </a>
                    </li>

                    {{-- Manajemen Pengetahuan --}}
                    <li>
                        @php $isPengetahuan = request()->routeIs('admin.pengetahuan'); @endphp
                        <a href="{{ route('admin.pengetahuan') }}"
                            class="w-full flex items-center gap-3 p-3 rounded-xl {{ $isPengetahuan ? 'bg-[#002D72] text-white' : 'bg-gray-50 text-gray-600' }} hover:bg-[#002D72] hover:text-white transition-all duration-200 cursor-pointer">
                            <svg class="w-[19px] h-[19px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4">
                                </path>
                            </svg>
                            <span class="font-medium text-[12px]">Manajemen Pengetahuan</span>
                        </a>
                    </li>

                    {{-- Tentang Kami --}}
                    <li>
                        @php $isTentang = request()->routeIs('admin.tentangkami'); @endphp
                        <a href="{{ route('admin.tentangkami') }}"
                            class="w-full flex items-center gap-3 p-3 rounded-xl {{ $isTentang ? 'bg-[#002D72] text-white' : 'bg-gray-50 text-gray-600' }} hover:bg-[#002D72] hover:text-white transition-all duration-200 cursor-pointer">
                            <svg class="w-[19px] h-[19px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="9" stroke-width="1.5" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M12 8v4m0 4h.01" />
                            </svg>
                            <span class="font-medium text-[12px]">Tentang Kami</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="p-5">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-bold py-2.5 px-5 rounded-2xl transition-all duration-200 shadow-lg hover:shadow-xl text-[17px]">
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <main class="flex-1 flex flex-col min-w-0 overflow-auto">
            {{-- Header responsif: di HP logo, judul, dan jarak mengecil mengikuti lebar layar (clamp + vw);
                 mulai lg ukurannya sama dengan tampilan desktop. --}}
            <header
                class="bg-gradient-to-r from-[#002D72] to-[#003d8f] shadow-xl min-h-[64px] sm:min-h-[76px] lg:min-h-[85px] flex items-center justify-between px-3 sm:px-5 lg:px-6 py-2 sm:py-0 gap-2 sm:gap-4">
                <div class="flex items-center gap-2 sm:gap-4 lg:gap-5 min-w-0 lg:flex-shrink-0">

                    <button @click="sidebarOpen = true"
                        class="text-white focus:outline-none lg:hidden hover:bg-white/20 p-1.5 sm:p-2 rounded-lg transition flex-shrink-0">
                        <svg class="w-6 h-6 sm:w-7 sm:h-7 lg:w-8 lg:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>

                    <img src="{{ asset('images/LogoBPS.png') }}" alt="Logo BPS"
                        class="w-[clamp(52px,14vw,107px)] object-contain p-1 lg:p-1.5 -mr-1 sm:-mr-3 lg:-mr-6 flex-shrink-0">
                    <h2
                        class="min-w-0 text-white text-[length:clamp(11px,3.4vw,20px)] font-bold italic uppercase leading-tight lg:leading-[30px]">
                        <span class="block truncate pr-1">BADAN PUSAT STATISTIK</span>
                        <span class="block truncate pr-1">KOTA PEMATANGSIANTAR</span>
                    </h2>
                </div>

                {{-- JADIKAN SEPERTI INI --}}
                <a href="{{ route('admin.pengaturan') }}" title="Pengaturan Akun"
                    class="flex items-center gap-3 p-1 sm:p-2 -mr-1 sm:-mr-2 rounded-lg hover:bg-white/10 transition-colors duration-200 cursor-pointer min-w-0 flex-shrink-0 lg:flex-shrink">
                    <div class="text-right hidden lg:block min-w-0">
                        <p class="text-white text-[20px] font-bold leading-[34px] truncate">Selamat Datang,
                            {{ Auth::user()->name }}</p>
                        <p class="text-white text-[12px] font-semibold leading-[16px] truncate">Semoga harimu menyenangkan</p>
                    </div>
                    <img src="{{ Auth::user()->avatar_url }}" alt="User Avatar"
                        class="w-8 h-8 sm:w-9 sm:h-9 md:w-[42px] md:h-[43px] rounded-full object-cover border-2 border-white/30 shadow-md transform hover:scale-105 transition-transform duration-200 flex-shrink-0">
                </a>
            </header>

            <div class="flex-1 bg-[#F5F5F7] overflow-y-auto w-full flex flex-col">
                <div class="flex-1 p-4 md:p-8">
                    {{ $slot }}
                </div>

                <footer
                    class="bg-gradient-to-r from-[#002D72] to-[#003d8f] shadow-lg py-3 md:h-[50px] flex items-center justify-center md:justify-start md:px-5 mt-auto">
                    <p
                        class="text-white text-[12px] md:text-[17px] font-bold leading-tight md:leading-[21px] text-center md:text-left">
                        Hak Cipta &copy; 2025 BPS Kota Pematangsiantar.
                    </p>
                </footer>
            </div>
        </main>
    </div>

    @stack('scripts')
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>
</body>

</html>
