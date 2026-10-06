{-- Halaman form untuk memasukkan password baru. --}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - PRANATA</title>
    
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
                <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Reset Password</h1>
                <p class="mt-1.5 text-sm text-gray-500">Masukkan password baru Anda di bawah ini.</p>
            </div>

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm shadow-sm" role="alert">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email ?? old('email') }}">

                <div>
                    <label for="password" class="text-sm font-semibold text-gray-600">Password Baru</label>
                    <input type="password" id="password" name="password" required placeholder="••••••••"
                        class="w-full px-4 py-2 mt-1.5 text-gray-800 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition shadow-sm text-sm">
                </div>
                
                <div>
                    <label for="password_confirmation" class="text-sm font-semibold text-gray-600">Konfirmasi Password Baru</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="••••••••"
                        class="w-full px-4 py-2 mt-1.5 text-gray-800 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition shadow-sm text-sm">
                </div>
                
                <button type="submit"
                    class="w-full py-2.5 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition-all shadow-md text-sm mt-2">
                    Simpan Password
                </button>
            </form>
            
        </div>
    </div>
</body>

</html>
