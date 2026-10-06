"""
=============================================================
  LOCAL INGEST SCRIPT - PRANATA AI
=============================================================
  Script ini membaca semua PDF dari folder dokumen_bps di
  storage Laravel dan langsung meng-embed ke Qdrant Cloud
  TANPA perlu server Hugging Face (100% lokal).

  CARA PAKAI:
  1. Pastikan .env di folder scripts/ sudah diisi
  2. Install dependencies:
       pip install -r requirements_ingest.txt
  3. Jalankan:
       python local_ingest.py
     atau untuk file tertentu:
       python local_ingest.py --file "nama_file.pdf"
     atau untuk force proses ulang:
       python local_ingest.py --force
     atau untuk reset log dan proses ulang semua:
       python local_ingest.py --reset
=============================================================
"""

import os
import sys
import io

# Force stdout to UTF-8 to support emojis in Windows Terminal
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

import re
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
COLLECTION_NAME = "bps_knowledge_bge_m3_v1"
EMBEDDING_MODEL = "BAAI/bge-m3"

DOKUMEN_PATH = Path(r"C:\Herd\pranata\storage\app\public\dokumen_bps")
LOG_PATH     = Path(r"C:\Herd\pranata\storage\app\processed_log_bge_m3.txt")

# =============================================================
# INISIALISASI
# =============================================================
from qdrant_client import QdrantClient, models
from langchain_community.document_loaders import PyMuPDFLoader
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_huggingface import HuggingFaceEmbeddings

print("=" * 60)
print("  PRANATA LOCAL INGEST SCRIPT")
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
print(f"Device: {device.upper()}")
print("(Pertama kali download model, sabar ya...)\n")

try:
    embedding_model = HuggingFaceEmbeddings(
        model_name=EMBEDDING_MODEL,
        model_kwargs={"device": device},
        encode_kwargs={"normalize_embeddings": True},
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


def is_in_qdrant(filename):
    try:
        res = client_qdrant.scroll(
            collection_name=COLLECTION_NAME,
            scroll_filter=models.Filter(must=[
                models.FieldCondition(key="metadata.source", match=models.MatchValue(value=filename))
            ]),
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

        splits = text_splitter.split_documents(cleaned)
        print(f"   -> {len(splits)} chunks dari {len(cleaned)} halaman")

        points = []
        for doc in splits:
            if not doc.page_content.strip():
                continue
            vector = embedding_model.embed_query(doc.page_content)
            points.append(models.PointStruct(
                id=str(uuid.uuid4()),
                vector=vector,
                payload={"page_content": doc.page_content, "metadata": doc.metadata}
            ))

        if points:
            client_qdrant.upsert(collection_name=COLLECTION_NAME, points=points)
            write_log(filename)
            print(f"   OK Sukses! {len(points)} vektor tersimpan.")
            return "ok"
        else:
            print(f"   GAGAL: Tidak ada vektor.")
            return "gagal"

    except Exception as e:
        print(f"   ERROR: {e}")
        return "gagal"


# =============================================================
# MAIN
# =============================================================
def main():
    parser = argparse.ArgumentParser(description="PRANATA Local Ingest")
    parser.add_argument("--file", "-f", type=str, default=None, help="Proses 1 file spesifik")
    parser.add_argument("--reset", action="store_true", help="Hapus log lokal, proses ulang semua")
    parser.add_argument("--force", action="store_true", help="Paksa proses ulang meski sudah ada")
    parser.add_argument("--check", action="store_true", help="Cek status database dan collection saja, tanpa ingest")
    args = parser.parse_args()

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
        print(f"   📝 Sudah di log lokal   : {len(processed_log)}")
        print(f"   ⏳ Belum diproses       : {len(belum)}")

        if belum and len(belum) <= 20:
            print(f"\n   File belum diproses:")
            for f in belum:
                print(f"     - {f}")

        print("-" * 60)
        print("\nJalankan tanpa --check untuk memulai ingest.")
        return

    splitter = RecursiveCharacterTextSplitter(chunk_size=2000, chunk_overlap=400)

    print(f"Folder Sumber : {DOKUMEN_PATH}")
    print(f"Log File      : {LOG_PATH}")
    print(f"Collection    : {COLLECTION_NAME}")
    print()

    if args.reset:
        if LOG_PATH.exists():
            LOG_PATH.unlink()
            print("Log lokal dihapus. Semua file akan diproses ulang.\n")

    if args.file:
        target = DOKUMEN_PATH / args.file
        if not target.exists():
            print(f"ERROR: File tidak ditemukan: {target}")
            exit(1)
        print(f"Mode: File Spesifik -> {args.file}\n" + "-" * 60)
        result = ingest_pdf(target, splitter, force=args.force)
        print("-" * 60)
        print("Selesai!" if result != "gagal" else "Gagal memproses file.")
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

    pbar.close()

    print("-" * 60)
    print(f"\nRINGKASAN:")
    print(f"  Berhasil di-embed : {sukses} file")
    print(f"  Dilewati (skip)   : {skip} file")
    print(f"  Gagal             : {gagal} file")
    print(f"  Total             : {len(all_pdfs)} file")
    print("\nProses Ingest Lokal Selesai!")


if __name__ == "__main__":
    main()
