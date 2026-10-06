"""
=============================================================
  LOCAL INGEST SCRIPT V3 - PRANATA AI
=============================================================
  Sama persis dengan local_ingest_v2.py, HANYA overlap-nya yang
  berbeda, dan disimpan ke COLLECTION TERPISAH sehingga v1 (dipakai
  Space) dan v2 tidak tersentuh sama sekali.

  Perbandingan ketiga versi:
            chunk / overlap   rasio overlap   titik potong
    v1      2000 / 400        20%             spasi sembarang
    v2      1000 / 150        15%             akhir kalimat
    v3      1000 / 200        20%             akhir kalimat
  v3 vs v2 -> mengukur efek besar overlap (satu-satunya yang beda).

  - Collection : bps_knowledge_bge_m3_v3
  - Log lokal  : processed_log_bge_m3_v3.txt
  - Mode --uji / --evaluasi membandingkan v1, v2, dan v3 sekaligus.

  CARA PAKAI:
  1. Pastikan .env di folder scripts/ sudah diisi (sama dengan v1)
  2. Install dependencies:
       pip install -r requirements_ingest.txt
  3. Jalankan:
       python local_ingest_v3.py
     atau untuk file tertentu:
       python local_ingest_v3.py --file "nama_file.pdf"
     atau untuk memproses ulang (mengganti data lama file itu):
       python local_ingest_v3.py --force
     atau untuk reset log v3:
       python local_ingest_v3.py --reset
     atau untuk cek status collection v3:
       python local_ingest_v3.py --check
     atau untuk membandingkan hasil pencarian v1, v2, v3:
       python local_ingest_v3.py --uji "Tingkat Pengangguran Terbuka"
     atau untuk mengukur v1, v2, v3 dengan daftar kasus uji:
       python local_ingest_v3.py --evaluasi
       python local_ingest_v3.py --evaluasi kasus_lain.json --k 3
=============================================================
"""

import os
import sys
import io

# Force stdout to UTF-8 to support emojis in Windows Terminal
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

import re
import json
import time
import uuid
import warnings
import argparse
import torch
from pathlib import Path
from dotenv import load_dotenv

load_dotenv(dotenv_path=Path(__file__).parent / ".env")

warnings.filterwarnings("ignore")

# =============================================================
# KONFIGURASI - Sesuaikan jika path berbeda
# =============================================================
QDRANT_URL      = os.getenv("QDRANT_URL")
QDRANT_API_KEY  = os.getenv("QDRANT_API_KEY")
COLLECTION_NAME = "bps_knowledge_bge_m3_v3"
COLLECTION_V1   = "bps_knowledge_bge_m3_v1"   # hanya dibaca, untuk mode --uji/--evaluasi
COLLECTION_V2   = "bps_knowledge_bge_m3_v2"   # hanya dibaca, untuk mode --uji/--evaluasi
EMBEDDING_MODEL = "BAAI/bge-m3"

DOKUMEN_PATH = Path(r"C:\Herd\pranata\storage\app\public\dokumen_bps")
# Log terpisah dari v1 dan v2: log v1 dipakai bersama oleh Laravel (PengetahuanController),
# jadi kalau v3 memakai log yang sama, semua file akan dianggap "sudah" dan dilewati.
LOG_PATH     = Path(r"C:\Herd\pranata\storage\app\processed_log_bge_m3_v3.txt")

# Pemotongan chunk v3 = v2 dengan overlap 200 (rasio 20%, sama dengan v1 2000/400).
# Diukur langsung pada 305 PDF (tanpa embedding), dibanding v2 (1000/150):
#   kalimat definisi utuh dalam satu chunk          : 98,5% -> 99,0%
#   definisi + kalimat sesudahnya utuh              : 85,9% -> 88,7%
#   jumlah chunk                                    : 50.315 -> 50.696 (+0,8%)
# clean_text() meratakan semua baris baru, jadi pemisah yang benar-benar bekerja adalah
# ". " — potongan jatuh di akhir kalimat, bukan di tengah kata.
CHUNK_SIZE    = 1000
CHUNK_OVERLAP = 200
SEPARATORS    = ["\n\n", "\n", ". ", "; ", ", ", " ", ""]

# Qdrant menolak request yang terlalu besar, jadi point dikirim bertahap.
UPSERT_BATCH = 256

# Threshold yang sedang dipakai main.py (get_rag_context). Di mode --uji hanya dipakai
# untuk MENANDAI hasil yang akan terbuang, tidak untuk menyaring.
THRESHOLD_SAAT_INI = 0.40

# Jumlah potongan yang di-embed sekaligus di GPU. Bawaan sentence-transformers 32; diturunkan
# ke 16 karena di GPU 4 GB (GTX 1650) setting 32 membuat memori GPU hampir penuh dan driver
# sempat di-reset Windows di tengah jalan. Diukur di GTX 1650 dengan 256 potongan:
#   batch 32: 21,3 detik, memori puncak 3,23 GB
#   batch 16: 18,9 detik, memori puncak 2,72 GB  (lebih cepat DAN lebih hemat, vektor identik)
# Catatan: FP16 (setengah-presisi) sengaja TIDAK dipakai. Di GTX seri 16 yang tidak punya
# tensor core, FP16 terukur 4,5x LEBIH LAMBAT (95,3 detik untuk 256 potongan yang sama).
BATCH_EMBEDDING = 16

# Total waktu per tahap untuk file yang benar-benar diproses (bukan yang dilewati).
WAKTU = {"baca": 0.0, "embedding": 0.0, "upload": 0.0}

# =============================================================
# INISIALISASI
# =============================================================
from qdrant_client import QdrantClient, models
from langchain_community.document_loaders import PyMuPDFLoader
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_huggingface import HuggingFaceEmbeddings

print("=" * 60)
print("  PRANATA LOCAL INGEST SCRIPT V3")
print("=" * 60)

if not QDRANT_URL or not QDRANT_API_KEY:
    print("\n❌ ERROR: QDRANT_URL atau QDRANT_API_KEY belum diisi di .env!")
    print(f"   Buat file: {Path(__file__).parent / '.env'}")
    print("   Isi dengan:\n   QDRANT_URL=https://...\n   QDRANT_API_KEY=...")
    exit(1)

if not DOKUMEN_PATH.exists():
    print(f"\n⚠️  Folder dokumen belum ada, membuat otomatis...")
    DOKUMEN_PATH.mkdir(parents=True, exist_ok=True)
    print(f"   📁 Dibuat: {DOKUMEN_PATH}")


# =============================================================
# FUNGSI SETUP DATABASE - Pastikan Collection Qdrant Siap
# =============================================================
def setup_qdrant_database():
    """
    Memastikan koneksi Qdrant berhasil, collection sudah ada,
    dan payload index sudah dibuat SEBELUM proses ingest dimulai.
    """
    print(f"\n🔌 [1/3] Menghubungkan ke Qdrant Cloud...")
    print(f"   URL: {QDRANT_URL}")

    try:
        client = QdrantClient(url=QDRANT_URL, api_key=QDRANT_API_KEY, timeout=30)
    except Exception as e:
        print(f"   ❌ GAGAL koneksi Qdrant: {e}")
        exit(1)

    # Test koneksi nyata - coba ambil daftar collections
    try:
        existing_collections = [c.name for c in client.get_collections().collections]
        print(f"   ✅ Terhubung! Collections di server: {existing_collections if existing_collections else '(kosong)'}")
    except Exception as e:
        print(f"   ❌ GAGAL berkomunikasi dengan Qdrant: {e}")
        print(f"   Pastikan QDRANT_URL dan QDRANT_API_KEY sudah benar!")
        exit(1)

    # Cek apakah collection target sudah ada
    print(f"\n📦 [2/3] Memeriksa collection '{COLLECTION_NAME}'...")

    if client.collection_exists(COLLECTION_NAME):
        # Collection sudah ada - tampilkan info detail
        try:
            info = client.get_collection(COLLECTION_NAME)
            total_vectors = info.points_count
            status = info.status
            print(f"   ✅ Collection sudah ada!")
            print(f"   📊 Status       : {status}")
            print(f"   📊 Total vektor  : {total_vectors:,}")
            print(f"   📊 Dimensi       : {info.config.params.vectors.size}")
            print(f"   📊 Jarak metrik  : {info.config.params.vectors.distance}")
        except Exception as e:
            print(f"   ✅ Collection ada (detail tidak bisa dibaca: {e})")
    else:
        # Collection belum ada - buat baru
        print(f"   ⚠️  Collection '{COLLECTION_NAME}' BELUM ADA. Membuat baru...")
        try:
            client.create_collection(
                collection_name=COLLECTION_NAME,
                vectors_config=models.VectorParams(
                    size=1024,
                    distance=models.Distance.COSINE
                )
            )
            print(f"   ✅ Collection '{COLLECTION_NAME}' berhasil dibuat!")
            print(f"   📊 Dimensi: 1024 | Metrik: COSINE")
        except Exception as e:
            print(f"   ❌ GAGAL membuat collection: {e}")
            exit(1)

    # Pastikan payload index ada untuk pencarian berdasarkan filename
    print(f"\n🔑 [3/3] Memastikan payload index 'metadata.source'...")
    try:
        client.create_payload_index(
            collection_name=COLLECTION_NAME,
            field_name="metadata.source",
            field_schema=models.PayloadSchemaType.KEYWORD
        )
        print(f"   ✅ Payload index berhasil dibuat/dipastikan siap.")
    except Exception as e:
        err_str = str(e).lower()
        if "already exists" in err_str or "409" in err_str:
            print(f"   ✅ Payload index sudah ada sebelumnya.")
        else:
            print(f"   ⚠️  Peringatan payload index: {e}")
            print(f"   (Proses ingest tetap bisa berjalan)")

    print(f"\n{'=' * 60}")
    print(f"   ✅ DATABASE SIAP! Lanjut ke proses embedding...")
    print(f"{'=' * 60}\n")

    return client


# Jalankan setup database
client_qdrant = setup_qdrant_database()

device = "cuda" if torch.cuda.is_available() else "cpu"
print(f"\nLoading Model Embedding: {EMBEDDING_MODEL}")
print(f"Device: {device.upper()} | Batch embedding: {BATCH_EMBEDDING}")
print("(Pertama kali download model, sabar ya...)\n")

try:
    embedding_model = HuggingFaceEmbeddings(
        model_name=EMBEDDING_MODEL,
        model_kwargs={"device": device},
        encode_kwargs={"normalize_embeddings": True, "batch_size": BATCH_EMBEDDING},
        cache_folder=str(Path(__file__).parent / ".cache_models")
    )
    print("OK Model Embedding Siap!\n")
except Exception as e:
    print(f"GAGAL load model embedding: {e}")
    exit(1)


# =============================================================
# HELPER FUNCTIONS
# =============================================================
def clean_text(text):
    text = re.sub(r'(\w+)-\n(\w+)', r'\1\2', text)
    text = re.sub(r'\n\s*\d{1,3}\s*\n', '\n', text)
    text = re.sub(r'(?<!\n)\n(?!\n)', ' ', text)
    text = re.sub(r'\s+', ' ', text)
    text = text.replace('\x00', '')
    return text.strip()


def extract_year_from_filename(filename):
    match = re.search(r'\d{4}', filename)
    return int(match.group(0)) if match else 0


def filter_file(filename):
    return models.Filter(must=[
        models.FieldCondition(key="metadata.source", match=models.MatchValue(value=filename))
    ])


def is_in_qdrant(filename):
    try:
        res = client_qdrant.scroll(
            collection_name=COLLECTION_NAME,
            scroll_filter=filter_file(filename),
            limit=1
        )
        return len(res[0]) > 0
    except Exception:
        return False


def read_log():
    if not LOG_PATH.exists():
        return set()
    with open(LOG_PATH, "r", encoding="utf-8") as f:
        return set(line.strip() for line in f if line.strip())


def write_log(filename):
    LOG_PATH.parent.mkdir(parents=True, exist_ok=True)
    with open(LOG_PATH, "a", encoding="utf-8") as f:
        f.write(filename + "\n")


def buat_splitter():
    return RecursiveCharacterTextSplitter(
        chunk_size=CHUNK_SIZE,
        chunk_overlap=CHUNK_OVERLAP,
        separators=SEPARATORS,
        keep_separator="end",  # titik ikut di akhir kalimat, bukan di awal chunk berikutnya
    )


def upsert_bertahap(points, filename):
    """Kirim point per UPSERT_BATCH. Kalau salah satu tahap gagal, point yang SUDAH
    terkirim dihapus lagi. Tanpa ini, PDF yang tersimpan setengah jadi akan dianggap
    "sudah ada" oleh is_in_qdrant() dan tidak pernah dilengkapi."""
    sent_ids = []
    try:
        for i in range(0, len(points), UPSERT_BATCH):
            batch = points[i:i + UPSERT_BATCH]
            sent_ids.extend(p.id for p in batch)  # dicatat SEBELUM dikirim, untuk jaga-jaga
            client_qdrant.upsert(collection_name=COLLECTION_NAME, points=batch)
    except BaseException:  # termasuk Ctrl+C, supaya menghentikan skrip tidak meninggalkan PDF setengah jadi
        try:
            client_qdrant.delete(
                collection_name=COLLECTION_NAME,
                points_selector=models.PointIdsList(points=sent_ids)
            )
            print(f"   🧹 Sisa data {filename} yang sempat tersimpan sudah dihapus.")
        except Exception as cleanup_error:
            print(f"   ⚠️  Gagal membersihkan sisa data {filename}: {cleanup_error}")
        raise


def ingest_pdf(pdf_path, text_splitter, force=False):
    filename = pdf_path.name

    if not force:
        if filename in read_log():
            print(f"   SKIP (log lokal): {filename}")
            return "skip"
        if is_in_qdrant(filename):
            print(f"   SKIP (Qdrant)   : {filename}")
            write_log(filename)
            return "skip"

    print(f"   PROSES: {filename}")
    try:
        t_mulai = time.perf_counter()
        loader = PyMuPDFLoader(str(pdf_path))
        raw_docs = loader.load()
        if not raw_docs:
            print(f"   SKIP: File kosong/tidak terbaca.")
            return "skip"

        year = extract_year_from_filename(filename)
        cleaned = []
        for doc in raw_docs:
            doc.page_content = clean_text(doc.page_content)
            if not doc.page_content:
                continue
            doc.metadata.update({"source": filename, "year": year, "page": doc.metadata.get("page", 0) + 1})
            cleaned.append(doc)

        if not cleaned:
            print(f"   SKIP: Tidak ada teks yang bisa diekstrak.")
            return "skip"

        splits = [d for d in text_splitter.split_documents(cleaned) if d.page_content.strip()]
        print(f"   -> {len(splits)} chunks dari {len(cleaned)} halaman")
        if not splits:
            print(f"   GAGAL: Tidak ada vektor.")
            return "gagal"

        t_baca = time.perf_counter()

        # Embedding satu PDF sekaligus. Untuk bge-m3 hasilnya identik dengan embed_query
        # per chunk (tidak ada awalan instruksi), hanya jauh lebih cepat.
        vectors = embedding_model.embed_documents([d.page_content for d in splits])
        if device == "cuda":
            # Kembalikan memori GPU yang ditahan PyTorch setelah tiap PDF, supaya pemakaian
            # tidak terus menumpuk mendekati batas 4 GB selama proses berjam-jam.
            torch.cuda.empty_cache()
        points = [
            models.PointStruct(
                id=str(uuid.uuid4()),
                vector=vector,
                payload={"page_content": doc.page_content, "metadata": doc.metadata}
            )
            for doc, vector in zip(splits, vectors)
        ]
        t_embed = time.perf_counter()

        # --force berarti MENGGANTI: hapus data lama file ini dulu. Di v1, --force menambah
        # salinan kedua sehingga hasil pencarian terisi duplikat.
        if force and is_in_qdrant(filename):
            client_qdrant.delete(collection_name=COLLECTION_NAME, points_selector=filter_file(filename))
            print(f"   ♻️  Data lama {filename} dihapus, diganti dengan yang baru.")

        upsert_bertahap(points, filename)
        t_upload = time.perf_counter()
        write_log(filename)

        WAKTU["baca"] += t_baca - t_mulai
        WAKTU["embedding"] += t_embed - t_baca
        WAKTU["upload"] += t_upload - t_embed
        print(f"   OK Sukses! {len(points)} vektor tersimpan. "
              f"⏱️ baca {t_baca - t_mulai:.0f}s | embedding {t_embed - t_baca:.0f}s | upload {t_upload - t_embed:.0f}s")
        return "ok"

    except Exception as e:
        # "CUDA error: ..." (misalnya illegal memory access setelah driver GPU di-reset Windows)
        # merusak koneksi ke GPU secara PERMANEN untuk sisa proses ini: semua file berikutnya
        # akan gagal seketika. Beri tanda khusus supaya loop utama berhenti, bukan terus
        # "maju" menghitung kegagalan. (CUDA out of memory tidak termasuk; itu bisa pulih.)
        if "CUDA error" in str(e):
            print(f"   ERROR GPU: {e}")
            return "gpu_rusak"
        print(f"   ERROR: {e}")
        return "gagal"


PESAN_GPU_RUSAK = (
    "\n🛑 GPU mengalami error fatal (biasanya karena driver GPU di-reset oleh Windows).\n"
    "   Setelah ini SEMUA file berikutnya pasti gagal, jadi proses dihentikan di sini.\n"
    "   Data aman: file yang gagal belum terunggah dan tidak tercatat di log.\n"
    "   Jalankan ulang skripnya; file yang sudah selesai otomatis dilewati.\n"
    "   Kalau sering terulang, beri jeda agar GPU dingin dan pastikan ventilasi laptop lega."
)


# =============================================================
# MODE UJI - bandingkan hasil pencarian v1, v2, v3
# =============================================================
_ATAS_DASAR_HARGA_RE = re.compile(r"\batas dasar harga (berlaku|konstan)\b", re.IGNORECASE)


def konsep_dari_nama_indikator(nama):
    """SALINAN PERSIS dari main.py — harus tetap sama supaya mode --uji/--evaluasi memakai
    query yang sama dengan sistem produksi. Mengambil inti konsep dari nama indikator yang
    sering berupa judul tabel ("Jumlah Pegawai Negeri Sipil Menurut Jabatan ..." -> "Pegawai Negeri Sipil")."""
    teks = nama.strip()
    adh = _ATAS_DASAR_HARGA_RE.search(teks)
    teks = _ATAS_DASAR_HARGA_RE.sub(" ", teks)
    teks = re.sub(r"^\s*\[[^\]]*\]\s*", "", teks)
    teks = re.sub(r"\s*\((?![A-Z][A-Z0-9]{0,5}\))[^)]*\)", "", teks)
    teks = re.sub(r"\s+(menurut|berdasarkan)\s+.*$", "", teks, flags=re.IGNORECASE)
    teks = re.sub(r"\s+per\s+(kecamatan|kelurahan|desa|kabupaten|kota|wilayah)\b.*$", "", teks, flags=re.IGNORECASE)
    teks = re.sub(r"\s+hasil\s+.*$", "", teks, flags=re.IGNORECASE)
    teks = re.sub(r"^(jumlah|banyaknya)\s+", "", teks, flags=re.IGNORECASE)
    teks = re.sub(r"\b(triwulanan|tahunan|bulanan)\b", " ", teks, flags=re.IGNORECASE)
    teks = re.sub(r"\b(19|20)\d{2}\b", " ", teks)
    teks = re.sub(r"\s{2,}", " ", teks).strip(" ,-")
    if adh:
        teks = f"{teks} {adh.group(0)}".strip()
    return teks or nama.strip()


def buat_query(indikator):
    """Query RAG persis seperti di main.py (generate_narrative)."""
    return f"Definisi konsep {konsep_dari_nama_indikator(indikator)} dan cara interpretasinya menurut BPS."


def cari(collection, vektor, k):
    try:
        return client_qdrant.query_points(
            collection_name=collection, query=vektor, limit=k, with_payload=True
        ).points
    except AttributeError:  # qdrant-client versi lama
        return client_qdrant.search(
            collection_name=collection, query_vector=vektor, limit=k, with_payload=True
        )


def uji_retrieval(indikator, k):
    # Query disusun PERSIS seperti di main.py (generate_narrative), supaya hasilnya
    # mewakili referensi yang benar-benar diterima model saat membuat narasi.
    query = buat_query(indikator)
    print("🔎 MODE: Uji Retrieval v1 vs v2 vs v3")
    print(f"   Query: {query}")
    print(f"   Semua hasil ditampilkan (tidak disaring). Threshold main.py saat ini: {THRESHOLD_SAAT_INI:.2f}")

    vektor = embedding_model.embed_query(query)

    for label, nama in (("V1 (2000/400)", COLLECTION_V1), ("V2 (1000/150)", COLLECTION_V2),
                        (f"V3 ({CHUNK_SIZE}/{CHUNK_OVERLAP})", COLLECTION_NAME)):
        print("\n" + "=" * 60)
        print(f"  {label} — {nama}")
        print("=" * 60)
        if not client_qdrant.collection_exists(nama):
            print("   (collection belum ada)")
            continue
        hasil = cari(nama, vektor, k)
        if not hasil:
            print("   (tidak ada hasil — collection mungkin masih kosong)")
            continue
        for i, p in enumerate(hasil, 1):
            payload = p.payload or {}
            meta = payload.get("metadata") or {}
            teks = payload.get("page_content", "")
            tanda = "" if p.score >= THRESHOLD_SAAT_INI else "   <- di bawah threshold, akan dibuang"
            print(f"\n   [{i}] skor {p.score:.3f}{tanda}")
            print(f"       {meta.get('source', '?')} | hal {meta.get('page', '?')} | {len(teks)} karakter")
            print(f"       {teks[:220]}{'...' if len(teks) > 220 else ''}")


# =============================================================
# MODE EVALUASI - ukur v1, v2, v3 secara objektif
# =============================================================
# Sebuah hasil pencarian dianggap BENAR kalau memuat kalimat definisi indikator:
# "<istilah> adalah/merupakan/didefinisikan ...". Kalimat nilai seperti "TPT sebesar
# 11,50 persen" atau "AHH Kota ... merupakan yang tertinggi" TIDAK dihitung.
# Aturan ini sudah diuji terhadap seluruh korpus PDF (±50 ribu potongan).
# Semua versi dinilai dengan aturan yang sama persis, jadi perbandingannya adil.
KATA_DEFINISI = r"(adalah|merupakan|didefinisikan|diartikan|yaitu|ialah|menggambarkan|memberikan gambaran|mengukur|terdiri dari|terdiri atas)"
BUKAN_NILAI = r"(?!\s*(sebesar|sebanyak|mencapai|berkisar|tercatat|hanya|sekitar|sebagai berikut|:|\d|(yang )?(tertinggi|terendah|paling|lebih)))"
SATUAN = r"(persen|jiwa|orang|rupiah|tahun|km|unit|poin|ton|hektar|ha)"
KASUS_UJI_DEFAULT = Path(__file__).parent / "kasus_uji_retrieval.json"


def pola_definisi(istilah_list):
    istilah = "|".join(re.escape(i.lower()) for i in istilah_list)
    # Bentuk 1 — kalimat: istilah, boleh ada singkatan dalam kurung, paling banyak 1 kata sisipan
    # (misalnya "yang"), lalu kata kerja definisi yang TIDAK diikuti nilai/angka.
    #   "Angka Partisipasi Murni (APM) adalah proporsi ..."
    verba = rf"(\s+[\w/-]+){{0,1}}?\s+{KATA_DEFINISI}\b{BUKAN_NILAI}"
    # Bentuk 2 — glosarium: "Istilah (Singkatan): definisi", hanya di AWAL BUTIR (setelah tanda
    # poin, nomor, atau akhir kalimat) supaya judul pustaka "... pertumbuhan ekonomi: kasus ..."
    # tidak ikut. Setelah titik dua harus huruf, bukan istilah itu sendiri (keterangan singkatan
    # "APK: Angka Partisipasi Kasar") dan bukan satuan ("TPT: persen").
    #   "• Angka Partisipasi Kasar (APK): Proporsi anak sekolah ..."
    titik_dua = rf"\s*:\s*(?!({istilah}|{SATUAN})(?!\w))(?=[a-z])"
    awal_butir = r"(?:^|[^\w\s,]\s*)"
    # Singkatan dalam kurung boleh diikuti koma, tapi HANYA kalau sesudahnya "yang":
    #   "Garis Kemiskinan (GK), yang terdiri dari ..."            -> definisi, dihitung
    #   "Inflasi, yang diukur dengan ... (IHK), merupakan masalah" -> subjeknya inflasi, tidak dihitung
    kurung = r"(\s*\([^)]{1,40}\)(?:,(?=\s+yang\b))?)?"
    return re.compile(
        rf"(?<!\w)({istilah})(?!\w){kurung}{verba}"
        rf"|{awal_butir}({istilah})(?!\w){kurung}{titik_dua}"
    )


def adalah_definisi(teks, kasus, pola):
    t = re.sub(r"\s+", " ", (teks or "").lower())
    if not pola.search(t):
        return False
    for grup in kasus.get("kata_kunci", []):
        if not any(re.search(r"(?<!\w)" + re.escape(a.lower()) + r"(?!\w)", t) for a in grup):
            return False
    return True


def fmt(x, desimal=2):
    return f"{x:.{desimal}f}".replace(".", ",")


def p_mcnemar(b, c):
    """Uji McNemar eksak dua sisi untuk data berpasangan (versi A vs B pada indikator yang sama).
    b, c = jumlah indikator yang hanya benar di salah satu versi; yang sama-sama benar/salah diabaikan."""
    n = b + c
    if n == 0:
        return 1.0
    from math import comb
    return min(1.0, 2 * sum(comb(n, i) for i in range(min(b, c) + 1)) / 2 ** n)


def evaluasi(path_kasus, k):
    path_kasus = Path(path_kasus)
    if not path_kasus.is_absolute() and not path_kasus.exists():
        path_kasus = Path(__file__).parent / path_kasus
    if not path_kasus.exists():
        print(f"❌ File kasus uji tidak ditemukan: {path_kasus}")
        exit(1)
    data = json.loads(path_kasus.read_text(encoding="utf-8"))
    kasus_list = data["kasus"] if isinstance(data, dict) else data
    n = len(kasus_list)

    versi = [(label, nama) for label, nama in (("v1", COLLECTION_V1), ("v2", COLLECTION_V2), ("v3", COLLECTION_NAME))
             if client_qdrant.collection_exists(nama)]
    if not versi:
        print("❌ Collection v1, v2, maupun v3 belum ada.")
        return

    print(f"📊 MODE: Evaluasi Retrieval — {n} indikator, top-{k}")
    print(f"   Kasus uji: {path_kasus}")
    print(f"   Sedang mencari... (satu query per indikator, dipakai untuk semua versi)\n")

    peringkat = {label: [] for label, _ in versi}  # urutan pertama definisi benar (None = tidak muncul)
    nilai_semua = {label: [] for label, _ in versi}  # per kasus: [(skor, benar?, panjang teks), ...]

    for kasus in kasus_list:
        pola = pola_definisi(kasus["istilah"])
        query = buat_query(kasus["indikator"])
        vektor = embedding_model.embed_query(query)
        for label, nama in versi:
            nilai = []
            for p in cari(nama, vektor, k):
                teks = (p.payload or {}).get("page_content", "")
                nilai.append((p.score, adalah_definisi(teks, kasus, pola), len(teks)))
            pertama = next((i for i, (_, benar, _) in enumerate(nilai, 1) if benar), None)
            peringkat[label].append(pertama)
            nilai_semua[label].append(nilai)

    # --- Hasil per indikator
    kolom = "".join(f"{label:>8}" for label, _ in versi)
    print(f"{'Indikator':<42}{kolom}")
    print("-" * (42 + 8 * len(versi)))
    for i, kasus in enumerate(kasus_list):
        sel = "".join(f"{('#' + str(peringkat[label][i])) if peringkat[label][i] else '-':>8}" for label, _ in versi)
        print(f"{kasus['indikator'][:41]:<42}{sel}")
    print(f"(#n = urutan pertama definisi yang benar muncul; '-' = tidak muncul di top-{k})")

    # --- Ringkasan
    def hit_at(label, batas):
        return sum(1 for r in peringkat[label] if r is not None and r <= batas) / n

    def mrr(label):
        return sum(1 / r for r in peringkat[label] if r) / n

    def rata_karakter(label):
        return sum(sum(pj for *_, pj in nilai) for nilai in nilai_semua[label]) / n

    print(f"\n{'RINGKASAN':<42}{kolom}")
    print("-" * (42 + 8 * len(versi)))
    print(f"{'Definisi benar di urutan #1 (Hit@1)':<42}" + "".join(f"{hit_at(l, 1):>8.0%}" for l, _ in versi))
    print(f"{f'Definisi benar di top-{k} (Hit@{k})':<42}" + "".join(f"{hit_at(l, k):>8.0%}" for l, _ in versi))
    print(f"{'MRR (0-1, makin tinggi makin baik)':<42}" + "".join(f"{fmt(mrr(l)):>8}" for l, _ in versi))
    print(f"{'Rata-rata teks ke model (karakter)':<42}" + "".join(f"{rata_karakter(l):>8,.0f}".replace(",", ".") for l, _ in versi))

    # --- Kesimpulan: v3 dibandingkan dengan tiap versi lain yang ada
    label_versi = [label for label, _ in versi]
    if "v3" in label_versi and len(label_versi) > 1:
        print("\nKESIMPULAN:")
        b = (hit_at("v3", k), mrr("v3"))
        for lama in (l for l in label_versi if l != "v3"):
            a = (hit_at(lama, k), mrr(lama))
            # Uji McNemar: hanya indikator yang hasilnya BERBEDA antara kedua versi yang dihitung
            menang_v3 = sum(1 for r_a, r_b in zip(peringkat[lama], peringkat["v3"]) if r_b and not r_a)
            menang_lama = sum(1 for r_a, r_b in zip(peringkat[lama], peringkat["v3"]) if r_a and not r_b)
            if b[0] == a[0] and abs(b[1] - a[1]) < 0.05:
                print(f"   v3 vs {lama}: praktis setara (Hit@{k} sama, MRR {fmt(a[1])} vs {fmt(b[1])}).")
            elif b > a:
                print(f"   v3 vs {lama}: v3 lebih baik (Hit@{k} {a[0]:.0%} -> {b[0]:.0%}, MRR {fmt(a[1])} -> {fmt(b[1])}).")
            else:
                print(f"   v3 vs {lama}: {lama} lebih baik (Hit@{k} {lama} {a[0]:.0%} vs v3 {b[0]:.0%}, "
                      f"MRR {lama} {fmt(a[1])} vs v3 {fmt(b[1])}).")
            print(f"      indikator yang hasilnya berbeda: {menang_v3} hanya v3 benar, {menang_lama} hanya {lama} benar"
                  f" -> McNemar p = {fmt(p_mcnemar(menang_v3, menang_lama), 3)}")
        print(f"   Catatan: dengan {n} indikator, 1 indikator = {100 / n:.0f} poin persen.")
        print("   Selisih disebut bermakna kalau p McNemar < 0,05; kalau belum, tambahkan kasus uji.")

    # --- Kalibrasi threshold
    print("\nKALIBRASI THRESHOLD")
    print("   'dapat definisi' = indikator yang MASIH menerima definisi benar setelah disaring threshold")
    print("   'potongan lain'  = rata-rata potongan bukan-definisi yang ikut terkirim ke model per query")
    for label, _ in versi:
        print(f"\n   {label}:  threshold | dapat definisi | potongan lain")
        for t in (0.35, 0.40, 0.45, 0.50, 0.55, 0.60):
            dapat = sum(1 for nilai in nilai_semua[label] if any(s >= t and benar for s, benar, _ in nilai))
            lain = sum(sum(1 for s, benar, _ in nilai if s >= t and not benar) for nilai in nilai_semua[label]) / n
            tanda = "   <- threshold main.py sekarang" if abs(t - THRESHOLD_SAAT_INI) < 1e-9 else ""
            print(f"         {fmt(t)}    |    {dapat:>2}/{n}      |     {fmt(lain, 1)}{tanda}")
    print("\n   Pilih threshold TERTINGGI yang belum mengurangi 'dapat definisi' — di situ potongan")
    print("   lain paling banyak terbuang tanpa kehilangan definisi yang benar.")


# =============================================================
# MAIN
# =============================================================
def main():
    parser = argparse.ArgumentParser(description="PRANATA Local Ingest V3")
    parser.add_argument("--file", "-f", type=str, default=None, help="Proses 1 file spesifik")
    parser.add_argument("--reset", action="store_true", help="Hapus log lokal v3, proses ulang semua")
    parser.add_argument("--force", action="store_true", help="Proses ulang dan GANTI data lama file tsb")
    parser.add_argument("--check", action="store_true", help="Cek status database dan collection saja, tanpa ingest")
    parser.add_argument("--uji", type=str, default=None, metavar="INDIKATOR",
                        help='Bandingkan hasil pencarian v1, v2, v3, misal --uji "Tingkat Pengangguran Terbuka"')
    parser.add_argument("--evaluasi", nargs="?", const=str(KASUS_UJI_DEFAULT), default=None, metavar="FILE_KASUS",
                        help="Ukur v1, v2, v3 dengan daftar kasus uji (default: kasus_uji_retrieval.json)")
    parser.add_argument("--k", type=int, default=4, help="Jumlah hasil per pencarian di mode --uji/--evaluasi (default 4)")
    args = parser.parse_args()

    # Mode: evaluasi v1, v2, v3
    if args.evaluasi:
        evaluasi(args.evaluasi, args.k)
        return

    # Mode: uji hasil pencarian
    if args.uji:
        uji_retrieval(args.uji, args.k)
        return

    # Mode: cek status database saja
    if args.check:
        print("📋 MODE: Cek Status Database")
        print("-" * 60)
        try:
            info = client_qdrant.get_collection(COLLECTION_NAME)
            print(f"   Collection   : {COLLECTION_NAME}")
            print(f"   Status       : {info.status}")
            print(f"   Total vektor : {info.points_count:,}")
            print(f"   Dimensi      : {info.config.params.vectors.size}")
            print(f"   Jarak metrik : {info.config.params.vectors.distance}")
        except Exception as e:
            print(f"   ❌ Gagal membaca info collection: {e}")

        # Hitung file PDF di folder
        all_pdfs = sorted(DOKUMEN_PATH.glob("*.pdf"))
        processed_log = read_log()
        belum = [p.name for p in all_pdfs if p.name not in processed_log]

        print(f"\n   📁 File PDF di folder   : {len(all_pdfs)}")
        print(f"   📝 Sudah di log v3      : {len(processed_log)}")
        print(f"   ⏳ Belum diproses       : {len(belum)}")

        if belum and len(belum) <= 20:
            print(f"\n   File belum diproses:")
            for f in belum:
                print(f"     - {f}")

        print("-" * 60)
        print("\nJalankan tanpa --check untuk memulai ingest.")
        return

    splitter = buat_splitter()

    print(f"Folder Sumber : {DOKUMEN_PATH}")
    print(f"Log File      : {LOG_PATH}")
    print(f"Collection    : {COLLECTION_NAME}")
    print(f"Chunk         : {CHUNK_SIZE} karakter, overlap {CHUNK_OVERLAP}")
    print()

    if args.reset:
        if LOG_PATH.exists():
            LOG_PATH.unlink()
            print("Log lokal v3 dihapus. Semua file akan diperiksa ulang.\n")

    if args.file:
        target = DOKUMEN_PATH / args.file
        if not target.exists():
            print(f"ERROR: File tidak ditemukan: {target}")
            exit(1)
        print(f"Mode: File Spesifik -> {args.file}\n" + "-" * 60)
        result = ingest_pdf(target, splitter, force=args.force)
        print("-" * 60)
        if result == "gpu_rusak":
            print(PESAN_GPU_RUSAK)
        else:
            print("Selesai!" if result in ("ok", "skip") else "Gagal memproses file.")
        return

    all_pdfs = sorted(DOKUMEN_PATH.glob("*.pdf"))
    if not all_pdfs:
        print(f"Tidak ada file PDF di: {DOKUMEN_PATH}")
        return

    print(f"Ditemukan {len(all_pdfs)} file PDF\n" + "-" * 60)

    from tqdm import tqdm

    sukses = skip = gagal = 0
    pbar = tqdm(all_pdfs, desc="Proses File", unit="pdf", dynamic_ncols=True)
    for pdf in pbar:
        filename = pdf.name
        pbar.set_postfix_str(f"Membaca: {filename[:20]}...")

        r = ingest_pdf(pdf, splitter, force=args.force)

        if r == "ok":
            sukses += 1
            pbar.write(f"✅ SUKSES: {filename}")
        elif r == "skip":
            skip += 1
            pbar.write(f"⏭️  SKIP: {filename}")
        else:
            gagal += 1
            pbar.write(f"❌ GAGAL: {filename}")
            if r == "gpu_rusak":
                pbar.write(PESAN_GPU_RUSAK)
                break

    pbar.close()

    print("-" * 60)
    print(f"\nRINGKASAN:")
    print(f"  Berhasil di-embed : {sukses} file")
    print(f"  Dilewati (skip)   : {skip} file")
    print(f"  Gagal             : {gagal} file")
    print(f"  Total             : {len(all_pdfs)} file")

    total_waktu = sum(WAKTU.values())
    if total_waktu > 0:
        print(f"\nWAKTU PER TAHAP (hanya file yang diproses):")
        for kunci, label in (("baca", "Baca + potong PDF"),
                             ("embedding", f"Embedding ({device.upper()})"),
                             ("upload", "Upload ke Qdrant")):
            d = WAKTU[kunci]
            print(f"  {label:<26}: {int(d // 60):>3}m {int(d % 60):02d}s ({d / total_waktu:.0%})")

    print("\nProses Ingest Lokal V3 Selesai!")


if __name__ == "__main__":
    main()
