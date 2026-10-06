import os
import re

comments = {
    'datapdf.blade.php': 'View untuk mengekspor (cetak) data indikator ke format PDF.',
    'forgotpassword.blade.php': 'Halaman form untuk meminta link reset password.',
    'login.blade.php': 'Halaman utama untuk login pengguna.',
    'register.blade.php': 'Halaman form registrasi pengguna baru.',
    'resetpassword.blade.php': 'Halaman form untuk memasukkan password baru.',
    'welcome.blade.php': 'Halaman sambutan/landing page aplikasi.',
    
    'dashboard.blade.php': 'Menampilkan dashboard berisi rangkuman statistik dan grafik.',
    'keloladata.blade.php': 'Halaman antarmuka untuk manajemen data (kategori, subjek, indikator).',
    'lihatdata.blade.php': 'Halaman untuk melihat daftar data yang telah dimasukkan.',
    'model.blade.php': 'Halaman pengaturan pemilihan model AI yang akan digunakan.',
    'pengaturan.blade.php': 'Halaman profil dan pengaturan akun pengguna.',
    'pengetahuan.blade.php': 'Halaman untuk mengelola basis pengetahuan (knowledge base) dokumen.',
    'pengguna.blade.php': 'Halaman bagi admin untuk mengelola daftar pengguna dan perannya.',
    'tampilandata.blade.php': 'Halaman untuk melihat detail dan visualisasi grafik dari data spesifik.',
    'tentangkami.blade.php': 'Halaman informasi tentang pembuat atau tujuan aplikasi.',
    
    'adminlayout.blade.php': 'Komponen kerangka dasar (layout) HTML untuk halaman Admin.',
    'penanggungjawablayout.blade.php': 'Komponen kerangka dasar (layout) HTML untuk halaman Penanggung Jawab.',
    'penggunalayout.blade.php': 'Komponen kerangka dasar (layout) HTML untuk halaman Pengguna Biasa.',
    
    'reset-password.blade.php': 'Template email yang dikirim untuk reset password.',
    '403.blade.php': 'Halaman error kustom ketika akses ditolak (Forbidden).'
}

dir_path = r'c:\Herd\pranata\resources\views'

for root, _, files in os.walk(dir_path):
    if 'vendor' in root: continue # Skip vendor views
    for file in files:
        if file.endswith('.blade.php'):
            file_path = os.path.join(root, file)
            file_name = file
            
            comment_text = comments.get(file_name)
            if comment_text:
                with open(file_path, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                if not content.startswith('{{--'):
                    new_content = f"{{-- {comment_text} --}}\n{content}"
                    with open(file_path, 'w', encoding='utf-8') as f:
                        f.write(new_content)

print('Selesai menambahkan komentar ke file views!')
