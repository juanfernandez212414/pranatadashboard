import os
import re

comments = {
    'Category.php': {
        'subjects': 'Relasi: Satu kategori memiliki banyak subjek.'
    },
    'Indicator.php': {
        'subject': 'Relasi: Indikator ini milik satu subjek tertentu.',
        'user': 'Relasi: Indikator ini dibuat atau dikelola oleh satu pengguna.',
        'narrative': 'Relasi: Indikator ini memiliki satu narasi penjelasan.'
    },
    'Narrative.php': {
        'indicator': 'Relasi: Narasi ini terkait dengan satu indikator tertentu.',
        'user': 'Relasi: Narasi ini dibuat atau diedit oleh satu pengguna.'
    },
    'Role.php': {
        'users': 'Relasi: Satu role (peran) dimiliki oleh banyak pengguna.'
    },
    'Subject.php': {
        'category': 'Relasi: Subjek ini termasuk dalam satu kategori tertentu.',
        'indicators': 'Relasi: Satu subjek memiliki banyak indikator.'
    },
    'User.php': {
        'role': 'Relasi: Pengguna ini memiliki satu role (peran) tertentu.',
        'isAdmin': 'Pengecekan: Memastikan apakah pengguna ini adalah Admin.',
        'sendPasswordResetNotification': 'Notifikasi: Mengirimkan email berisi link untuk reset password.'
    }
}

dir_path = r'c:\Herd\pranata\app\Models'

for file_name, funcs in comments.items():
    file_path = os.path.join(dir_path, file_name)
    if not os.path.exists(file_path): continue
    
    with open(file_path, 'r', encoding='utf-8') as f:
        lines = f.readlines()
        
    for i in range(len(lines)):
        match = re.search(r'^\s*public\s+function\s+([a-zA-Z0-9_]+)\(', lines[i])
        if match:
            func_name = match.group(1)
            if func_name in funcs:
                prev_line = lines[i-1].strip() if i > 0 else ''
                if not prev_line.endswith('*/') and not prev_line.startswith('//'):
                    indent = lines[i][:len(lines[i]) - len(lines[i].lstrip())]
                    lines[i] = f"{indent}// {funcs[func_name]}\n{lines[i]}"
                    
    with open(file_path, 'w', encoding='utf-8') as f:
        f.writelines(lines)

print('Selesai menambahkan komentar model!')
