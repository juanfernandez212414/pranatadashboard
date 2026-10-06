{{--
    Halaman seragam untuk SEMUA penolakan hak akses (HTTP 403): tampilan DAN pesannya sama.
    Pesan dari abort(403, '...') di controller sengaja tidak ditampilkan di halaman ini. Pesan
    pop-up (respons JSON untuk generate narasi, simpan narasi, Bangunkan AI, dll.) tidak memakai
    halaman ini dan tetap memakai pesannya sendiri.
    Gaya ditulis langsung di halaman (tanpa Vite/Tailwind) supaya tetap tampil walaupun
    aset belum di-build, dan tidak bergantung pada layout role mana pun.
--}}
@php
    $user = auth()->user();
    [$tujuan, $label] = match (true) {
        !$user => [route('login'), 'Masuk ke PRANATA'],
        $user->role_id == 1 => [route('admin.dashboard'), 'Kembali ke Dashboard'],
        $user->role_id == 3 => [route('penanggungjawab.dashboard'), 'Kembali ke Dashboard'],
        default => [route('pengguna.dashboard'), 'Kembali ke Dashboard'],
    };
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak - PRANATA</title>
    <link rel="icon" type="image/png" href="{{ asset('images/LogoPRANATA.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 24px 16px; background: #F5F5F7; color: #1f2937;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        .kartu {
            width: 100%; max-width: 440px; background: #fff; border-radius: 20px; overflow: hidden;
            text-align: center; box-shadow: 0 10px 30px rgba(0, 45, 114, .12);
        }
        .pita { height: 6px; background: linear-gradient(90deg, #002D72, #003d8f); }
        .isi { padding: 32px 28px 28px; }
        .logo { display: block; width: 84px; height: auto; margin: 0 auto 6px; }
        .kode { font-size: 12px; font-weight: 700; letter-spacing: .14em; color: #9ca3af; }
        .ikon {
            width: 64px; height: 64px; margin: 18px auto 16px; border-radius: 50%;
            background: #FEF2F2; display: flex; align-items: center; justify-content: center;
        }
        h1 { font-size: 24px; font-weight: 800; color: #002D72; margin-bottom: 10px; }
        p { font-size: 15px; line-height: 1.6; color: #4b5563; }
        p.bantuan { margin-top: 8px; font-size: 13px; color: #9ca3af; }
        .aksi { margin-top: 26px; display: flex; flex-direction: column; gap: 10px; }
        .tombol {
            display: block; padding: 12px 20px; border-radius: 12px; font-size: 15px; font-weight: 700;
            text-decoration: none; transition: opacity .2s, background-color .2s;
        }
        .utama { background: linear-gradient(90deg, #002D72, #003d8f); color: #fff; }
        .utama:hover { opacity: .9; }
        .kedua { background: #EEF2F9; color: #002D72; }
        .kedua:hover { background: #E1E8F4; }
        .kaki { margin-top: 22px; font-size: 12px; color: #9ca3af; }
    </style>
</head>

<body>
    <main class="kartu">
        <div class="pita"></div>
        <div class="isi">
            <img class="logo" src="{{ asset('images/LogoPRANATA.png') }}" alt="PRANATA">
            <div class="kode">KODE 403</div>
            <div class="ikon" aria-hidden="true">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="11" width="16" height="10" rx="2" />
                    <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                </svg>
            </div>
            <h1>Akses Ditolak</h1>
            <p>Anda tidak memiliki hak akses untuk membuka halaman ini.</p>
            <p class="bantuan">Jika menurut Anda ini keliru, silakan hubungi Admin PRANATA.</p>
            <div class="aksi">
                <a class="tombol utama" href="{{ $tujuan }}">{{ $label }}</a>
                <a class="tombol kedua" href="javascript:history.back()">Kembali ke Halaman Sebelumnya</a>
            </div>
            <div class="kaki">Badan Pusat Statistik Kota Pematangsiantar</div>
        </div>
    </main>
</body>

</html>
