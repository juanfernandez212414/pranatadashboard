{{-- Halaman form registrasi pengguna baru. --}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PRANATA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('images/LogoPRANATA.png') }}">
    
    <style>
        body {
            font-family: 'Nunito', sans-serif;
        }
    </style>
</head>

<body class="antialiased text-gray-800 bg-white">

    <div class="relative flex items-center justify-center min-h-screen px-4 py-8 overflow-hidden">

        <div class="absolute inset-0 z-0">
            <img src="{{ asset('assets/img/hero-bg-light.webp') }}" alt="Background" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-white/75"></div>
        </div>
        <div class="relative z-10 w-full max-w-md p-8 space-y-6 bg-white/80 backdrop-blur-md rounded-2xl shadow-xl border border-white/60">

            <div class="text-center">
                <div class="flex justify-center mb-3">
                    <img src="{{ asset('images/LogoPRANATA.png') }}" alt="Logo PRANATA" class="h-16 w-auto object-contain">
                </div>
                <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Buat Akun Baru</h1>
                <p class="mt-1.5 text-sm text-gray-500">Bergabunglah bersama kami untuk memulai</p>
            </div>

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm shadow-sm" role="alert">
                    <strong class="font-bold">Oops! Terjadi kesalahan:</strong>
                    <ul class="mt-2 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register.post') }}" class="space-y-4">
                @csrf <div>
                    <label for="name" class="text-sm font-semibold text-gray-600">Nama Lengkap</label>
                    <input type="text" id="name" name="name" placeholder="John Doe"
                        value="{{ old('name') }}" required
                        class="w-full px-4 py-2 mt-1.5 text-gray-800 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition shadow-sm text-sm">
                </div>
                
                <div>
                    <label for="email" class="text-sm font-semibold text-gray-600">Email</label>
                    <input type="email" id="email" name="email" placeholder="email@contoh.com"
                        value="{{ old('email') }}" required
                        class="w-full px-4 py-2 mt-1.5 text-gray-800 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition shadow-sm text-sm">
                </div>
                
                <div>
                    <label for="password" class="text-sm font-semibold text-gray-600">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required
                        class="w-full px-4 py-2 mt-1.5 text-gray-800 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition shadow-sm text-sm">
                </div>
                
                <div>
                    <label for="password_confirmation" class="text-sm font-semibold text-gray-600">Konfirmasi Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                        placeholder="••••••••" required
                        class="w-full px-4 py-2 mt-1.5 text-gray-800 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition shadow-sm text-sm">
                </div>

                <button type="submit"
                    class="w-full py-2.5 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition-all shadow-md text-sm mt-2">
                    Daftar
                </button>
            </form>

            <div class="text-xs text-center text-gray-500 mt-2">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-semibold text-blue-600 hover:text-blue-800 hover:underline transition">Sign In</a>
            </div>
        </div>
    </div>

</body>

</html>
