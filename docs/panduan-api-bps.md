# Panduan Menghubungkan PRANATA ke WebAPI BPS (Tabel Dinamis)

Dashboard PRANATA memakai **tabel dinamis** BPS Kota Pematangsiantar langsung dari
[WebAPI BPS](https://webapi.bps.go.id/documentation). Tidak ada langkah impor manual:

1. **Semua tabel dinamis otomatis menjadi indikator.** Setiap tabel dinamis di BPS dibuatkan indikator,
   dikelompokkan menurut kategori dan subjek BPS (klasifikasi CSA, sama dengan situs BPS). Daftar tabel dicek
   ulang berkala (paling sering sekali sehari) saat Admin/Penanggung Jawab membuka Dashboard atau menu Data
   API BPS, jadi tabel baru di BPS ikut muncul sendiri. Pengguna biasa tidak pernah menunggu proses ini.
2. **Data dibaca dari API saat dibuka.** Saat indikator dibuka di Dashboard, Lihat Data, ekspor Excel/PDF,
   atau saat narasi AI dibuat, datanya (**seluruh tahun**) diambil dari API lalu disimpan. Halaman lain dan
   narasi AI (RAG) memakai data yang sama. Bila API sedang gagal, data terakhir yang tersimpan tetap dipakai.

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
5. Buka Dashboard sebagai Admin. Kategori dan indikator dari tabel dinamis BPS langsung muncul di menu.

## 2. Hal yang perlu diketahui

- **Kecepatan.** Membuka indikator pertama kali (atau setelah datanya lebih tua dari `BPS_SEGAR_MENIT`,
  bawaan 360 menit) butuh beberapa detik karena data diambil dari API. Setelah itu data tersimpan dipakai.
- **Indikator lama bernama sama.** Indikator yang dibuat manual/impor Excel dan namanya persis sama dengan
  tabel dinamis BPS **tidak ditimpa otomatis** dan tidak dibuat duplikatnya. Daftarnya tampil di menu
  **Data API BPS**; klik **Pakai data API** bila ingin datanya diganti data API (nama, subjek, dan narasi tetap).
- **Bila API gagal**, dashboard menampilkan data terakhir yang tersimpan, dan API tidak dicoba lagi untuk
  indikator itu selama 30 menit agar halaman tetap cepat.
- **Mengedit indikator API** di Kelola Data hanya menyimpan nama, subjek, dan satuan. Tabel datanya selalu
  dari API. Indikator API ditandai label **API BPS**.
- **Menghapus indikator API** (atau subjek/kategorinya) membuat tabel itu tidak dibuat ulang otomatis. Untuk
  memunculkannya lagi: menu **Data API BPS → "tabel disembunyikan" → Tampilkan lagi**.
- **Jenis grafik** yang disarankan BPS untuk tabel itu (garis/batang) ditampilkan paling depan di dashboard.
- Tab **Tabel Dinamis** di menu Data API BPS tetap bisa dipakai untuk menyimpan potongan tabel (mis. hanya
  beberapa kecamatan) sebagai indikator tersendiri; datanya juga diperbarui dari API saat dibuka.

## 3. Pembaruan otomatis (opsional)

Tanpa penjadwal pun data sudah diperbarui saat dibuka. Penjadwal Laravel menambahkan pengecekan tabel
dinamis baru setiap malam (pukul 02.00): jalankan `php artisan schedule:work` saat pengembangan, atau
cron/Task Scheduler yang menjalankan `php artisan schedule:run` setiap menit di server.

Manual dari terminal:

```bash
php artisan bps:sinkron                 # buat indikator tabel baru + ambil ulang data semua indikator API
php artisan bps:sinkron --hanya-katalog # hanya buat indikator untuk tabel baru
```

Di aplikasi: tombol **Cek Tabel Baru Sekarang** di menu Data API BPS.

## 4. Bila ada masalah

| Pesan | Penyebab dan solusi |
| --- | --- |
| `Kunci API BPS belum diisi` | `BPS_API_KEY` kosong, atau `php artisan config:clear` belum dijalankan. |
| `Kunci API BPS ditolak` | Key salah atau tidak aktif. Periksa di webapi.bps.go.id. |
| `Permintaan ditolak firewall WebAPI BPS (HTTP 403)` | Terlalu banyak permintaan. Tunggu beberapa saat. |
| Dashboard: "Data terbaru dari WebAPI BPS gagal diambil" | API sedang bermasalah; data terakhir tetap ditampilkan. |

Perintah pemeriksaan:

```bash
php artisan bps:cek --daftar               # daftar tabel dinamis (ID var, judul, kategori, subjek)
php artisan bps:cek 31                     # pratinjau tabel dinamis var 31 (seluruh tahun)
php artisan bps:cek 31 --mentah --tahun=2024   # respons JSON mentah dari API (kunci disamarkan)
```

Pengaturan di `.env` (opsional): `BPS_SEGAR_MENIT` (umur data sebelum diambil ulang, bawaan 360),
`BPS_KATALOG_MENIT` (selang cek tabel baru, bawaan 1440), `BPS_CACHE_MENIT` (cache respons API, bawaan 360).

## 5. Narasi AI (RAG)

Tidak ada yang berubah di layanan Hugging Face (`scripts/Hugging Face/main.py`). Sebelum narasi dibuat,
Laravel memastikan data indikator sudah yang terbaru dari API, lalu mengirim `data_json` (format tabel
`headers`/`rows` yang sama dengan impor Excel) ke `/generate-narrative`. Karena seluruh tahun ikut dikirim,
tabel bisa lebih besar dari sebelumnya.
