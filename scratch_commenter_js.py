import os

comments = {
    'admin.js': '// File utama Javascript untuk menangani interaktivitas (dropdown, modal, dll) di halaman Admin.',
    'app.js': '// File entry point utama untuk mengatur inisialisasi aplikasi (misal: AlpineJS).',
    'bootstrap.js': '// File konfigurasi dasar untuk mengatur library eksternal (seperti Axios) sebelum aplikasi dimuat.',
    'pengguna.js': '// File Javascript khusus untuk menangani fungsionalitas dan antarmuka di halaman Pengguna.',
    'pj.js': '// File Javascript khusus untuk menangani interaktivitas di halaman Penanggung Jawab.'
}

dir_path = r'c:\Herd\pranata\resources\js'

for file_name, comment_text in comments.items():
    file_path = os.path.join(dir_path, file_name)
    if not os.path.exists(file_path): continue
    
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
        
    if not content.startswith('// File'):
        new_content = f"{comment_text}\n\n{content}"
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(new_content)

print('Selesai menambahkan komentar ke file JS!')
