# Panduan Menghubungkan PRANATA ke WebAPI BPS

PRANATA mengambil tabel statistik dan publikasi BPS Kota Pematangsiantar langsung dari
[WebAPI BPS](https://webapi.bps.go.id/documentation). Tabel yang diambil disimpan sebagai **indikator**,
sehingga Dashboard, Lihat Data, ekspor Excel/PDF, dan narasi AI langsung memakai data dari API.

## 1. Memasang kunci API

1. Masuk ke [webapi.bps.go.id](https://webapi.bps.go.id), buka menu aplikasi/kunci Anda, lalu salin **key**.
2. Buka file `.env` di folder proyek PRANATA, lalu isi:

   ```env
   BPS_API_KEY=isi_dengan_key_anda
   BPS_DOMAIN=1273
   ```

   `1273` adalah domain BPS Kota Pematangsiantar. `BPS_SIMDASI_WILAYAH` boleh dikosongkan
   (otomatis `1273000`, kode wilayah tabel publikasi SIMDASI).
3. Jalankan migrasi (sekali saja, menambah kolom tautan API pada tabel indikator) dan bersihkan cache konfigurasi:

   ```bash
   php artisan migrate
   php artisan config:clear
   ```

4. Uji koneksinya:

   ```bash
   php artisan bps:cek
   ```

   Bila berhasil, muncul `Koneksi berhasil: kunci API diterima WebAPI BPS.` beserta jumlah tabel tiap sumber.

## 2. Sumber data yang diambil

| Sumber | Isi | Tahun yang diambil |
| --- | --- | --- |
| **Tabel Publikasi (SIMDASI)** | Tabel-tabel publikasi *Kota Pematangsiantar Dalam Angka* | Semua tahun di `ketersediaan_tahun`, digabung dalam satu indikator |
| **Tabel Dinamis** | Tabel dinamis di situs BPS (model `var`/`data`) | Semua tahun, karakteristik, dan judul baris |
| **Tabel Statis** | Tabel statis (HTML) di situs BPS | Satu tabel menjadi satu indikator |
| **Publikasi (PDF)** | Berkas publikasi | Disimpan ke basis pengetahuan AI (tab Publikasi) |

## 3. Mengimpor tabel menjadi indikator

**Dari aplikasi:** menu **Data API BPS → Sinkronisasi Semua Tabel**.

1. Pilih sumber (Tabel Publikasi, Tabel Dinamis, atau Tabel Statis).
2. Centang tabel yang diinginkan, atau klik **Pilih Semua yang Tampil**.
3. Pilih **Subjek tujuan**. Pilihan bawaan *Otomatis* memakai subjek PRANATA yang namanya sama dengan
   subjek BPS. Bila belum ada, kategori dan subjeknya dibuat mengikuti BPS (untuk SIMDASI: bab dan subjek publikasi).
4. Klik **Impor yang Dipilih**. Tabel diproses satu per satu dan kemajuannya ditampilkan.

Aturan penyimpanan:

- Tabel yang sudah menjadi indikator **diperbarui**, tidak dibuat ganda.
- Tabel yang namanya sama dengan indikator lama (misalnya hasil impor Excel) **menautkan** indikator
  tersebut ke API dan mengganti datanya. Nama dan subjek indikator tetap.

**Dari terminal:**

```bash
php artisan bps:sinkron --impor=simdasi           # semua tabel publikasi
php artisan bps:sinkron --impor=simdasi,dinamis   # beberapa sumber
php artisan bps:sinkron --impor=semua             # termasuk tabel statis
```

## 4. Memperbarui data

- Di aplikasi: tombol **Perbarui Semua dari API** (atau **Perbarui** per indikator) di tab Sinkronisasi.
- Di terminal: `php artisan bps:sinkron`
- Otomatis setiap hari pukul 02.00 bila penjadwal Laravel berjalan: `php artisan schedule:work` saat
  pengembangan, atau cron/Task Scheduler yang menjalankan `php artisan schedule:run` setiap menit di server.

## 5. Bila ada masalah

| Pesan | Penyebab dan solusi |
| --- | --- |
| `Kunci API BPS belum diisi` | `BPS_API_KEY` kosong, atau `php artisan config:clear` belum dijalankan. |
| `Kunci API BPS ditolak` | Key salah atau tidak aktif. Periksa di webapi.bps.go.id. |
| `Permintaan ditolak firewall WebAPI BPS (HTTP 403)` | Terlalu banyak permintaan. Tunggu beberapa saat lalu coba lagi. |
| `Tabel ... tidak berisi data yang bisa dibaca` | Lihat respons asli API dengan `php artisan bps:cek <sumber> <id> --mentah`. |

Perintah pemeriksaan lain:

```bash
php artisan bps:cek simdasi                      # daftar tabel SIMDASI (ID, judul, tahun)
php artisan bps:cek simdasi <id_tabel>           # pratinjau tabel seperti yang akan disimpan
php artisan bps:cek simdasi <id_tabel> --mentah --tahun=2024   # respons JSON mentah (kunci disamarkan)
php artisan bps:cek dinamis 31                   # pratinjau tabel dinamis
php artisan bps:cek statis <table_id>            # pratinjau tabel statis
```

Respons API disimpan di cache selama `BPS_CACHE_MENIT` menit (bawaan 360), agar halaman cepat dan kuota API hemat.
Pembaruan (tombol **Perbarui** dan `php artisan bps:sinkron`) selalu mengambil ulang data dari API.
