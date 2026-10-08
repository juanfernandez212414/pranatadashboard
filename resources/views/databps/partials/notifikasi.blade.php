{{-- Pesan sukses/gagal untuk halaman Data API BPS. $galat = kegagalan saat memuat data dari API. --}}
@if (session('success'))
    <div class="bg-green-100 border border-green-300 text-green-700 px-4 py-3 rounded-lg" role="alert">
        <span class="font-medium">{{ session('success') }}</span>
    </div>
@endif
@if (session('error'))
    <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-lg" role="alert">
        <span class="font-medium">{{ session('error') }}</span>
    </div>
@endif
@if ($galat ?? null)
    <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-lg" role="alert">
        <span class="font-bold">Gagal mengambil data dari WebAPI BPS.</span>
        <span class="block text-sm mt-1">{{ $galat }}</span>
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
