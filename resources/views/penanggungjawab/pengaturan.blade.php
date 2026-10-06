{{-- resources/views/penanggungjawab/pengaturan.blade.php --}}

<x-penanggungjawablayout title="Pengaturan Akun - penanggungjawab PRANATA">

    {{-- ========================================== --}}
    {{-- MODAL NOTIFIKASI --}}
    {{-- ========================================== --}}

    {{-- Success Modal (Umum) --}}
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-cloak
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto overflow-hidden"
                @click.away="show = false" 
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4" 
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0" 
                x-transition:leave-end="opacity-0 translate-y-4">
                <div class="p-6 md:p-8 text-center">
                    <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-5 shadow-inner">
                        <svg class="w-10 h-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Berhasil!</h3>
                    <p class="text-gray-600 text-sm md:text-base mb-6">{{ session('success') }}</p>

                    {{-- Progress Bar --}}
                    <div class="w-full bg-gray-100 rounded-full h-1.5 mb-8 overflow-hidden">
                        <div class="bg-green-500 h-1.5 rounded-full transition-all duration-[4000ms] ease-linear"
                            x-bind:style="show ? 'width: 0%' : 'width: 100%'"></div>
                    </div>

                    <div class="flex justify-center">
                        <button @click="show = false"
                            class="w-full px-6 py-3 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 shadow-lg transition-colors">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Success Modal untuk Update Password --}}
    @if (session('success-password'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-cloak
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto overflow-hidden"
                @click.away="show = false" 
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4" 
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0" 
                x-transition:leave-end="opacity-0 translate-y-4">
                <div class="p-6 md:p-8 text-center">
                    <div class="w-20 h-20 rounded-full bg-blue-100 flex items-center justify-center mx-auto mb-5 shadow-inner">
                        <svg class="w-10 h-10 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Keamanan Diperbarui!</h3>
                    <p class="text-gray-600 text-sm md:text-base mb-6">{{ session('success-password') }}</p>

                    {{-- Progress Bar --}}
                    <div class="w-full bg-gray-100 rounded-full h-1.5 mb-8 overflow-hidden">
                        <div class="bg-blue-500 h-1.5 rounded-full transition-all duration-[4000ms] ease-linear"
                            x-bind:style="show ? 'width: 0%' : 'width: 100%'"></div>
                    </div>

                    <div class="flex justify-center">
                        <button @click="show = false"
                            class="w-full px-6 py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 shadow-lg transition-colors">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Error Modal --}}
    @if ($errors->any())
        <div x-data="{ show: true }" x-show="show" x-cloak
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto overflow-hidden"
                @click.away="show = false" 
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4" 
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0" 
                x-transition:leave-end="opacity-0 translate-y-4">
                <div class="p-6 md:p-8 text-center">
                    <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-5 shadow-inner">
                        <svg class="w-10 h-10 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Oops! Ada Kesalahan</h3>
                    
                    {{-- Box Pesan Error --}}
                    <div class="bg-red-50 rounded-xl p-4 mb-8 text-left inline-block w-full border border-red-100">
                        <ul class="space-y-2">
                            @foreach ($errors->all() as $error)
                                <li class="flex items-start text-red-700 text-sm md:text-base">
                                    <svg class="h-5 w-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ $error }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="flex justify-center">
                        <button @click="show = false"
                            class="w-full px-6 py-3 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 shadow-lg transition-colors">
                            Mengerti
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif


    {{-- ========================================== --}}
    {{-- KONTEN HALAMAN UTAMA --}}
    {{-- ========================================== --}}

    {{-- Header Halaman --}}
    <div class="mb-8">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2 flex items-center gap-3">
            <svg class="w-8 h-8 text-[#002D72]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                </path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Pengaturan Akun
        </h1>
        <p class="text-gray-500 text-sm md:text-base">Kelola informasi profil pribadi dan tingkatkan keamanan
            kredensial login Anda.</p>
    </div>

    {{-- Perubahan di sini: Menggunakan items-start agar kotak mengikuti tinggi natural kontennya --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- KOLOM KIRI: Form Informasi Profil --}}
        <div class="lg:col-span-7">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gray-50/50 border-b border-gray-100 px-6 py-5">
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#002D72]" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Informasi Profil Pribadi
                    </h2>
                </div>

                <form method="POST" action="{{ route('penanggungjawab.profil.update') }}"
                    enctype="multipart/form-data" class="p-6 sm:p-8">
                    @csrf @method('PATCH')

                    <div class="space-y-8">
                        {{-- Upload Foto Profil --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-4">Foto Profil</label>
                            <div class="flex flex-col sm:flex-row items-center gap-6">
                                <div class="relative group shrink-0">
                                    <div
                                        class="absolute inset-0 bg-[#002D72] rounded-full blur-md opacity-20 group-hover:opacity-40 transition-opacity">
                                    </div>
                                    <img src="{{ Auth::user()->avatarUrl }}" alt="Foto Profil"
                                        class="relative h-28 w-28 rounded-full object-cover border-4 border-white shadow-md z-10">
                                </div>
                                <div class="flex-1 w-full text-center sm:text-left">
                                    <div class="relative">
                                        <input type="file" name="photo" id="photo"
                                            accept="image/jpeg,image/png,image/jpg"
                                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-[#002D72] hover:file:bg-blue-100 transition-all duration-200 border border-gray-200 rounded-xl p-1.5 cursor-pointer bg-gray-50">
                                    </div>
                                    <p
                                        class="text-[11px] text-gray-500 mt-2 flex items-center justify-center sm:justify-start gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Format JPG, PNG. Ukuran maksimal 2MB. Biarkan kosong jika tidak ingin mengubah.
                                    </p>
                                    @error('photo')
                                        <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Input Nama --}}
                        <div>
                            <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Nama
                                Lengkap</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                        </path>
                                    </svg>
                                </div>
                                <input type="text" name="name" id="name"
                                    value="{{ old('name', Auth::user()->name) }}"
                                    class="bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-[#002D72] focus:border-transparent focus:bg-white block w-full pl-11 p-3 transition-all"
                                    required placeholder="Masukkan nama lengkap Anda">
                            </div>
                            @error('name')
                                <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="pt-4 mt-6 border-t border-gray-100 flex justify-end">
                        <button type="submit"
                            class="w-full sm:w-auto inline-flex justify-center items-center gap-2 py-2.5 px-6 border border-transparent shadow-md text-sm font-bold rounded-xl text-white bg-gradient-to-r from-[#002D72] to-[#004B9C] hover:from-[#004B9C] hover:to-[#002D72] focus:ring-2 focus:ring-offset-2 focus:ring-[#002D72] transition-all duration-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                            Simpan Perubahan Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- KOLOM KANAN: Form Keamanan & Login --}}
        <div class="lg:col-span-5">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gray-50/50 border-b border-gray-100 px-6 py-5">
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                            </path>
                        </svg>
                        Keamanan & Login
                    </h2>
                </div>

                <div class="p-6 sm:p-8">
                    @if (Auth::user()->password)
                        <form method="POST" action="{{ route('penanggungjawab.password.update') }}"
                            class="space-y-5">
                            @csrf @method('PUT')

                            {{-- Input Email --}}
                            <div>
                                <label for="email"
                                    class="block text-xs font-semibold text-gray-700 mb-1.5 uppercase tracking-wide">Alamat
                                    Email</label>
                                <div class="relative">
                                    <div
                                        class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                            </path>
                                        </svg>
                                    </div>
                                    <input type="email" name="email" id="email"
                                        value="{{ old('email', Auth::user()->email) }}"
                                        class="bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-[#002D72] focus:border-transparent focus:bg-white block w-full pl-11 p-3 transition-all"
                                        required>
                                </div>
                                @error('email')
                                    <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <hr class="border-gray-100 my-4">

                            {{-- Password Lama --}}
                            <div>
                                <label for="current_password"
                                    class="block text-xs font-semibold text-gray-700 mb-1.5 uppercase tracking-wide">Password
                                    Saat Ini</label>
                                <div class="relative">
                                    <div
                                        class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                            </path>
                                        </svg>
                                    </div>
                                    <input type="password" name="current_password" id="current_password"
                                        placeholder="••••••••"
                                        class="bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-[#002D72] focus:border-transparent focus:bg-white block w-full pl-11 p-3 transition-all"
                                        required>
                                </div>
                                @error('current_password')
                                    <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Password Baru --}}
                            <div>
                                <label for="password"
                                    class="block text-xs font-semibold text-gray-700 mb-1.5 uppercase tracking-wide">Password
                                    Baru</label>
                                <div class="relative">
                                    <div
                                        class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z">
                                            </path>
                                        </svg>
                                    </div>
                                    <input type="password" name="password" id="password" placeholder="••••••••"
                                        class="bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-[#002D72] focus:border-transparent focus:bg-white block w-full pl-11 p-3 transition-all"
                                        required>
                                </div>
                                @error('password')
                                    <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Konfirmasi Password --}}
                            <div>
                                <label for="password_confirmation"
                                    class="block text-xs font-semibold text-gray-700 mb-1.5 uppercase tracking-wide">Konfirmasi
                                    Password</label>
                                <div class="relative">
                                    <div
                                        class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                            </path>
                                        </svg>
                                    </div>
                                    <input type="password" name="password_confirmation" id="password_confirmation"
                                        placeholder="••••••••"
                                        class="bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-[#002D72] focus:border-transparent focus:bg-white block w-full pl-11 p-3 transition-all"
                                        required>
                                </div>
                            </div>

                            <div class="pt-4 mt-6 border-t border-gray-100">
                                <button type="submit"
                                    class="w-full inline-flex justify-center items-center gap-2 py-3 px-6 border border-transparent shadow-md text-sm font-bold rounded-xl text-white bg-gradient-to-r from-[#002D72] to-[#004B9C] hover:from-[#004B9C] hover:to-[#002D72] focus:ring-2 focus:ring-offset-2 focus:ring-[#002D72] transition-all duration-300">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Perbarui Keamanan
                                </button>
                            </div>
                        </form>
                    @else
                        {{-- Pesan untuk pengguna yang login via Social Media --}}
                        <div class="bg-blue-50 border border-blue-100 p-5 rounded-xl text-center">
                            <div
                                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 mb-4">
                                {{-- Logo Google --}}
                                <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 mb-2">Login Pihak Ketiga Terdeteksi</h3>
                            <p class="text-sm text-gray-600 leading-relaxed">
                                Anda login menggunakan akun pihak ketiga. Demi alasan keamanan dan
                                sinkronisasi, pengaturan email dan password dikelola langsung oleh penyedia layanan.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>

</x-penanggungjawablayout>
