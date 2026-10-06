import os
import re

docstrings = {
    'clean_text': '"""Membersihkan teks dari spasi berlebih atau karakter yang tidak diperlukan."""',
    'extract_year_from_filename': '"""Mengekstrak angka (khususnya tahun) dari nama file dokumen yang diunggah."""',
    'is_document_exist_in_qdrant': '"""Mengecek apakah dokumen dengan nama file tersebut sudah pernah diproses dan tersimpan di database vektor (Qdrant)."""',
    'task_ingest_from_files': '"""Proses latar belakang (background task) untuk mengekstrak teks dari file PDF/TXT, mengubahnya menjadi vektor (embedding), lalu menyimpannya ke Qdrant."""',
    'check_missing_files': '"""Endpoint untuk memeriksa apakah ada file yang seharusnya ada tetapi belum masuk ke database vektor."""',
    'delete_by_file': '"""Endpoint untuk menghapus semua data vektor yang berasal dari file tertentu di Qdrant."""',
    'delete_all': '"""Endpoint untuk menghapus keseluruhan koleksi data di Qdrant."""',
    'upload_ingest': '"""Endpoint API untuk menerima unggahan file, lalu menjadwalkan tugas ingesting (ekstraksi) secara otomatis di latar belakang."""',
    'home': '"""Endpoint dasar/root untuk mengecek apakah server FastAPI ini sedang hidup (aktif)."""',
    'parse_table': '"""Mengubah data JSON mentah menjadi teks berbentuk tabel atau daftar agar lebih mudah dibaca oleh model AI."""',
    'konsep_dari_nama_indikator': '"""Fungsi pembantu untuk menebak konsep/makna statistik dari kata kunci pada nama indikator (contoh: PDRB, IPM)."""',
    'get_rag_context': '"""Mencari referensi dokumen terdekat dari Qdrant (RAG - Retrieval Augmented Generation) berdasarkan query untuk diberikan kepada AI sebagai contekan."""',
    'get_groq_compact_prompt': '"""Mendapatkan template prompt (instruksi sistem) khusus untuk model AI via Groq yang membutuhkan format lebih ringkas."""',
    'build_user_prompt': '"""Menggabungkan kategori, subjek, indikator, tabel data, dan konteks referensi (RAG) menjadi satu pertanyaan utuh untuk dikirim ke AI."""',
    'strip_analysis_tag': '"""Membersihkan output teks dari AI jika secara tidak sengaja mengeluarkan tag internal seperti <analysis> atau </analysis>."""',
    'looks_like_leaked_analysis': '"""Mendeteksi apakah teks yang dihasilkan AI sepertinya memuat log pemikiran internal yang bocor."""',
    'temperature_untuk': '"""Mengembalikan nilai temperature (tingkat kreativitas AI). Model yang lebih pintar bisa diberi temperature lebih tinggi."""',
    'build_compact_prompt': '"""Menyusun versi prompt yang lebih padat (ringkas), biasanya digunakan saat memakai API OpenAI atau Groq."""',
    '_ambil': '"""Fungsi kecil pembantu (helper) untuk mengambil properti dari objek Python dengan aman."""',
    'log_token_openai': '"""Mencatat (logging) jumlah pemakaian token dan durasi pemanggilan API OpenAI ke konsol (terminal)."""',
    'generate_via_groq_fallback': '"""Menggunakan API Groq sebagai AI cadangan (fallback) seandainya API Hugging Face utama sedang down atau terlalu lambat."""',
    'call_hf': '"""Menghubungi API Hugging Face Inference Endpoint untuk meminta model AI (misal: Llama 3) membuatkan narasi/analisis."""',
    'call_narrative_provider': '"""Fungsi inti yang mengontrol AI mana (Hugging Face, OpenAI, atau Groq) yang harus dipanggil berdasarkan nama model yang dipilih user."""',
    'panggil_dengan_batas_waktu': '"""Menjalankan fungsi lain dengan batasan waktu (timeout) dalam hitungan detik agar tidak macet/hang (infinite loop)."""',
    'generate_narrative': '"""Endpoint API Utama! Menerima perintah dari web Laravel, menyusun konteks (RAG), memanggil AI, lalu mengembalikan teks narasinya ke Laravel."""'
}

file_path = r'c:\Herd\pranata\scripts\Hugging Face\main.py'

with open(file_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

out_lines = []
i = 0
while i < len(lines):
    line = lines[i]
    out_lines.append(line)
    match = re.search(r'^\s*def\s+([a-zA-Z0-9_]+)\(', line)
    if match:
        func_name = match.group(1)
        if func_name in docstrings:
            indent = line[:len(line) - len(line.lstrip())] + '    '
            # Cek apakah line berikutnya sudah docstring
            if i + 1 < len(lines) and '"""' in lines[i+1]:
                pass # sudah ada docstring
            else:
                out_lines.append(f"{indent}{docstrings[func_name]}\n")
    i += 1

with open(file_path, 'w', encoding='utf-8') as f:
    f.writelines(out_lines)

print('Selesai menambahkan komentar ke main.py!')
