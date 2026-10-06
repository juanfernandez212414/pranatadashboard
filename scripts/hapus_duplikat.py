"""
=============================================================
  HAPUS CHUNK GANDA DI COLLECTION QDRANT - PRANATA AI
=============================================================
  Menghapus chunk yang tersimpan DUA KALI karena ingest untuk file
  yang sama sempat berjalan bersamaan (misalnya dua proses ingest).

  Chunk dianggap ganda kalau SUMBER, HALAMAN, dan TEKS-nya persis
  sama. Dari tiap kelompok satu disimpan, sisanya dihapus. Vektornya
  identik (teks sama, model sama), jadi yang mana pun yang disimpan
  hasilnya sama.

  Teks yang sama dari PDF BERBEDA (misalnya paragraf yang diulang di
  tiap tahun terbitan) TIDAK disentuh — itu memang isi korpus.

  Tidak memakai GPU dan tidak meng-embed ulang.

  CARA PAKAI (dari folder scripts/):
    # hanya melihat, tidak mengubah apa pun (bawaan):
    python hapus_duplikat.py --collection bps_knowledge_bge_m3_v3
    # benar-benar menghapus:
    python hapus_duplikat.py --collection bps_knowledge_bge_m3_v3 --hapus
=============================================================
"""

import io
import os
import sys
import argparse
from collections import defaultdict
from pathlib import Path

# line_buffering: tanpa ini semua print baru muncul saat skrip selesai, sehingga di terminal
# terlihat seperti macet selama pemindaian berlangsung.
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", line_buffering=True)

from dotenv import load_dotenv
from qdrant_client import QdrantClient, models

load_dotenv(dotenv_path=Path(__file__).parent / ".env")

HAPUS_BATCH = 500


def cari_ganda(client, collection):
    """Kembalikan {sumber: [id yang harus dihapus, ...]} dan jumlah point yang diperiksa."""
    kelompok = defaultdict(list)
    offset, total = None, 0
    while True:
        points, offset = client.scroll(
            collection_name=collection, limit=2000, offset=offset,
            with_payload=["page_content", "metadata.source", "metadata.page"], with_vectors=False,
        )
        for p in points:
            payload = p.payload or {}
            meta = payload.get("metadata") or {}
            kelompok[(meta.get("source"), meta.get("page"), payload.get("page_content"))].append(p.id)
        total += len(points)
        if offset is None:
            break

    per_file = defaultdict(list)
    for (sumber, _, _), ids in kelompok.items():
        per_file[sumber].extend(ids[1:])  # simpan yang pertama, sisanya ganda
    return {s: ids for s, ids in per_file.items() if ids}, total


def main():
    parser = argparse.ArgumentParser(description="Hapus chunk ganda di collection Qdrant")
    parser.add_argument("--collection", required=True, help="misal bps_knowledge_bge_m3_v3")
    parser.add_argument("--hapus", action="store_true", help="benar-benar menghapus (tanpa ini hanya menampilkan)")
    args = parser.parse_args()

    # check_compatibility=False: qdrant-client lokal (1.15) lebih lama dari server (1.19). Operasi
    # scroll/count/delete yang dipakai di sini tetap berjalan normal; ini hanya mematikan peringatannya.
    client = QdrantClient(url=os.getenv("QDRANT_URL"), api_key=os.getenv("QDRANT_API_KEY"), timeout=120,
                          check_compatibility=False)
    if not client.collection_exists(args.collection):
        print(f"❌ Collection '{args.collection}' tidak ada.")
        sys.exit(1)

    print(f"🔎 Memeriksa {args.collection} ...")
    ganda, total = cari_ganda(client, args.collection)
    jumlah = sum(len(ids) for ids in ganda.values())
    print(f"   {total:,} point diperiksa, {jumlah:,} point ganda di {len(ganda)} file")
    for sumber, ids in sorted(ganda.items()):
        print(f"   - {sumber}: {len(ids)} point ganda")

    if not jumlah:
        print("✅ Tidak ada chunk ganda.")
        return
    if not args.hapus:
        print("\nBelum ada yang dihapus. Jalankan ulang dengan --hapus untuk menghapusnya.")
        return

    semua_id = [i for ids in ganda.values() for i in ids]
    for i in range(0, len(semua_id), HAPUS_BATCH):
        client.delete(collection_name=args.collection,
                      points_selector=models.PointIdsList(points=semua_id[i:i + HAPUS_BATCH]), wait=True)
    sisa = client.count(collection_name=args.collection, exact=True).count
    print(f"\n🧹 {len(semua_id):,} point ganda dihapus. Jumlah point sekarang: {sisa:,} (sebelumnya {total:,}).")


if __name__ == "__main__":
    main()
