{{-- Halaman utama untuk login pengguna. --}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PRANATA</title>

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

    <div class="relative flex items-center justify-center min-h-screen px-4 overflow-hidden">
        
        <div class="absolute inset-0 z-0">
            <img src="{{ asset('assets/img/hero-bg-light.webp') }}" alt="Background" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-white/75"></div>
        </div>
        <div class="relative z-10 w-full max-w-md p-8 space-y-6 bg-white/80 backdrop-blur-md rounded-2xl shadow-xl border border-white/60">

            <div class="text-center">
                <div class="flex justify-center mb-3">
                    <img src="{{ asset('images/LogoPRANATA.png') }}" alt="Logo PRANATA" class="h-16 w-auto object-contain">
                </div>
                <h1 class="text-3xl font-bold text-gray-800 tracking-tight">PRANATA</h1>
                <p class="mt-1.5 text-sm text-gray-500">Masuk untuk mengakses akun Anda</p>
            </div>

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm shadow-sm" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm shadow-sm" role="alert">
                    @foreach ($errors->all() as $error)
                        {{ $error }}
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
                @csrf
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

                <div class="text-right">
                    <a href="{{ url('/forgot-password') }}" class="text-xs text-blue-600 hover:text-blue-800 hover:underline transition font-medium">
                        Lupa Password?
                    </a>
                </div>

                <button type="submit"
                    class="w-full py-2.5 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition-all shadow-md text-sm">
                    Masuk
                </button>
            </form>

            <div class="flex items-center justify-center py-1">
                <hr class="w-full border-gray-200">
                <span class="px-3 text-xs text-gray-400 whitespace-nowrap">atau lanjutkan dengan</span>
                <hr class="w-full border-gray-200">
            </div>

            <div class="flex justify-center">
                <a href="{{ route('auth.google.redirect') }}"
                    class="w-full py-2.5 flex items-center justify-center gap-3 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-all shadow-sm text-sm">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" class="w-4 h-4">
                    <span class="font-semibold">Sign in with Google</span>
                </a>
            </div>

            <div class="text-xs text-center text-gray-500 mt-2">
                Belum punya akun?
                <a href="{{ url('/register') }}" class="font-semibold text-blue-600 hover:text-blue-800 hover:underline transition">Daftar gratis</a>
            </div>
        </div>
    </div>

</body>

</html>
