<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - PRANATA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

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
                <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Lupa Password</h1>
                <p class="mt-1.5 text-sm text-gray-500">Masukkan email Anda untuk menerima tautan reset.</p>
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

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="text-sm font-semibold text-gray-600">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required
                        placeholder="email@contoh.com"
                        class="w-full px-4 py-2 mt-1.5 text-gray-800 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition shadow-sm text-sm">
                </div>

                <button type="submit"
                    class="w-full py-2.5 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition-all shadow-md text-sm mt-2">
                    Kirim Tautan Reset
                </button>
            </form>

            <div class="text-xs text-center text-gray-500 mt-2">
                <a href="{{ route('login') }}" class="font-semibold text-blue-600 hover:text-blue-800 hover:underline transition">← Kembali ke Sign In</a>
            </div>
        </div>
    </div>

</body>

</html>
