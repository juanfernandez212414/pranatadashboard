import os
import re
import time
import uuid
import tempfile
import warnings
import shutil
import torch
from dotenv import load_dotenv
load_dotenv()

from pydantic import BaseModel
from typing import Dict, Any, List

# --- IMPORT SDK GEMINI TERBARU ---
from google import genai
from google.genai import types

# --- IMPORT FASTAPI & RAG TOOLS ---
from fastapi import FastAPI, BackgroundTasks, UploadFile, File
from qdrant_client import QdrantClient, models
from langchain_community.document_loaders import PyMuPDFLoader
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_huggingface import HuggingFaceEmbeddings

# --- IMPORT HUGGING FACE & GROQ INFERENCE CLIENT ---
from huggingface_hub import InferenceClient
from groq import Groq

warnings.filterwarnings("ignore", message=".*TRANSFORMERS_CACHE.*")

app = FastAPI(title="PRANATA AI Worker (Ingest & Generate)")

# =================================================================
# 1. KONFIGURASI DAN INISIALISASI
# =================================================================
QDRANT_URL = os.getenv("QDRANT_URL")
QDRANT_API_KEY = os.getenv("QDRANT_API_KEY")
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY")
HF_TOKEN = os.getenv("HF_TOKEN")
GROQ_API_KEY = os.getenv("GROQ_API_KEY")

COLLECTION_NAME = "bps_knowledge_bge_m3_v1"
EMBEDDING_MODEL_NAME = "BAAI/bge-m3"

# Pemotongan chunk = konfigurasi v4 hasil evaluasi: 2000 karakter, overlap 400, dipotong di akhir
# kalimat. clean_text() meratakan semua baris baru, jadi pemisah yang benar-benar bekerja adalah
# ". "; tanpa pemisah kalimat, 89% batas chunk jatuh di tengah kalimat.
# HARUS sama dengan scripts/local_ingest_v4.py, supaya PDF yang diunggah lewat halaman Pengetahuan
# dipotong sama persis dengan isi collection yang sudah ada.
CHUNK_SIZE = 2000
CHUNK_OVERLAP = 400
CHUNK_SEPARATORS = ["\n\n", "\n", ". ", "; ", ", ", " ", ""]

# Skor minimal referensi RAG. 0.55 = nilai tertinggi yang tidak membuang satu pun definisi benar
# pada kalibrasi (skor terendah definisi benar 0.562). Skor bge-m3 tidak bisa membedakan relevan
# dan tidak relevan (topik di luar korpus pun mencapai 0.66), jadi ini hanya batas bawah pengaman.
RAG_SCORE_THRESHOLD = 0.55

# Jatah waktu total untuk satu permintaan /generate-narrative.
# HARUS LEBIH KECIL dari timeout di Laravel (300 detik, lihat DashboardController::generateNarrative),
# supaya worker selalu sempat menjawab sebelum Laravel menyerah. Kalau Laravel menyerah duluan,
# hasil kerja worker terbuang percuma padahal kuota model sudah terpakai.
NARRATIVE_BUDGET_SECONDS = 240

# Init Gemini
client_gemini = None
if not GEMINI_API_KEY:
    print("⚠️ WARNING: GEMINI_API_KEY belum diset!", flush=True)
else:
    client_gemini = genai.Client(api_key=GEMINI_API_KEY)
    print("✅ Terhubung ke Google Gemini API", flush=True)

# Init Hugging Face Inference
client_hf = None
if not HF_TOKEN:
    print("⚠️ WARNING: HF_TOKEN (Hugging Face) belum diset!", flush=True)
else:
    client_hf = InferenceClient(api_key=HF_TOKEN)
    print("✅ Terhubung ke Hugging Face Inference API", flush=True)

# Init Groq Client (Untuk Llama-3.3, GPT-OSS-120B, & Fallback Otomatis)
client_groq = None
if not GROQ_API_KEY:
    print("⚠️ WARNING: GROQ_API_KEY belum diset! Fitur Groq nonaktif.", flush=True)
else:
    client_groq = Groq(api_key=GROQ_API_KEY)
    print("✅ Terhubung ke Groq Cloud API", flush=True)

# Init Qdrant
client_qdrant = None
if QDRANT_URL and QDRANT_API_KEY:
    try:
        client_qdrant = QdrantClient(url=QDRANT_URL, api_key=QDRANT_API_KEY)
        print(f"✅ Terhubung ke Qdrant Cloud (Collection: {COLLECTION_NAME})", flush=True)
    except Exception as e:
        print(f"⚠️ Gagal koneksi Qdrant: {e}", flush=True)

# Init Embedding
print(f"⏳ Loading Model Embedding: {EMBEDDING_MODEL_NAME}...", flush=True)
device = "cuda" if torch.cuda.is_available() else "cpu"
print(f"🖥️  Running Embedding on: {device.upper()}", flush=True)

model_kwargs = {'device': device}
encode_kwargs = {'normalize_embeddings': True}

try:
    embedding_model = HuggingFaceEmbeddings(
        model_name=EMBEDDING_MODEL_NAME,
        model_kwargs=model_kwargs,
        encode_kwargs=encode_kwargs,
        cache_folder="./.cache_models"
    )
    print("✅ Model Embedding Siap.", flush=True)
except Exception as e:
    print(f"❌ Gagal load model embedding: {e}", flush=True)

# Pastikan Qdrant Collection siap
if client_qdrant:
    if not client_qdrant.collection_exists(COLLECTION_NAME):
        client_qdrant.create_collection(
            collection_name=COLLECTION_NAME,
            vectors_config=models.VectorParams(size=1024, distance=models.Distance.COSINE)
        )
    
    try:
        client_qdrant.create_payload_index(
            collection_name=COLLECTION_NAME,
            field_name="metadata.source",
            field_schema=models.PayloadSchemaType.KEYWORD
        )
        print("🔑 Payload Index untuk 'metadata.source' berhasil dipastikan siap.", flush=True)
    except Exception as e:
        print(f"⚠️ Gagal/Sudah ada payload index untuk metadata.source: {e}", flush=True)

# =================================================================
# 2. HELPER FUNCTIONS
# =================================================================
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

def is_document_exist_in_qdrant(filename):
    if not client_qdrant: return False
    try:
        scroll_filter = models.Filter(
            must=[models.FieldCondition(key="metadata.source", match=models.MatchValue(value=filename))]
        )
        res = client_qdrant.scroll(collection_name=COLLECTION_NAME, scroll_filter=scroll_filter, limit=1)
        return len(res[0]) > 0
    except:
        return False

# =================================================================
# 3. BACKGROUND TASKS (INGEST)
# =================================================================
def task_ingest_from_files(file_records: List[Dict[str, str]]):
    print(f"🚀 Memulai Ingestion untuk {len(file_records)} dokumen yang diunggah...")
    text_splitter = RecursiveCharacterTextSplitter(
        chunk_size=CHUNK_SIZE,
        chunk_overlap=CHUNK_OVERLAP,
        separators=CHUNK_SEPARATORS,
        keep_separator="end",  # titik ikut di akhir kalimat, bukan di awal chunk berikutnya
    )

    for record in file_records:
        tmp_path = record["tmp_path"]
        filename = record["filename"]
        
        if is_document_exist_in_qdrant(filename):
            print(f"⏩ Skip: {filename} sudah ada di Qdrant.")
            if os.path.exists(tmp_path):
                os.remove(tmp_path)
            continue
            
        print(f"⬇️ Mengekstrak Dokumen: {filename}...")
        try:
            loader = PyMuPDFLoader(tmp_path)
            raw_docs = loader.load()
            cleaned_docs = []
            file_year = extract_year_from_filename(filename)
            
            for doc in raw_docs:
                doc.page_content = clean_text(doc.page_content)
                doc.metadata.update({
                    "source": filename, 
                    "year": file_year, 
                    "page": doc.metadata.get("page", 0) + 1
                })
                cleaned_docs.append(doc)

            splits = text_splitter.split_documents(cleaned_docs)
            points = []

            for doc in splits:
                vector = embedding_model.embed_query(doc.page_content)
                payload = {"page_content": doc.page_content, "metadata": doc.metadata}
                points.append(models.PointStruct(id=str(uuid.uuid4()), vector=vector, payload=payload))
            
            if points:
                client_qdrant.upsert(collection_name=COLLECTION_NAME, points=points)
                print(f"✅ Sukses Ingest ({len(splits)} chunks): {filename}")
                
        except Exception as e:
            print(f"❌ Error Ingest {filename}: {e}")
        finally:
            if os.path.exists(tmp_path):
                os.remove(tmp_path)
            
    print("🎉 Proses Ingestion dari File Upload Selesai!")

# =================================================================
# 4. API ENDPOINTS & RAG LOGIC
# =================================================================
class CheckFilesRequest(BaseModel):
    filenames: List[str]

# Jumlah chunk yang diambil per putaran scroll di /check-missing-files.
# Nilai ini TIDAK menentukan kebenaran hasil (lihat loop penyempitan filter di
# bawah), hanya menentukan berapa putaran yang dibutuhkan.
CHECK_SCROLL_LIMIT = 10000

@app.post("/check-missing-files")
def check_missing_files(req: CheckFilesRequest):
    if not client_qdrant:
        return {"missing_filenames": req.filenames}
    try:
        # PENTING: limit scroll menghitung CHUNK, bukan file. Satu PDF bisa jadi
        # ratusan chunk, jadi sekali scroll dengan limit tetap bisa habis terpakai
        # oleh chunk milik segelintir file saja — file lain lalu salah dilaporkan
        # "missing" dan ikut di-ingest ulang (vektor jadi ganda).
        #
        # Solusinya: loop dengan filter yang makin menyempit. Setiap putaran hanya
        # mencari nama file yang BELUM ketemu, sehingga chunk milik file yang sudah
        # ketemu tidak lagi memakan jatah limit. Loop dijamin berhenti karena setiap
        # putaran mengeluarkan minimal satu file dari daftar pending, atau scroll
        # balik kosong. Berapa pun jumlah chunk per file, hasilnya tetap benar.
        pending = list(dict.fromkeys(req.filenames))  # buang duplikat, urutan tetap
        existing_files = set()
        putaran = 0

        while pending:
            putaran += 1
            pending_set = set(pending)

            records, _ = client_qdrant.scroll(
                collection_name=COLLECTION_NAME,
                scroll_filter=models.Filter(
                    must=[models.FieldCondition(
                        key="metadata.source",
                        match=models.MatchAny(any=pending)
                    )]
                ),
                limit=CHECK_SCROLL_LIMIT,
                with_payload=["metadata.source"],
                with_vectors=False
            )

            # Tidak ada chunk sama sekali untuk sisa nama file -> semuanya memang belum ada.
            if not records:
                break

            found_now = set()
            for record in records:
                metadata = (record.payload or {}).get("metadata") or {}
                if isinstance(metadata, dict):
                    source = metadata.get("source")
                    # Dibatasi ke pending_set supaya daftar pending DIJAMIN menyusut
                    # tiap putaran (tidak mungkin loop selamanya).
                    if source in pending_set:
                        found_now.add(source)

            # Pengaman: scroll balik berisi data tapi tidak ada nama file yang terbaca
            # (payload di luar dugaan). Berhenti daripada berputar selamanya.
            if not found_now:
                print("⚠️ check_missing_files: scroll mengembalikan data tanpa nama file yang cocok, loop dihentikan.", flush=True)
                break

            existing_files |= found_now
            pending = [f for f in pending if f not in found_now]

        missing_filenames = [filename for filename in req.filenames if filename not in existing_files]
        print(
            f"🔎 check_missing_files: {len(existing_files)} file sudah ada, "
            f"{len(missing_filenames)} belum ada (selesai dalam {putaran} putaran scroll).",
            flush=True
        )
        return {"missing_filenames": missing_filenames}
    except Exception as e:
        print(f"❌ Error saat bulk check_missing_files: {e}")
        return {"missing_filenames": req.filenames}

class DeleteRequest(BaseModel):
    filename: str

@app.delete("/delete-by-file")
async def delete_by_file(req: DeleteRequest):
    if not client_qdrant:
        return {"status": "error", "message": "Database Qdrant tidak terhubung."}
    try:
        client_qdrant.delete(
            collection_name=COLLECTION_NAME,
            points_selector=models.Filter(
                must=[
                    models.FieldCondition(
                        key="metadata.source",
                        match=models.MatchValue(value=req.filename)
                    )
                ]
            )
        )
        print(f"✅ Berhasil menghapus vektor untuk file: {req.filename}")
        return {"status": "success", "message": f"Berhasil menghapus data untuk {req.filename}"}
    except Exception as e:
        print(f"❌ Error saat menghapus {req.filename}: {e}")
        from fastapi import HTTPException
        raise HTTPException(status_code=500, detail=str(e))

@app.delete("/delete-all")
async def delete_all():
    if not client_qdrant:
        return {"status": "error", "message": "Database Qdrant tidak terhubung."}
    try:
        # Hapus koleksi untuk mereset data
        if client_qdrant.collection_exists(COLLECTION_NAME):
            client_qdrant.delete_collection(collection_name=COLLECTION_NAME)
        
        # Buat ulang koleksi
        client_qdrant.create_collection(
            collection_name=COLLECTION_NAME,
            vectors_config=models.VectorParams(size=1024, distance=models.Distance.COSINE)
        )
        client_qdrant.create_payload_index(
            collection_name=COLLECTION_NAME,
            field_name="metadata.source",
            field_schema=models.PayloadSchemaType.KEYWORD
        )
        print("✅ Berhasil menghapus seluruh data vektor dan mereset koleksi.")
        return {"status": "success", "message": "Berhasil menghapus seluruh data pengetahuan"}
    except Exception as e:
        print(f"❌ Error saat menghapus seluruh data: {e}")
        from fastapi import HTTPException
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/upload-ingest")
async def upload_ingest(background_tasks: BackgroundTasks, files: List[UploadFile] = File(...)):
    file_records = []
    for file in files:
        fd, tmp_path = tempfile.mkstemp(suffix=".pdf")
        with os.fdopen(fd, 'wb') as f:
            shutil.copyfileobj(file.file, f)
            
        file_records.append({
            "tmp_path": tmp_path, 
            "filename": file.filename
        })

    background_tasks.add_task(task_ingest_from_files, file_records)
    return {
        "status": "started", 
        "message": f"Menerima {len(files)} file. Proses Ingest berjalan di background."
    }

@app.get("/")
def home():
    return {
        "status": "Active", 
        "mode": "Worker (Ingest & Generate)",
        "collection": COLLECTION_NAME
    }

class BPSDataInput(BaseModel):
    category: str
    subject: str
    indicator: str
    data_json: Dict[str, Any]
    # Default model diset ke Llama 3.3 Versatile dari Groq
    model_id: str = "llama-3.3-70b-versatile"

def parse_table(raw_data):
    try:
        if "headers" in raw_data and "rows" in raw_data:
            headers = [str(h.get('value', '')).strip() for h in raw_data['headers']]
            md_table = "| " + " | ".join(headers) + " |\n"
            md_table += "|" + "|".join(["---" for _ in headers]) + "|\n"
            for row in raw_data['rows']:
                row_vals = [str(c.get('value', '')).strip() for c in row]
                md_table += "| " + " | ".join(row_vals) + " |\n"
            return md_table
        elif isinstance(raw_data, dict):
            md_table = "| Kunci | Nilai |\n|---|---|\n"
            for k, v in raw_data.items():
                md_table += f"| {k} | {v} |\n"
            return md_table
        else:
            return str(raw_data)
    except Exception as e:
        return f"Format data tidak valid: {str(e)}"

_ATAS_DASAR_HARGA_RE = re.compile(r"\batas dasar harga (berlaku|konstan)\b", re.IGNORECASE)


def konsep_dari_nama_indikator(nama: str) -> str:
    """Ambil inti KONSEP dari nama indikator, untuk dipakai sebagai query RAG.

    Banyak nama indikator di dashboard adalah judul tabel publikasi BPS, misalnya
    "Jumlah Pegawai Negeri Sipil Menurut Jabatan dan Jenis Kelamin". Kalau dipakai apa adanya,
    pencarian justru menemukan TABEL itu sendiri (judulnya identik) — berisi angka yang sudah
    dimiliki model — bukan definisi konsepnya. Contoh:
      "Jumlah Pegawai Negeri Sipil Menurut Jabatan dan Jenis Kelamin" -> "Pegawai Negeri Sipil"
      "[Metode Baru] Indeks Pembangunan Manusia (UHH LF SP2020)"      -> "Indeks Pembangunan Manusia"
      "Laju Pertumbuhan PDRB Atas Dasar Harga Konstan 2010 Menurut Lapangan Usaha"
                                          -> "Laju Pertumbuhan PDRB Atas Dasar Harga Konstan"
    HANYA untuk pencarian; nama lengkap indikator tetap dikirim ke model apa adanya.
    """
    teks = nama.strip()
    # "Atas Dasar Harga Berlaku/Konstan" bagian dari konsep: disimpan dulu, apa pun posisinya.
    adh = _ATAS_DASAR_HARGA_RE.search(teks)
    teks = _ATAS_DASAR_HARGA_RE.sub(" ", teks)
    teks = re.sub(r"^\s*\[[^\]]*\]\s*", "", teks)                                   # "[Metode Baru] ..."
    teks = re.sub(r"\s*\((?![A-Z][A-Z0-9]{0,5}\))[^)]*\)", "", teks)                # kurung dibuang, kecuali singkatan: (IPM), (P0)
    teks = re.sub(r"\s+(menurut|berdasarkan)\s+.*$", "", teks, flags=re.IGNORECASE)  # "... Menurut Lapangan Usaha"
    teks = re.sub(r"\s+per\s+(kecamatan|kelurahan|desa|kabupaten|kota|wilayah)\b.*$", "", teks, flags=re.IGNORECASE)
    teks = re.sub(r"\s+hasil\s+.*$", "", teks, flags=re.IGNORECASE)                 # "... Hasil Long Form SP2020"
    teks = re.sub(r"^(jumlah|banyaknya)\s+", "", teks, flags=re.IGNORECASE)
    teks = re.sub(r"\b(triwulanan|tahunan|bulanan)\b", " ", teks, flags=re.IGNORECASE)
    teks = re.sub(r"\b(19|20)\d{2}\b", " ", teks)                                    # tahun, misal "Harga Konstan 2010"
    teks = re.sub(r"\s{2,}", " ", teks).strip(" ,-")
    if adh:
        teks = f"{teks} {adh.group(0)}".strip()
    return teks or nama.strip()


def get_rag_context(query, limit=3):
    if not client_qdrant:
        return "Database Qdrant tidak terhubung."
    try:
        vector = embedding_model.embed_query(query)
        # Pencarian eksak (tanpa indeks HNSW): pada korpus ini indeks HNSW terbukti melewatkan hasil
        # terbaik, kemungkinan karena banyak teks yang sama persis di publikasi tahun berbeda.
        # Dengan ±36 ribu vektor waktunya praktis sama (±215 ms vs ±205 ms).
        pencarian_eksak = models.SearchParams(exact=True)
        try:
            res = client_qdrant.query_points(
                collection_name=COLLECTION_NAME,
                query=vector,
                limit=limit,
                score_threshold=RAG_SCORE_THRESHOLD,
                search_params=pencarian_eksak,
                with_payload=True,
            )
            points = res.points
        except AttributeError:
            res = client_qdrant.search(
                collection_name=COLLECTION_NAME,
                query_vector=vector,
                limit=limit,
                score_threshold=RAG_SCORE_THRESHOLD,
                search_params=pencarian_eksak,
                with_payload=True,
            )
            points = res

        contexts = []
        for point in points:
            source = point.payload.get("metadata", {}).get("source", "Dokumen BPS")
            year = point.payload.get("metadata", {}).get("year", "?")
            page = point.payload.get("metadata", {}).get("page", "?")
            content = point.payload.get("page_content", "")
            contexts.append(
                f"--- [Sumber: {source} | Thn: {year} | Hal: {page}] ---\n{content}"
            )

        # === TAMBAHKAN KODE PRINT INI AGAR MUNCUL DI LOGS TERMINAL ===
        if contexts:
            print(
                f"\n🔍 [RAG RETRIEVAL SUKSES] Ditemukan {len(contexts)} dokumen referensi:",
                flush=True,
            )
            for idx, ctx in enumerate(contexts, 1):
                print(f"[{idx}] {ctx[:150]}... [lanjut ke sistem]", flush=True)
            print("=" * 60, flush=True)
        else:
            print(
                "\n⚠️ [RAG KOSONG] Tidak ada referensi PDF yang cocok dengan threshold.",
                flush=True,
            )
        # =============================================================

        return (
            "\n\n".join(contexts)
            if contexts
            else "Tidak ditemukan referensi spesifik di database."
        )
    except Exception as e:
        print(f"RAG Search Error: {e}", flush=True)
        return "Gagal mengakses Knowledge Base."

# =================================================================
# SYSTEM PROMPT RINGKAS KHUSUS GROQ (Hemat Token)
# =================================================================
def get_groq_compact_prompt():
    return """Anda adalah Senior Data Analyst BPS dengan pengalaman 20 tahun menyusun Berita Resmi Statistik.
Tugas: Susun narasi analisis statistik dalam Bahasa Indonesia Baku, akurat secara matematis, setara kualitas publikasi resmi BPS.

================================================================
LANGKAH 1 — ANALISIS SINGKAT (WAJIB, TAPI RINGKAS)
================================================================
SEBELUM menulis narasi, WAJIB jabarkan analisis di dalam tag berikut, PERSIS seperti format ini:

<langkah_analisis>
1. Periodisasi & satuan: [tahunan/bulanan/triwulanan; satuan data]
2. Nilai tertinggi & terendah keseluruhan: [nilai + periode + label wilayah/kategori, HANYA 1 tertinggi + 1 terendah]
3. Dua data terakhir: [nilai terbaru, nilai sebelumnya, selisih absolut, persentase perubahan]
</langkah_analisis>

ATURAN KHUSUS BAGIAN INI:
- WAJIB tutup dengan tag </langkah_analisis> SEBELUM mulai menulis narasi. JANGAN PERNAH lupa menutup tag ini — ini WAJIB MUTLAK, bahkan jika Anda harus mempersingkat isi analisisnya.
- Jika data punya BANYAK wilayah/kategori (lebih dari 3), JANGAN uraikan satu per satu di sini. Cukup sebutkan SATU wilayah/kategori dengan nilai TERTINGGI dan SATU dengan nilai TERENDAH — sisanya cukup dirangkum di narasi sebagai "wilayah/kategori lainnya".
- Bagian ini HARUS singkat (maksimal 5 baris). JANGAN menghitung rata-rata atau mendaftar tiap baris data satu per satu.
- Setelah tag ditutup, LANGSUNG tulis narasi final 3 paragraf. Narasi TIDAK BOLEH kosong — ini bagian TERPENTING dari jawaban Anda, jauh lebih penting daripada analisisnya.

================================================================
LANGKAH 2 — NARASI FINAL (3 PARAGRAF, WAJIB ADA SETELAH TAG DITUTUP)
================================================================
FORMAT ANGKA WAJIB:
- Desimal pakai KOMA (14,52 bukan 14.52). Ribuan pakai TITIK (1.234 bukan 1,234).
- Setiap angka WAJIB disertai satuan. Angka PERSIS seperti data, DILARANG membulatkan/mengarang.

Paragraf 1 - Definisi: Jelaskan apa indikator ini dan maknanya. Sebutkan rentang periode data yang tersedia.
Paragraf 2 - Historis: Ceritakan perjalanan data kronologis (kapan naik/turun). Sebutkan nilai tertinggi & terendah keseluruhan beserta periode/wilayahnya. DILARANG menyebut rata-rata historis.
Paragraf 3 - Terkini (PALING PENTING): Bandingkan DUA data TERAKHIR. WAJIB cantumkan: nilai terbaru, nilai sebelumnya, selisih absolut, dan persentase perubahan secara relatif. Untuk data bersatuan persen, sebut selisihnya sebagai "poin persentase".

ATURAN MUTLAK:
1. Narasi LANGSUNG mulai dari kalimat pertama. TANPA judul/header/label.
2. DILARANG Markdown (*, #, -, penomoran 1. 2. 3.) di dalam narasi. Tulis paragraf teks murni.
3. DILARANG kesimpulan, opini, proyeksi, atau rekomendasi kebijakan.
4. DILARANG istilah Inggris. Semua Bahasa Indonesia baku.
5. DILARANG mengarang angka yang tidak ada di data.
6. DILARANG istilah teknis susah (delta, MoM, YoY, observasi, anomali). Gunakan: selisih, dibandingkan tahun lalu, perubahan.
7. Gaya bahasa jurnalistik naratif, mengalir, mudah dipahami masyarakat umum.
8. Narasi TIDAK BOLEH hanya berisi langkah analisis — harus 3 paragraf prosa lengkap.
"""

# =================================================================
# HELPER: PEMBERSIHAN TAG <langkah_analisis> & DETEKSI OUTPUT BOCOR
# =================================================================
# Toleran terhadap variasi spasi/kapitalisasi tag dari model, dan terhadap
# tag penutup yang hilang (biasanya karena generasi terpotong oleh max_tokens).
_ANALYSIS_TAG_RE = re.compile(r'<\s*langkah_analisis\s*>.*?<\s*/\s*langkah_analisis\s*>', re.DOTALL | re.IGNORECASE)
_ANALYSIS_OPEN_RE = re.compile(r'<\s*langkah_analisis\s*>', re.IGNORECASE)
_ANALYSIS_CLOSE_RE = re.compile(r'<\s*/\s*langkah_analisis\s*>', re.IGNORECASE)
# Judul baris yang HANYA muncul di template langkah_analisis, tidak pernah di narasi prosa.
_LEAKED_ANALYSIS_HEADINGS_RE = re.compile(
    r'^\s*\d+\.\s*(tipe periodisasi|periodisasi\s*&?\s*satuan|nilai tertinggi dan terendah|analisis\s*\d*\s*data terakhir|dua data terakhir)',
    re.IGNORECASE | re.MULTILINE
)

def strip_analysis_tag(text: str) -> str:
    """Buang blok <langkah_analisis>...</langkah_analisis>. Jika tag pembuka ada
    tapi tag penutup hilang (generasi terpotong), tutup dulu di akhir teks supaya
    sisa chain-of-thought yang terpotong ikut terbuang, bukan malah lolos ke user."""
    if not text:
        return ""
    if _ANALYSIS_OPEN_RE.search(text) and not _ANALYSIS_CLOSE_RE.search(text):
        text = text + "</langkah_analisis>"
    return _ANALYSIS_TAG_RE.sub('', text).strip()

def looks_like_leaked_analysis(text: str) -> bool:
    """Heuristik: teks yang tersisa setelah stripping ternyata masih berupa
    bocoran langkah analisis (daftar bernomor/berpoin pendek), bukan narasi
    prosa 3 paragraf. Ini terjadi saat model TIDAK PERNAH memakai tag
    <langkah_analisis> sama sekali (jadi tidak ada yang bisa di-strip) atau
    berhenti generate sebelum sempat menulis narasi."""
    if not text:
        return True
    if _LEAKED_ANALYSIS_HEADINGS_RE.search(text):
        return True
    lines = [l for l in text.strip().splitlines() if l.strip()]
    if not lines:
        return True
    list_like = sum(1 for l in lines if re.match(r'^\s*(\d+\.|\*|-)\s', l))
    # Narasi golden umumnya >500 karakter & berupa paragraf, bukan daftar.
    if len(text) < 500 and list_like >= max(2, len(lines) // 2):
        return True
    return False

def generate_via_groq_fallback(category, subject, indicator, data_table, rag_query,
                                groq_model="llama-3.3-70b-versatile", context=None, extra_instruction=""):
    """Jalankan narasi via Groq Cloud dengan prompt ringkas. Dipakai sebagai (1) fallback
    saat provider utama gagal/timeout, dan (2) retry saat output provider utama terdeteksi
    hanya berisi langkah analisis (biasanya karena generasi terpotong oleh max_tokens)."""
    groq_system = get_groq_compact_prompt() + extra_instruction
    # Reuse context yang sudah diambil kalau ada (hemat 1 panggilan Qdrant); kalau tidak,
    # ambil ulang dengan limit kecil supaya muat di TPM limit Groq.
    groq_context = context if context is not None else get_rag_context(rag_query, limit=1)
    groq_user = f"""Kategori: {category}
Indikator: {indicator}
Wilayah: {subject}

Referensi:
{groq_context}

Data:
{data_table}

Tulis narasi analisis sesuai instruksi sistem."""

    # Temperature mengikuti anjuran resmi pengembang tiap model:
    # - GPT-OSS  : 1.0 (OpenAI, README gpt-oss bagian "Recommended Sampling Parameters")
    # - Llama 3.3: 0.6 (Meta, contoh kode resmi Llama 3 di example_chat_completion.py)
    temperature = 1.0 if "gpt-oss" in groq_model else 0.6

    chat = client_groq.chat.completions.create(
        model=groq_model,
        messages=[
            {"role": "system", "content": groq_system},
            {"role": "user", "content": groq_user}
        ],
        temperature=temperature,
        max_tokens=4096
    )
    return chat.choices[0].message.content

def pilih_provider(target_model: str) -> str:
    """Tentukan provider mana yang melayani sebuah model_id: "gemini", "groq", atau "hf".

    SATU SUMBER KEBENARAN — dipakai baik untuk memilih cabang di call_narrative_provider
    maupun untuk menentukan ukuran konteks RAG di generate_narrative. Dulu kedua tempat itu
    punya kondisi sendiri-sendiri dan sempat tidak sinkron.

    Catatan penting: id model di katalog Laravel memakai penamaan ala Hugging Face
    ("meta-llama/Llama-3.3-70B-Instruct", "openai/gpt-oss-120b"), padahal Groq-lah yang
    benar-benar melayani kedua model itu untuk kita. Jadi keduanya sengaja diarahkan ke Groq.
    Ini BUKAN "ganti model otomatis saat gagal" — ini keputusan routing yang tetap dan
    bisa diprediksi. Nama model diterjemahkan ke penamaan Groq di dalam cabangnya."""
    tl = target_model.lower()
    if "gemini" in tl:
        return "gemini"
    if "groq" in tl or "versatile" in tl or "llama" in tl or "gpt-oss" in tl or "120b" in tl:
        return "groq"
    return "hf"

def call_narrative_provider(target_model, system_prompt, user_prompt, context, rag_query,
                             category, subject, indicator, data_table, extra_instruction=""):
    """Dispatch ke provider (Gemini/Groq/HF) sesuai target_model yang DIPILIH USER, dan
    kembalikan (narrative, final_model_label).

    TIDAK melakukan fallback ke provider/model lain — kalau provider yang dipilih gagal,
    exception dilempar apa adanya ke pemanggil. ini SENGAJA: user tidak mau sistem diam-diam
    mengalihkan ke model lain saat gagal; kalau gagal, tampilkan apa adanya supaya user tahu
    persis model mana yang bermasalah dan bisa memilih model lain secara manual."""
    tl = target_model.lower()
    provider = pilih_provider(target_model)

    # 1. GOOGLE GEMINI
    if provider == "gemini":
        if not client_gemini:
            raise Exception("Koneksi API Gemini belum dikonfigurasi (GEMINI_API_KEY kosong).")
        print(f"🚀 Mengerjakan via Google Gemini API ({target_model})...", flush=True)

        full_system_prompt = system_prompt + extra_instruction
        if "3.5" in tl or "3.6" in tl:
            combined_input = f"{full_system_prompt}\n\n{user_prompt}"
            interaction = client_gemini.interactions.create(
                model=target_model,
                input=combined_input
            )
            narrative = interaction.output_text
        else:
            contents = [
                types.Content(
                    role="user",
                    parts=[types.Part.from_text(text=user_prompt)],
                ),
            ]
            t_level = "MINIMAL" if "lite" in tl else "HIGH"
            # temperature sengaja TIDAK diatur (bawaan 1.0): Google menganjurkan Gemini 3 tetap di 1.0;
            # di bawah 1.0 model bisa berputar-putar (looping) atau kualitas penalarannya turun.
            generate_content_config = types.GenerateContentConfig(
                system_instruction=full_system_prompt,
                max_output_tokens=4096,
                thinking_config=types.ThinkingConfig(thinking_level=t_level),
                automatic_function_calling=types.AutomaticFunctionCallingConfig(disable=True)
            )
            response = client_gemini.models.generate_content(
                model=target_model,
                contents=contents,
                config=generate_content_config,
            )
            narrative = response.text
        return narrative, target_model

    # 2. GROQ (termasuk Llama 3.3 & GPT-OSS dari katalog, lihat pilih_provider)
    elif provider == "groq":
        if not client_groq:
            raise Exception("Koneksi Groq belum dikonfigurasi (GROQ_API_KEY kosong).")

        # Pemetaan nama model ke format Groq Cloud
        if "versatile" in tl or "llama" in tl:
            groq_model = "llama-3.3-70b-versatile"
        elif "gpt-oss" in tl or "120b" in tl:
            groq_model = "openai/gpt-oss-120b"
        else:
            groq_model = "llama-3.3-70b-versatile"

        print(f"🚀 Mengerjakan langsung via Groq Cloud API ({groq_model})...", flush=True)
        narrative = generate_via_groq_fallback(
            category, subject, indicator, data_table, rag_query,
            groq_model=groq_model, context=context, extra_instruction=extra_instruction
        )
        return narrative, f"Groq ({groq_model})"

    # 3. HUGGING FACE
    else:
        if not client_hf:
            raise Exception("Koneksi Hugging Face belum dikonfigurasi (HF_TOKEN kosong).")
        print(f"🚀 Mengerjakan via Hugging Face Inference API ({target_model})...", flush=True)

        messages = [
            {"role": "system", "content": system_prompt + extra_instruction},
            {"role": "user", "content": user_prompt}
        ]
        chat = client_hf.chat.completions.create(
            model=target_model,
            messages=messages,
            temperature=0.3,
            max_tokens=4096
        )
        narrative = chat.choices[0].message.content
        return narrative, target_model

@app.post("/generate-narrative")
async def generate_narrative(input_data: BPSDataInput):
    data_table = parse_table(input_data.data_json)
    # Query RAG memakai KONSEP-nya, bukan nama indikator mentah yang sering berupa judul tabel
    # (lihat konsep_dari_nama_indikator). Nama lengkap tetap dipakai di prompt ke model.
    rag_query = f"Definisi konsep {konsep_dari_nama_indikator(input_data.indicator)} dan cara interpretasinya menurut BPS."
    print(f"🔎 Query RAG: {rag_query}", flush=True)
    
    # Groq punya batas token per menit yang ketat, jadi konteks referensinya dipersempit.
    # Memakai pilih_provider() supaya SELALU sinkron dengan cabang yang benar-benar dipakai
    # di call_narrative_provider.
    target_model = input_data.model_id
    is_groq_route = pilih_provider(target_model) == "groq"
    rag_limit = 1 if is_groq_route else 3
    context = get_rag_context(rag_query, limit=rag_limit)
    
    system_prompt = """ Reasoning: high
    Anda adalah Senior Data Analyst dan Kepala Editor Publikasi Badan Pusat Statistik (BPS) dengan pengalaman lebih dari 20 tahun dalam menyusun Berita Resmi Statistik.
    Tugas Anda adalah menyusun narasi analisis statistik yang Mendalam, Akurat Secara Matematis, Lengkap, dan 100% Bahasa Indonesia Baku.
    Narasi harus setara kualitasnya dengan publikasi resmi BPS tingkat nasional.

    ================================================================
    FASE 1 — PRA-ANALISIS WAJIB (CHAIN OF THOUGHT EKSPLISIT)
    ================================================================
    SEBELUM Anda menulis narasi akhir, Anda WAJIB menjabarkan analisis data dan logika Anda di dalam tag XML <langkah_analisis>.
    LLM tidak memiliki pikiran tersembunyi, jadi Anda harus menuliskan langkah-langkah pencarian data Anda selangkah demi selangkah.
    
    Format output yang diwajibkan:
    <langkah_analisis>
    1. Tipe periodisasi: [Analisis...]
    2. Nilai tertinggi dan terendah: [...]
    3. Analisis 2 data terakhir: [...]
    </langkah_analisis>
    
    [MULAI TULIS NARASI 3 PARAGRAF ANDA DI SINI SETELAH TAG DITUTUP]
    
    Ikuti panduan berikut saat menyusun <langkah_analisis> dan narasi:

    Langkah 1 — Inventarisasi dan Validasi Data:
    - Catat SEMUA titik data beserta labelnya (tahun/bulan) secara berurutan kronologis.
    - Tentukan rentang waktu: periode paling awal hingga paling akhir.
    - Hitung jumlah total titik data (observasi).
    - Identifikasi SATUAN data: apakah persen, jiwa, rupiah, indeks, rasio, ton, hektar, atau satuan lain. Satuan ini WAJIB disebut di narasi.

    Langkah 2 — Deteksi Tipe Periodisasi:
    - Jika label data berisi nama BULAN (Januari, Februari, Jan, Feb, dst.) atau format bulan-tahun: tipe = BULANAN.
    - Jika label data hanya berisi TAHUN (2019, 2020, 2021, dst.): tipe = TAHUNAN.
    - Jika label data berisi TRIWULAN/KUARTAL (Q1, Q2, Triwulan I, dst.): tipe = TRIWULANAN.
    - Tipe periodisasi menentukan cara penulisan Paragraf 3 dan istilah perbandingan yang digunakan.

    Langkah 3 — Identifikasi Nilai Ekstrem (HEMAT TOKEN):
    - DILARANG menuliskan perhitungan selisih/persentase untuk SETIAP tahun di sini. Lakukan kalkulasi tersebut di memori internal Anda saja.
    - Cari nilai TERTINGGI sepanjang seri data: catat nilainya dan periodenya.
    - Cari nilai TERENDAH sepanjang seri data: catat nilainya dan periodenya.
    - DILARANG menghitung rata-rata keseluruhan.
    - Jika data mencakup BANYAK wilayah/kategori (kolom geografis/kategorikal dengan lebih dari 3 nilai unik), DILARANG menguraikan hasil setiap wilayah/kategori satu per satu di sini. Cukup catat SATU nilai tertinggi dan SATU nilai terendah secara keseluruhan beserta label wilayah/kategorinya.
    
    Langkah 4 — Fokus pada Analisis Terkini:
    - Pastikan Anda HANYA mencatat perhitungan detail (selisih dan persentase) untuk 2 DATA TERAKHIR saja.

    Langkah 5 — Analisis Tren Dominan:
    - Tentukan pola tren makro sepanjang seri data:
      * Konsisten naik / konsisten turun / fluktuatif / stagnan
      * Jika fluktuatif: identifikasi fase-fase pergerakan (misalnya "naik pada 2019-2021, lalu turun pada 2022-2023, kemudian naik kembali pada 2024").
    - Identifikasi titik balik (turning point): periode dimana arah berubah dari naik ke turun atau sebaliknya.

    Langkah 6 — Analisis Perubahan Terkini (Dua Data Terakhir):
    - Fokus pada perbandingan DUA titik data TERAKHIR.
    - Hitung WAJIB:
      a) Selisih absolut: Nilai Terbaru - Nilai Sebelumnya
      b) Persentase perubahan: ((Nilai Terbaru - Nilai Sebelumnya) / |Nilai Sebelumnya|) x 100
    - Tentukan apakah perubahan terkini ini berupa lonjakan tajam, perlambatan, stabil, atau berbalik arah.
    - Untuk data BULANAN, hitung TAMBAHAN jika data tersedia:
      c) Perbandingan dengan bulan yang SAMA di tahun sebelumnya (tahunan):
         Selisih Tahunan = Nilai Bulan Ini - Nilai Bulan Sama Tahun Lalu
         Persen Tahunan = ((Nilai Bulan Ini - Nilai Bulan Sama Thn Lalu) / |Nilai Bulan Sama Thn Lalu|) x 100
    - Semua angka hasil kalkulasi ini WAJIB dicantumkan di narasi.

    Langkah 7 — Verifikasi Definisi dari Referensi:
    - Baca blok [KONTEKS REFERENSI] dengan seksama.
    - Ekstrak INTI definisi konsep indikator.
    - WAJIB terjemahkan ke Bahasa Indonesia formal jika teks referensi berbahasa Inggris.
    - Ambil MAKNA dan CARA INTERPRETASI, bukan rumus atau notasi matematika.
    - Jika referensi menyebutkan cara membaca angka (misal: "semakin tinggi berarti semakin baik" atau "di bawah 100 berarti kontraksi"), informasi ini WAJIB masuk ke narasi.
    - Jika tidak ada konteks referensi yang relevan, tetap tulis definisi umum berdasarkan pengetahuan statistik resmi BPS.

    Langkah 8 — Perencanaan Struktur Narasi:
    - Susun kerangka narasi: kalimat pembuka definisi, transisi ke data historis, lalu analisis terkini.
    - Pastikan setiap paragraf memiliki FOKUS yang jelas dan tidak ada pengulangan informasi.
    - Pastikan SETIAP angka yang akan disebutkan sudah diverifikasi terhadap data asli.

    ================================================================
    FASE 2 — PENULISAN NARASI
    ================================================================

    GAYA BAHASA DAN FORMAT WAJIB:
    - Nada: Formal, objektif, birokratis, khas laporan resmi pemerintah Indonesia.
    - Angka desimal: WAJIB gunakan KOMA (,) sebagai pemisah desimal. Contoh benar: 14,52. Contoh salah: 14.52.
    - Angka ribuan: WAJIB gunakan TITIK (.) sebagai pemisah ribuan. Contoh benar: 1.234.567. Contoh salah: 1,234,567.
    - Pembulatan: Ikuti jumlah desimal yang ada di data asli. Jangan membulatkan sendiri.
    - Satuan: WAJIB sebutkan satuan di setiap angka (persen, jiwa, rupiah, indeks, ton, hektar, dst.).
    - Presisi: Sebutkan angka PERSIS seperti di data. DILARANG memperkirakan atau membulatkan.

    Diksi Analisis yang WAJIB Digunakan (pilih yang sesuai konteks):
    - Untuk menyebut nilai: "tercatat sebesar", "berada pada angka", "mencapai level"
    - Untuk kenaikan: "mengalami peningkatan sebesar X [satuan]", "meningkat sebesar X [satuan]", "meningkat sebesar X [satuan/poin persentase] atau sebesar Y persen [secara relatif]"
    - Untuk penurunan: "mengalami penurunan sebesar X [satuan]", "menurun sebesar X [satuan]", "menurun sebesar X [satuan/poin persentase] atau sebesar Y persen [secara relatif]"
    - Untuk perbandingan: "dibandingkan dengan periode sebelumnya", "jika dibandingkan dengan", "relatif terhadap"
    - Untuk tren: "menunjukkan tren yang meningkat", "cenderung mengalami penurunan", "bergerak fluktuatif"
    - Untuk ekstrem: "mencapai titik tertinggi", "berada pada titik terendah", "merupakan capaian tertinggi sepanjang periode pengamatan"
    - Untuk konteks: "angka ini menandakan", "kondisi ini mengindikasikan", "capaian tersebut menunjukkan"

    ================================================================
    STRUKTUR NARASI WAJIB (3-4 PARAGRAF):
    ================================================================

    PARAGRAF 1 — Definisi dan Konteks Indikator:
    - Kalimat pertama: Langsung jelaskan APA indikator ini, bagaimana cara mengukurnya, dan apa maknanya.
    - JANGAN gunakan pembuka "Mengacu pada..." atau sejenisnya. Langsung ke substansi definisi.
    - Jika referensi menyebutkan cara interpretasi (misal "semakin tinggi semakin baik" atau "di atas 100 berarti ekspansi"), WAJIB sertakan.
    - Kalimat terakhir paragraf: sebutkan rentang periode data yang tersedia, contoh: "Berdasarkan data yang tersedia dari tahun 2019 hingga 2024, berikut adalah perkembangan [indikator] di [wilayah]."
    - Paragraf ini harus 2-4 kalimat.

    PARAGRAF 2 — Dinamika Historis dan Nilai Ekstrem:
    - Deskripsikan pergerakan data secara kronologis dan menyeluruh.
    - JANGAN hanya menyebut tertinggi dan terendah. Ceritakan PERJALANAN datanya: kapan naik, kapan turun, apakah ada titik balik.
    - Jika ada fase-fase berbeda, sebutkan: "Pada periode [tahun]-[tahun], [indikator] menunjukkan tren [naik/turun], kemudian pada [tahun]-[tahun] terjadi [perubahan arah]."
    - Sebutkan secara eksplisit nilai TERTINGGI dan TERENDAH beserta periodenya.
    - DILARANG menyebutkan nilai rata-rata historis.
    - Jika ada anomali atau lonjakan tidak biasa, sebutkan dan berikan konteks jika memungkinkan.
    - Paragraf ini harus 3-5 kalimat.

    PARAGRAF 3 — Analisis Perubahan Terkini (PALING PENTING):
    - Paragraf ini adalah INTI dari narasi. Harus paling detail dan presisi.
    - Fokus pada perbandingan DUA data TERAKHIR.
    - WAJIB cantumkan SEMUA angka berikut dalam narasi:
      1) Nilai terbaru (periode terbaru) dengan satuan
      2) Nilai sebelumnya (periode sebelumnya) dengan satuan
      3) Selisih absolut dengan satuan
      4) Persentase perubahan (dengan keterangan "persen")
      5) Arah perubahan (naik/turun/stagnan)

    - ATURAN KHUSUS UNTUK DATA BERSATUAN "PERSEN" (Contoh: Inflasi, TPAK, TPT, Kemiskinan):
      * Selisih absolut WAJIB disebut sebagai "poin persentase" (jangan gunakan kata "persen").
      * Persentase perubahan WAJIB disebut sebagai "persen secara relatif".
      * Jika satuan BUKAN persen (misal: jiwa, rupiah), gunakan "persen" biasa untuk persentase perubahan.

    - Untuk data TAHUNAN, gunakan pola kalimat:
      "Pada tahun [terbaru], [indikator] di [wilayah] tercatat sebesar [nilai baru] [satuan], [meningkat/menurun] sebesar [selisih absolut] [satuan/poin persentase] atau sebesar [X] persen [secara relatif] dibandingkan dengan tahun [sebelumnya] yang tercatat sebesar [nilai lama] [satuan]."

    - Untuk data BULANAN, WAJIB tulis DUA perbandingan:
      Kalimat 1 (perbandingan bulan ke bulan): "Pada [Bulan] [Tahun], [indikator] tercatat sebesar [nilai baru] [satuan], [meningkat/menurun] sebesar [selisih bulan lalu] [satuan/poin persentase] atau sebesar [X] persen [secara relatif] dibandingkan dengan [Bulan sebelumnya] [Tahun] yang tercatat sebesar [nilai lama] [satuan]."
      Kalimat 2 (perbandingan tahunan, JIKA DATA TERSEDIA): "Jika dibandingkan dengan [Bulan yang sama] tahun [lalu] yang tercatat sebesar [nilai lama tahun lalu] [satuan], terjadi [peningkatan/penurunan] sebesar [selisih tahun lalu] [satuan/poin persentase] atau sebesar [X] persen [secara relatif]."
      Jika data bulan yang sama tahun lalu TIDAK tersedia, LEWATI kalimat kedua. JANGAN mengarang angka.

    - Untuk data TRIWULANAN, gunakan pola serupa dengan bulanan: bandingkan dengan triwulan sebelumnya DAN triwulan yang sama tahun lalu jika data tersedia.

    - Tambahkan kalimat kontekstualisasi: bandingkan perubahan terkini dengan tren historis atau posisinya terhadap rekor ekstrem (tertinggi/terendah).
      Contoh: "Perubahan ini melanjutkan tren positif yang terjadi sejak tahun lalu."
      Contoh: "Dengan capaian ini, [indikator] di [wilayah] mencatatkan rekor tertinggi sepanjang periode pengamatan."
    - Paragraf ini harus 3-5 kalimat.

    PARAGRAF 4 (OPSIONAL) — Detail Tambahan:
    - Paragraf ini HANYA ditulis jika data mengandung sub-kategori, komponen, atau breakdown wilayah.
    - Jika data hanya berisi satu seri angka tanpa sub-komponen, JANGAN tulis paragraf ini.
    - Jika ada: deskripsikan komponen mana yang berkontribusi paling besar terhadap perubahan.

    ================================================================
    CONTOH NARASI BERKUALITAS TINGGI (GOLD STANDARD):
    ================================================================

    Contoh untuk data TAHUNAN (Indeks Pembangunan Manusia):
    "Indeks Pembangunan Manusia (IPM) merupakan indikator komposit yang mengukur capaian pembangunan manusia berbasis sejumlah komponen dasar kualitas hidup, meliputi dimensi umur panjang dan hidup sehat, pengetahuan, serta standar hidup layak. Semakin tinggi nilai IPM, semakin baik kualitas pembangunan manusia di suatu wilayah. Berdasarkan data yang tersedia dari tahun 2019 hingga 2024, berikut adalah perkembangan IPM di Kabupaten Contoh.

    Sepanjang periode 2019 hingga 2024, IPM Kabupaten Contoh menunjukkan tren yang konsisten meningkat. Pada tahun 2019, IPM tercatat sebesar 68,45 dan terus mengalami kenaikan hingga mencapai 70,82 pada tahun 2024, kecuali pada tahun 2020 yang mengalami sedikit perlambatan dengan kenaikan hanya sebesar 0,12 poin. Nilai tertinggi tercatat pada tahun 2024 sebesar 70,82, sedangkan nilai terendah terjadi pada tahun 2019 sebesar 68,45.

    Pada tahun 2024, IPM Kabupaten Contoh tercatat sebesar 70,82, mengalami kenaikan sebesar 0,54 poin atau setara dengan 0,77 persen dibandingkan tahun 2023 yang tercatat sebesar 70,28. Kenaikan ini memperkuat tren pertumbuhan IPM yang stabil dalam lima tahun terakhir. Dengan capaian 70,82, IPM Kabupaten Contoh telah melampaui ambang batas 70 yang mengkategorikan wilayah tersebut dalam kelompok pembangunan manusia tingkat tinggi."

    Contoh untuk data BULANAN (Inflasi):
    "Inflasi merupakan kecenderungan naiknya harga barang dan jasa secara umum dan terus-menerus dalam jangka waktu tertentu. Tingkat inflasi dihitung berdasarkan perubahan Indeks Harga Konsumen (IHK) dari satu periode ke periode berikutnya. Nilai inflasi positif menunjukkan terjadinya kenaikan harga secara umum, sedangkan nilai negatif (deflasi) menunjukkan penurunan harga. Berdasarkan data yang tersedia dari Januari 2024 hingga Juni 2025, berikut adalah perkembangan inflasi bulanan di Kota Contoh.

    Sepanjang periode Januari 2024 hingga Juni 2025, inflasi bulanan Kota Contoh bergerak fluktuatif dengan kisaran antara negatif 0,12 persen hingga 1,05 persen. Inflasi tertinggi tercatat pada Desember 2024 sebesar 1,05 persen, sementara deflasi terjadi pada Maret 2025 sebesar negatif 0,12 persen. Secara umum, pola inflasi menunjukkan kecenderungan meningkat pada akhir tahun dan melambat pada awal tahun berikutnya.

    Pada Juni 2025, inflasi Kota Contoh tercatat sebesar 0,34 persen, mengalami kenaikan sebesar 0,16 poin atau setara dengan 88,89 persen dibandingkan Mei 2025 yang tercatat sebesar 0,18 persen. Jika dibandingkan dengan Juni 2024 yang tercatat sebesar 0,27 persen, terjadi kenaikan sebesar 0,07 poin atau setara dengan 25,93 persen. Tingkat inflasi bulan ini merupakan yang tertinggi dalam semester pertama tahun 2025."

    ================================================================
    ATURAN MUTLAK (PELANGGARAN BERARTI GAGAL TOTAL):
    ================================================================
    1. DILARANG menambahkan judul, header, label, atau penanda apapun di atas atau di bawah narasi. Output LANGSUNG dimulai dari kalimat pertama narasi.
    2. DILARANG menggunakan simbol pemformatan Markdown: tanda bintang (*), tanda pagar (#), underscore (_), bullet points (-), atau penomoran (1. 2. 3.). Tulis narasi murni teks paragraf.
    3. DILARANG menambahkan kalimat kesimpulan, opini, proyeksi masa depan, rekomendasi kebijakan, atau kalimat normatif seperti "Hal ini menunjukkan perlunya..." atau "Diperlukan upaya untuk...".
    4. DILARANG menggunakan kata/frasa/istilah Bahasa Inggris apapun. WAJIB terjemahkan ke Bahasa Indonesia baku. Contoh: "growth" menjadi "pertumbuhan", "year-on-year" menjadi "dibandingkan periode yang sama tahun sebelumnya".
    5. DILARANG mengarang, memperkirakan, atau membulatkan angka yang tidak ada di data. Setiap angka HARUS merujuk ke data yang diberikan atau hasil kalkulasi yang bisa diverifikasi.
    6. DILARANG menyalin rumus, notasi matematika, atau formula mentah dari referensi ke dalam narasi. Jelaskan MAKNANYA, bukan rumusnya.
    7. Output HARUS berakhir tepat setelah analisis angka terakhir. JANGAN menambahkan kalimat penutup.
    8. Setiap angka yang disebutkan WAJIB disertai SATUAN (persen, poin, jiwa, rupiah, indeks, dst.).
    9. Jika terdapat istilah asing pada dokumen referensi, WAJIB diparafrase ke dalam istilah resmi BPS dalam Bahasa Indonesia.
    10. DILARANG memulai narasi dengan "Mengacu pada..." atau merujuk nama publikasi tertentu di kalimat pembuka.
    11. DILARANG KERAS menggunakan istilah teknis yang susah dimengerti awam seperti "delta", "delta absolut", "MoM", "YoY", "observasi", "anomali", atau "titik data". Gunakan bahasa Indonesia awam yang ramah pembaca seperti "selisih", "dibandingkan tahun lalu", "dibandingkan bulan sebelumnya", "perubahan", "perkembangan".
    12. Gunakan gaya bahasa jurnalistik naratif yang mengalir dan mudah dipahami oleh masyarakat umum, namun tetap menjaga akurasi matematis.
    """
    
    user_prompt = f"""
    ================================================================
    INFORMASI DATA YANG AKAN DIANALISIS
    ================================================================
    Kategori : {input_data.category}
    Indikator: {input_data.indicator}
    Wilayah  : {input_data.subject}

    ================================================================
    KONTEKS REFERENSI / DEFINISI RESMI DARI KNOWLEDGE BASE
    ================================================================
    Gunakan referensi berikut sebagai DASAR definisi indikator.
    WAJIB parafrase ke Bahasa Indonesia formal. JANGAN salin mentah.
    Jika referensi berisi cara interpretasi angka, WAJIB masukkan ke narasi.
    Jika referensi tidak relevan atau kosong, tulis definisi berdasarkan pengetahuan statistik resmi BPS.

    {context}

    ================================================================
    DATA STATISTIK YANG HARUS DIANALISIS
    ================================================================
    {data_table}

    ================================================================
    CHECKLIST EKSEKUSI (IKUTI URUTAN INI)
    ================================================================
    1. Tulis tag <langkah_analisis> dan lakukan SEMUA langkah Pra-Analisis (Langkah 1-8) di dalamnya.
    2. DETEKSI tipe periodisasi data: TAHUNAN, BULANAN, atau TRIWULANAN.
    3. IDENTIFIKASI satuan data dari konteks indikator (persen, jiwa, indeks, rupiah, ton, dst.).
    4. HITUNG nilai tertinggi dan terendah sepanjang seri data.
    5. DILARANG mendaftar/menulis perhitungan selisih untuk setiap tahun di tag langkah_analisis (lakukan dalam memori saja untuk menghemat token).
    6. KHUSUS dua data terakhir: hitung selisih absolut DAN persentase perubahan.
       Jika BULANAN: hitung juga perbandingan bulan yang sama tahun lalu (jika datanya ada di tabel).
    7. BANDINGKAN perubahan terkini dengan tren pergerakan historis (apakah melambat, stabil, atau melonjak).
    8. TULIS narasi sesuai struktur dan gaya bahasa yang ditentukan.
    9. VERIFIKASI: periksa ulang setiap angka di narasi terhadap data asli dan hasil kalkulasi.
    10. PASTIKAN tidak ada pelanggaran aturan mutlak sebelum mengeluarkan output.
    """
    
    target_model = input_data.model_id

    # TIDAK ADA fallback ke provider/model lain di sini — sesuai permintaan, kalau model
    # yang dipilih user gagal total (quota habis, API down, dll), error aslinya langsung
    # dikembalikan ke user apa adanya, bukan diam-diam dialihkan ke model lain.
    mulai = time.monotonic()
    try:
        narrative, final_model = call_narrative_provider(
            target_model, system_prompt, user_prompt, context, rag_query,
            input_data.category, input_data.subject, input_data.indicator, data_table
        )
    except Exception as e:
        error_msg = f"Model {target_model} gagal memproses data. Detail: {str(e)}"
        print(f"❌ {error_msg}", flush=True)

        return {
            "status": "error",
            "error_message": error_msg,
            "used_model": target_model,
            "rag_preview": context[:300] + "..." if context else ""
        }

    durasi_percobaan_1 = time.monotonic() - mulai
    print(f"⏱️ Percobaan pertama ({target_model}) selesai dalam {durasi_percobaan_1:.1f} detik.", flush=True)

    cleaned_narrative = strip_analysis_tag(narrative)

    # --- SAFETY NET: kalau hasil ternyata cuma bocoran langkah_analisis (model lupa
    # pakai tag, atau generasi terpotong sebelum sempat menulis narasi), coba ULANG
    # SEKALI dengan MODEL YANG SAMA (target_model) plus pengingat lebih tegas — BUKAN
    # pindah ke provider/model lain. Ini tidak menambah latensi pada jalur normal,
    # hanya jalan kalau percobaan pertama memang menghasilkan output yang rusak.
    #
    # Retry HANYA dimulai kalau jatah waktunya masih cukup. Percobaan kedua kira-kira
    # selama percobaan pertama, jadi kalau sisa waktu tidak muat, lebih baik langsung
    # mengembalikan pesan kegagalan daripada memulai pekerjaan yang pasti keburu
    # diputus Laravel (hasil terbuang, kuota model tetap terpakai).
    if looks_like_leaked_analysis(cleaned_narrative):
        sisa_waktu = NARRATIVE_BUDGET_SECONDS - (time.monotonic() - mulai)
        if sisa_waktu < durasi_percobaan_1 * 1.2:
            print(
                f"⏭️ [RETRY DILEWATI] Sisa waktu {sisa_waktu:.0f} detik tidak cukup untuk "
                f"percobaan kedua (perkiraan ~{durasi_percobaan_1:.0f} detik).",
                flush=True
            )
            cleaned_narrative = ""  # biar jatuh ke pesan kegagalan di bawah
        else:
            print(f"⚠️ [RETRY] Output dari {target_model} hanya berisi langkah analisis (kemungkinan terpotong/tag hilang). Mengulang sekali dengan model yang sama (sisa {sisa_waktu:.0f} detik)...", flush=True)
            try:
                retry_narrative, _ = call_narrative_provider(
                    target_model, system_prompt, user_prompt, context, rag_query,
                    input_data.category, input_data.subject, input_data.indicator, data_table,
                    extra_instruction=(
                        "\n\nPERHATIAN: Percobaan sebelumnya GAGAL karena hanya menghasilkan langkah "
                        "analisis tanpa narasi akhir. WAJIB tutup tag </langkah_analisis> lebih awal dan "
                        "lebih singkat, lalu LANGSUNG tulis narasi 3 paragraf lengkap sesudahnya."
                    )
                )
                retry_cleaned = strip_analysis_tag(retry_narrative)
                if retry_cleaned and not looks_like_leaked_analysis(retry_cleaned):
                    cleaned_narrative = retry_cleaned
                    final_model = f"{final_model} [Retry]"
                    print(f"✅ [RETRY] Berhasil menghasilkan narasi lengkap pada percobaan kedua (model sama: {target_model}, total {time.monotonic() - mulai:.1f} detik).", flush=True)
            except Exception as retry_error:
                print(f"⚠️ [RETRY] Percobaan ulang gagal: {retry_error}", flush=True)

    if not cleaned_narrative or looks_like_leaked_analysis(cleaned_narrative):
        cleaned_narrative = "⚠️ Teks gagal diproses secara penuh karena data terlalu panjang (Hit Max Tokens Limit). AI kehabisan napas saat menghitung data. Coba gunakan model LLM yang berbeda atau kurangi jumlah baris data."

    return {
        "status": "success",
        "narrative_result": cleaned_narrative,
        "used_model": final_model,
        "rag_preview": context[:300] + "..."
    }