"""
=============================================================
  SALIN COLLECTION QDRANT - PRANATA AI
=============================================================
  Menyalin semua point (vektor + payload, ID sama) dari satu
  collection ke collection lain. Tidak ada embedding ulang dan
  tidak memakai GPU, jadi hanya butuh beberapa menit.

  - Collection tujuan belum ada  -> dibuat dengan pengaturan
    vektor yang sama, lalu diisi.
  - Collection tujuan sudah berisi -> WAJIB --ganti. Data baru
    disalin DULU, baru data lama dihapus, sehingga collection
    tidak pernah kosong (Space tetap bisa mencari selama proses).

  CARA PAKAI (dari folder scripts/):
    # cadangkan v1 ke collection baru
    python salin_collection.py --dari bps_knowledge_bge_m3_v1 --ke bps_knowledge_bge_m3_v1_lama
    # ganti isi v1 dengan isi v4
    python salin_collection.py --dari bps_knowledge_bge_m3_v4 --ke bps_knowledge_bge_m3_v1 --ganti
    # mengembalikan v1 lama (kalau perlu)
    python salin_collection.py --dari bps_knowledge_bge_m3_v1_lama --ke bps_knowledge_bge_m3_v1 --ganti
=============================================================
"""

import io
import os
import sys
import argparse
from pathlib import Path

# line_buffering: supaya progres langsung tampil di terminal, bukan saat skrip selesai
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", line_buffering=True)

from dotenv import load_dotenv
from qdrant_client import QdrantClient, models

load_dotenv(dotenv_path=Path(__file__).parent / ".env")

# 256 point x 1024 dimensi per request: jauh di bawah batas ukuran request Qdrant
BATCH = 256


def semua_id(client, collection):
    ids, offset = set(), None
    while True:
        points, offset = client.scroll(collection_name=collection, limit=2000, offset=offset,
                                       with_payload=False, with_vectors=False)
        ids.update(p.id for p in points)
        if offset is None:
            return ids


def main():
    parser = argparse.ArgumentParser(description="Salin collection Qdrant")
    parser.add_argument("--dari", required=True, help="collection sumber")
    parser.add_argument("--ke", required=True, help="collection tujuan")
    parser.add_argument("--ganti", action="store_true",
                        help="kalau tujuan sudah berisi: ganti isinya dengan isi sumber")
    args = parser.parse_args()

    client = QdrantClient(url=os.getenv("QDRANT_URL"), api_key=os.getenv("QDRANT_API_KEY"), timeout=300,
                          check_compatibility=False)

    if not client.collection_exists(args.dari):
        print(f"❌ Collection sumber '{args.dari}' tidak ada.")
        sys.exit(1)
    jumlah_sumber = client.count(collection_name=args.dari, exact=True).count

    id_lama = set()
    if client.collection_exists(args.ke):
        id_lama = semua_id(client, args.ke)
        if id_lama and not args.ganti:
            print(f"❌ '{args.ke}' sudah berisi {len(id_lama):,} point. Tambahkan --ganti untuk mengganti isinya.")
            sys.exit(1)
        print(f"📦 '{args.ke}' sudah ada, berisi {len(id_lama):,} point (akan diganti).")
    else:
        vektor = client.get_collection(args.dari).config.params.vectors
        client.create_collection(collection_name=args.ke,
                                 vectors_config=models.VectorParams(size=vektor.size, distance=vektor.distance))
        print(f"📦 Collection '{args.ke}' dibuat ({vektor.size} dimensi, {vektor.distance}).")

    try:
        client.create_payload_index(collection_name=args.ke, field_name="metadata.source",
                                    field_schema=models.PayloadSchemaType.KEYWORD)
    except Exception as e:
        if "already exists" not in str(e).lower():
            print(f"⚠️  Payload index metadata.source: {e}")

    print(f"🚚 Menyalin {jumlah_sumber:,} point: {args.dari} -> {args.ke}")
    id_baru, offset, n = set(), None, 0
    while True:
        points, offset = client.scroll(collection_name=args.dari, limit=BATCH, offset=offset,
                                       with_payload=True, with_vectors=True)
        if points:
            client.upsert(collection_name=args.ke, wait=True, points=[
                models.PointStruct(id=p.id, vector=p.vector, payload=p.payload) for p in points])
            id_baru.update(p.id for p in points)
            n += len(points)
            if n % (BATCH * 20) < BATCH or offset is None:
                print(f"   {n:,}/{jumlah_sumber:,}")
        if offset is None:
            break

    # Data lama baru dihapus SETELAH data baru lengkap, jadi collection tidak pernah kosong.
    hapus = list(id_lama - id_baru)
    if hapus:
        print(f"🧹 Menghapus {len(hapus):,} point lama dari '{args.ke}' ...")
        for i in range(0, len(hapus), 500):
            client.delete(collection_name=args.ke, points_selector=models.PointIdsList(points=hapus[i:i + 500]),
                          wait=True)

    jumlah_tujuan = client.count(collection_name=args.ke, exact=True).count
    status = "✅ COCOK" if jumlah_tujuan == jumlah_sumber else "❌ TIDAK COCOK"
    print(f"\n{status}: {args.dari} = {jumlah_sumber:,} point | {args.ke} = {jumlah_tujuan:,} point")
    if jumlah_tujuan != jumlah_sumber:
        sys.exit(1)


if __name__ == "__main__":
    main()
