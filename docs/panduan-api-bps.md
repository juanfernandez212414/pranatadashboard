# Panduan Menghubungkan PRANATA ke WebAPI BPS (Tabel Dinamis)

Dashboard PRANATA memakai **tabel dinamis** BPS Kota Pematangsiantar dari
[WebAPI BPS](https://webapi.bps.go.id/documentation) dengan skema **impor semua**:

1. **Impor semua tabel dinamis sekaligus.** Tombol **Impor Semua Tabel Dinamis** (menu Data API BPS) atau
   perintah `php artisan bps:sinkron` mengambil daftar semua tabel dinamis, membuatkan indikator untuk
   tiap tabel (kategori dan subjek mengikuti klasifikasi CSA di situs BPS), lalu mengambil **data seluruh
   tahun** tiap tabel dan menyimpannya ke database.
2. **Dashboard membaca database.** Dashboard, Lihat Data, ekspor Excel/PDF, dan narasi AI langsung memakai
   data tersimpan, jadi tidak menunggu API. API hanya dipanggil sebagai cadangan bila sebuah indikator
   belum punya data.
3. **Pembaruan** lewat tombol yang sama atau penjadwal Laravel setiap malam pukul 02.00 WIB.

```
WebAPI BPS ──(Impor Semua / bps:sinkron / jadwal malam)──► database (categories, subjects, indicators)
                                                              │
                                    Dashboard · Lihat Data · Ekspor · Narasi AI (RAG) ◄──┘
```

## 1. Memasang kunci API

1. Masuk ke [webapi.bps.go.id](https://webapi.bps.go.id), lalu salin **key** aplikasi Anda.
2. Buka file `.env` di folder proyek PRANATA, lalu isi:

   ```env
   BPS_API_KEY=isi_dengan_key_anda
   BPS_DOMAIN=1273
   ```

   `1273` adalah domain BPS Kota Pematangsiantar.
3. Jalankan migrasi dan bersihkan cache konfigurasi:

   ```bash
   php artisan migrate
   php artisan config:clear
   ```

4. Uji koneksinya:

   ```bash
   php artisan bps:cek
   ```

   Bila berhasil, muncul `Koneksi berhasil: kunci API diterima WebAPI BPS.` beserta jumlah tabel dinamis.

## 2. Mengimpor semua tabel dinamis

**Dari aplikasi:** login sebagai Admin/Penanggung Jawab, buka menu **Data API BPS**, klik
**Impor Semua Tabel Dinamis**. Tabel diproses satu per satu dengan progress bar; biarkan halaman tetap
terbuka sampai muncul "Selesai". Tabel yang belum berisi data di BPS dilaporkan dan dilewati.

**Dari terminal** (cocok untuk impor pertama yang banyak):

```bash
php artisan bps:sinkron                 # indikator untuk tabel baru + data seluruh tahun semua indikator
php artisan bps:sinkron --hanya-katalog # hanya membuat indikator untuk tabel baru (tanpa data)
```

## 3. Hal yang perlu diketahui

- **Indikator lama bernama sama.** Indikator manual/impor Excel yang namanya persis sama dengan tabel
  dinamis BPS **tidak ditimpa otomatis** dan tidak dibuat duplikatnya. Daftarnya tampil di menu Data API
  BPS; klik **Pakai data API** bila ingin datanya diganti data API (nama, subjek, dan narasi tetap).
- **Mengedit indikator API** di Kelola Data hanya menyimpan kategori, subjek, nama, dan satuan; tabel
  datanya selalu dari API. Indikator API ditandai label **API BPS**.
- **Menghapus indikator API** (atau subjek/kategorinya) membuat tabel itu tidak dibuat ulang otomatis.
  Untuk memunculkannya lagi: menu **Data API BPS → "tabel disembunyikan" → Tampilkan lagi**.
- **Tab Tabel Dinamis** tetap bisa dipakai untuk menyimpan **potongan** tabel (mis. hanya beberapa
  kecamatan) sebagai indikator tersendiri. Tabel lengkapnya sudah otomatis ada, jadi tidak ditimpa/digandakan.
- **Bila API gagal**, data terakhir yang tersimpan tetap ditampilkan.
- Di dashboard tertulis jenis grafik yang disarankan BPS untuk tabel itu (garis/batang/lingkaran).

## 4. Pembaruan otomatis (opsional)

Jalankan penjadwal Laravel agar impor semua berjalan setiap malam pukul 02.00 WIB: `php artisan schedule:work`
saat pengembangan, atau cron/Task Scheduler yang menjalankan `php artisan schedule:run` setiap menit di server.

Bila ingin data juga diambil ulang saat indikator dibuka (mis. setiap 6 jam), isi di `.env`:
`BPS_SEGAR_MENIT=360` (bawaan `0` = hanya lewat impor/penjadwal).

## 5. Bila ada masalah

| Pesan | Penyebab dan solusi |
| --- | --- |
| `Kunci API BPS belum diisi` | `BPS_API_KEY` kosong, atau `php artisan config:clear` belum dijalankan. |
| `Kunci API BPS ditolak` | Key salah atau tidak aktif. Periksa di webapi.bps.go.id. |
| `Permintaan ditolak firewall WebAPI BPS (HTTP 403)` | Terlalu banyak permintaan; impor berhenti otomatis. Tunggu lalu ulangi. |
| `Tabel dinamis "..." belum berisi data di WebAPI BPS` | Tabelnya memang kosong di BPS; dilewati. |

Perintah pemeriksaan:

```bash
php artisan bps:cek --daftar               # daftar tabel dinamis (ID var, judul, kategori, subjek)
php artisan bps:cek 31                     # pratinjau tabel dinamis var 31 (seluruh tahun)
php artisan bps:cek 31 --mentah --tahun=2024   # respons JSON mentah dari API (kunci disamarkan)
```

## 6. Publikasi BPS untuk basis pengetahuan AI (tanpa unggah manual)

PDF publikasi BPS **tidak perlu disimpan di server Laravel**. Laravel hanya mengirim **link PDF** dari WebAPI
BPS ke layanan AI (endpoint `/ingest-url` di `scripts/Hugging Face/main.py`); server Hugging Face sendiri yang
mengunduh PDF itu lalu mengekstraknya (ingest) ke Qdrant. Tidak perlu mengunduh PDF, mengunggahnya, lalu klik Ingest.

```
WebAPI BPS ──(link PDF)──► Laravel ──POST /ingest-url {url, filename}──► Hugging Face ──unduh PDF dari BPS──► Qdrant
```

**Cara cadangan otomatis.** Bila server AI belum punya endpoint `/ingest-url` (Space belum di-deploy ulang)
atau gagal mengunduh dari BPS, Laravel mengunduh PDF ke folder basis pengetahuan lalu mengunggahnya ke
`/upload-ingest` seperti dulu. Bila server AI tidak bisa dihubungi, tidak ada yang diunduh; ulangi nanti.

**Wajib sekali:** deploy ulang Space Hugging Face dengan `scripts/Hugging Face/main.py` terbaru agar mode link
aktif. Link hanya diterima bila `https://` dan berasal dari `*.bps.go.id`.

- **Semua publikasi baru sekaligus:** menu **Data API BPS → tab Publikasi → Ambil & Latih Publikasi Baru**.
  Pilih tahun rilis paling awal dan (opsional) kata kunci judul, misalnya `dalam angka`. Publikasi diproses
  satu per satu dengan progress bar; yang sudah dilatih dilewati.
- **Satu publikasi:** tombol **Latih AI** di daftar publikasi (atau **Latih ke AI** bila PDF-nya sudah
  tersimpan di folder dari unggahan lama tetapi belum dilatih).
- **Otomatis setiap malam (03.00 WIB)** bila penjadwal Laravel berjalan, memakai pengaturan
  `BPS_PUBLIKASI_SEJAK` (tahun rilis paling awal; kosong = tahun lalu) dan `BPS_PUBLIKASI_KATA` di `.env`.
- **Dari terminal** (cocok untuk PDF besar atau banyak sekaligus, tanpa batas waktu halaman):

  ```bash
  php artisan bps:publikasi --daftar                       # lihat daftar & status
  php artisan bps:publikasi --sejak=2023 --kata="dalam angka"
  ```

Syarat: `HUGGINGFACE_API_URL` di `.env` terisi dan server Hugging Face aktif. Ekstraksi PDF berjalan di
server AI dan butuh beberapa menit per dokumen; hasilnya terlihat di **Manajemen Pengetahuan**. Di halaman
web, satu publikasi dibatasi sekitar 40 detik (server AI mengunduh dari BPS); bila PDF terlalu besar atau
server AI sedang "bangun", ulangi nanti atau pakai perintah terminal.

Status "sudah dilatih" dicatat di `storage/app/processed_log_bge_m3.txt` (log yang sama dengan Ingest manual),
jadi publikasi yang dilatih lewat link tidak dikirim dua kali walaupun PDF-nya tidak ada di folder.

- **Satu publikasi gagal tidak menghentikan proses massal.** Bila hanya publikasi itu yang bermasalah
  (PDF sangat besar sehingga batas waktu habis, atau ditolak karena melebihi 100 MB), publikasi berikutnya
  tetap diproses. Proses berhenti hanya bila layanan AI tidak bisa dipakai sama sekali.
- **Dokumen yang dihapus di Manajemen Pengetahuan** (hapus satu maupun Hapus Semua) **tidak dilatihkan lagi
  secara otomatis**. Di tab Publikasi tandanya "Pernah dihapus dari basis pengetahuan"; klik **Latih AI** bila
  ingin memakainya lagi, atau **Izinkan dilatih otomatis lagi** untuk semuanya. Fitur ini butuh
  `php artisan migrate` (tabel `bps_publikasi_diabaikan`).
- Unduhan PDF, baik oleh Laravel maupun server AI, hanya mengikuti pengalihan (redirect) ke alamat https `*.bps.go.id`.

## 7. Narasi AI (RAG)

Endpoint narasi di layanan Hugging Face (`scripts/Hugging Face/main.py`) tidak berubah. Laravel mengirim data
indikator dari database (`data_json`, format tabel `headers`/`rows` yang sama dengan impor Excel) ke
`/generate-narrative`; `main.py` mengubahnya ke tabel markdown, menambah konteks RAG dari Qdrant, lalu
memanggil LLM. Karena seluruh tahun ikut dikirim, tabel bisa lebih besar dari sebelumnya.
