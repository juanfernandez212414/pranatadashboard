{-- Halaman sambutan/landing page aplikasi. --}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>PRANATA - Portal Otomatisasi Narasi Statistik</title>
  <meta name="description" content="Portal Otomatisasi Narasi Statistik BPS Kota Pematangsiantar menggunakan AI dan RAG.">
  <meta name="keywords" content="BPS, Statistik, AI, RAG, LLM, Pematangsiantar, PRANATA">

  <link rel="icon" type="image/png" href="{{ asset('assets/img/LogoPRANATA.png') }}">

  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Inter:wght@100;200;300;400;500;600;700;800;900&family=Nunito:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/vendor/aos/aos.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/vendor/glightbox/css/glightbox.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/vendor/swiper/swiper-bundle.min.css') }}" rel="stylesheet">

  <link href="{{ asset('assets/css/main.css') }}" rel="stylesheet">
  <style>
    /* Kurangi padding hanya untuk section selain hero */
    .section:not(.hero) { padding: 40px 0 !important; }
    .section-title { padding-bottom: 20px !important; margin-bottom: 20px !important; }
    .section-title p { margin-bottom: 0 !important; }
  </style>
</head>

<body class="index-page">

  <header id="header" class="header d-flex align-items-center fixed-top">
    <div class="container-fluid container-xl position-relative d-flex align-items-center">

      <a href="{{ url('/') }}" class="logo d-flex align-items-center me-auto">
        <img src="{{ asset('assets/img/LogoPRANATA.png') }}" alt="Logo PRANATA" style="max-height: 50px; width: auto; object-fit: contain;">
        <h1 class="sitename ms-2 mb-0 fs-3">PRANATA</h1>
      </a>

      <nav id="navmenu" class="navmenu">
        <ul>
          <li><a href="#hero" class="active">Beranda</a></li>
          <li><a href="#about">Tentang</a></li>
          <li><a href="#features">Fitur Utama</a></li>
          <li><a href="#services">Modul AI</a></li>
          <li><a href="#faq">FAQ</a></li>
          <li><a href="#contact">Kontak</a></li>
        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
      </nav>

      <a class="btn-getstarted" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-2"></i>Login</a>

    </div>
  </header>

  <main class="main">

    <section id="hero" class="hero section">
      <div class="hero-bg">
        <img src="{{ asset('assets/img/hero-bg-light.webp') }}" alt="Background">
      </div>
      <div class="container text-center">
        <div class="d-flex flex-column justify-content-center align-items-center">
          <h1 data-aos="fade-up">Selamat Datang di <span>PRANATA</span></h1>
          <p data-aos="fade-up" data-aos-delay="100">Portal Otomatisasi Narasi Statistik untuk BPS Kota Pematangsiantar.<br>Mengubah data menjadi cerita bermakna dengan kekuatan <i>Artificial Intelligence</i>.</p>
          <div class="d-flex" data-aos="fade-up" data-aos-delay="200">
            <a href="{{ route('login') }}" class="btn-get-started"><i class="bi bi-shield-lock me-2"></i>Login Sistem</a>
          </div>
          <img src="{{ asset('assets/img/hero-services-img.webp') }}" class="img-fluid hero-img" alt="Ilustrasi Dashboard" data-aos="zoom-out" data-aos-delay="300">
        </div>
      </div>
    </section>

    <section id="featured-services" class="featured-services section light-background">
      <div class="container">
        <div class="row gy-4">

          <div class="col-xl-4 col-lg-6" data-aos="fade-up" data-aos-delay="100">
            <div class="service-item d-flex">
              <div class="icon flex-shrink-0"><i class="bi bi-robot"></i></div>
              <div>
                <h4 class="title"><a href="#" class="stretched-link">Analisis Berbasis AI</a></h4>
                <p class="description">Menggunakan Large Language Models (LLM) untuk menyusun draf narasi statistik yang logis dan berbahasa Indonesia baku.</p>
              </div>
            </div>
          </div>

          <div class="col-xl-4 col-lg-6" data-aos="fade-up" data-aos-delay="200">
            <div class="service-item d-flex">
              <div class="icon flex-shrink-0"><i class="bi bi-database-check"></i></div>
              <div>
                <h4 class="title"><a href="#" class="stretched-link">Validitas RAG</a></h4>
                <p class="description">Teknologi <i>Retrieval-Augmented Generation</i> membuat AI merujuk konsep dan definisi dari publikasi resmi BPS sehingga risiko halusinasi berkurang.</p>
              </div>
            </div>
          </div>

          <div class="col-xl-4 col-lg-6" data-aos="fade-up" data-aos-delay="300">
            <div class="service-item d-flex">
              <div class="icon flex-shrink-0"><i class="bi bi-lightning-charge"></i></div>
              <div>
                <h4 class="title"><a href="#" class="stretched-link">Efisiensi Diseminasi</a></h4>
                <p class="description">Mengotomatisasikan penyusunan narasi data secara akurat untuk mempermudah masyarakat luas dan internal dalam memahami informasi statistik.</p>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <section id="about" class="about section">
      <div class="container">
        <div class="row gy-4">

          <div class="col-lg-6 content" data-aos="fade-up" data-aos-delay="100">
            <p class="who-we-are">Tentang PRANATA</p>
            <h3>Inovasi Diseminasi Data Statistik di Era Digital</h3>
            <p class="fst-italic">
              PRANATA dikembangkan sebagai solusi modern bagi Badan Pusat Statistik (BPS) Kota Pematangsiantar untuk menghadapi tingginya volume data dan kebutuhan pelaporan yang cepat.
            </p>
            <ul>
              <li><i class="bi bi-check-circle"></i> <span>Mengekstrak pengetahuan secara otomatis dari puluhan dokumen publikasi PDF BPS.</span></li>
              <li><i class="bi bi-check-circle"></i> <span>Menerapkan teknik <i>Data Storytelling</i> untuk membuat angka statistik lebih mudah dipahami masyarakat luas.</span></li>
              <li><i class="bi bi-check-circle"></i> <span>Akses informasi satu pintu yang interaktif, praktis digunakan oleh internal maupun masyarakat umum.</span></li>
            </ul>
            <a href="{{ route('login') }}" class="read-more"><span>Mulai Gunakan</span><i class="bi bi-arrow-right"></i></a>
          </div>

          <div class="col-lg-6 about-images" data-aos="fade-up" data-aos-delay="200">
            <div class="d-flex align-items-center gap-2" style="height: 380px;">

              <!-- Gambar kiri besar: Data extraction-amico -->
              <div class="d-flex align-items-center justify-content-center" style="flex: 1.3; height: 100%;">
                <img src="{{ asset('assets/img/Data extraction-amico.png') }}"
                     class="img-fluid"
                     style="max-height: 100%; max-width: 100%; object-fit: contain;"
                     alt="Proyeksi Data Statistik">
              </div>

              <!-- Kolom kanan: 2 gambar ilustrasi lebih kecil -->
              <div class="d-flex flex-column justify-content-between" style="flex: 0.9; height: 100%; gap: 12px;">
                <div class="d-flex align-items-center justify-content-center" style="flex: 1; min-height: 0;">
                  <img src="{{ asset('assets/img/Data analysis-amico.png') }}"
                       class="img-fluid"
                       style="max-height: 100%; max-width: 100%; object-fit: contain;"
                       alt="Analisis Data">
                </div>
                <div class="d-flex align-items-center justify-content-center" style="flex: 1; min-height: 0;">
                  <img src="{{ asset('assets/img/Research paper-pana.png') }}"
                       class="img-fluid"
                       style="max-height: 100%; max-width: 100%; object-fit: contain;"
                       alt="Riset Publikasi BPS">
                </div>
              </div>

            </div>
          </div>

        </div>
      </div>
    </section>

    <section id="features" class="features section">
      <div class="container section-title" data-aos="fade-up">
        <h2>Fitur Unggulan</h2>
        <p>Sistem cerdas yang menggerakkan platform PRANATA BPS</p>
      </div>
      
      <div class="container">
        <div class="row justify-content-between align-items-center">

          <div class="col-lg-5 d-flex align-items-center">
            <ul class="nav nav-tabs" data-aos="fade-up" data-aos-delay="100">
              
              <li class="nav-item">
                <a class="nav-link active show" data-bs-toggle="tab" data-bs-target="#features-tab-1">
                  <i class="bi bi-search"></i>
                  <div>
                    <h4 class="d-none d-lg-block">Interpretasi Data Kontekstual</h4>
                      <p>
                        Menghubungkan tabel data dengan potongan informasi dari publikasi resmi BPS untuk memberikan pemahaman latar belakang yang kaya bagi pengguna.
                      </p>
                  </div>
                </a>
              </li>
              
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" data-bs-target="#features-tab-2">
                  <i class="bi bi-file-earmark-bar-graph"></i>
                  <div>
                    <h4 class="d-none d-lg-block">Otomatisasi Narasi Statistik</h4>
                    <p>
                      Mengubah indikator dan angka statistik mentah menjadi paragraf analisis data (Data Storytelling) yang menarik, akurat, dan mudah dipahami semua kalangan.
                    </p>
                  </div>
                </a>
              </li>
              
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" data-bs-target="#features-tab-3">
                  <i class="bi bi-shield-lock"></i>
                  <div>
                    <h4 class="d-none d-lg-block">Manajemen Data Terkontrol</h4>
                    <p>
                      Pemberian basis pengetahuan dan dokumen publikasi dikelola secara terpusat oleh Admin dan Penanggung Jawab untuk menjaga validitas informasi.
                    </p>
                  </div>
                </a>
              </li>
              
            </ul>
          </div>

          <div class="col-lg-6 d-flex align-items-stretch">
            <div class="tab-content w-100" data-aos="fade-up" data-aos-delay="200" style="display: flex; align-items: center;">
              
              <div class="tab-pane fade active show w-100" id="features-tab-1">
                <img src="{{ asset('assets/img/Data extraction-cuate.png') }}" alt="Eksplorasi Informasi Cerdas" style="width: 100%; height: auto; object-fit: contain; display: block; background: transparent;">
              </div>
              
              <div class="tab-pane fade w-100" id="features-tab-2">
                <img src="{{ asset('assets/img/Data extraction-amico.png') }}" alt="Otomatisasi Narasi Statistik" style="width: 100%; height: auto; object-fit: contain; display: block; background: transparent;">
              </div>
              
              <div class="tab-pane fade w-100" id="features-tab-3">
                <img src="{{ asset('assets/img/Server-cuate.png') }}" alt="Manajemen Data Terkontrol Admin PJ" style="width: 100%; height: auto; object-fit: contain; display: block; background: transparent;">
              </div>
              
            </div>
          </div>

        </div>
      </div>
    </section>

    <section id="services" class="services section light-background">
      <div class="container section-title" data-aos="fade-up">
        <h2>Modul Sistem Integrasi</h2>
        <p>Empat pilar teknologi yang bekerja bersama untuk menghadirkan informasi statistik secara cepat, akurat, dan mudah dipahami</p>
      </div>
      <div class="container">
        <div class="row g-5">

          <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
            <div class="service-item item-cyan position-relative">
              <i class="bi bi-cloud-arrow-up icon"></i>
              <div>
                <h3>1. Basis Pengetahuan Publikasi BPS</h3>
                <p>Dokumen PDF publikasi resmi BPS Kota Pematangsiantar diunggah oleh Admin dan Penanggung Jawab, lalu diproses menjadi basis pengetahuan yang menjadi rujukan AI.</p>
              </div>
            </div>
          </div>

          <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
            <div class="service-item item-orange position-relative">
              <i class="bi bi-diagram-3 icon"></i>
              <div>
                <h3>2. Embedding Teks BGE-M3</h3>
                <p>Teks publikasi dipotong menjadi bagian-bagian kecil, lalu diubah menjadi vektor dengan model <i>embedding</i> multibahasa BGE-M3 agar dapat dicari berdasarkan kemiripan makna, bukan hanya kesamaan kata.</p>
              </div>
            </div>
          </div>

          <div class="col-lg-6" data-aos="fade-up" data-aos-delay="300">
            <div class="service-item item-teal position-relative">
              <i class="bi bi-server icon"></i>
              <div>
                <h3>3. Database Qdrant Cloud</h3>
                <p>Potongan teks beserta vektornya disimpan di basis data vektor Qdrant. Saat narasi dibuat, sistem mencari potongan yang paling relevan dengan indikator yang dipilih untuk dijadikan rujukan AI.</p>
              </div>
            </div>
          </div>

          <div class="col-lg-6" data-aos="fade-up" data-aos-delay="400">
            <div class="service-item item-red position-relative">
              <i class="bi bi-cpu icon"></i>
              <div>
                <h3>4. Integrasi Multi-LLM</h3>
                <p>Ditenagai oleh satu layanan AI yang terhubung ke beberapa model, yaitu <i>Llama</i>, <i>GPT-OSS</i>, dan <i>Gemini</i>, untuk menghasilkan narasi berbahasa Indonesia.</p>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <section id="faq" class="faq section">
      <div class="container section-title" data-aos="fade-up">
        <h2>Pertanyaan Sering Diajukan (FAQ)</h2>
      </div>
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-10" data-aos="fade-up" data-aos-delay="100">
            <div class="faq-container">

              <div class="faq-item faq-active">
                <h3>Apa yang membedakan PRANATA dengan ChatGPT biasa?</h3>
                <div class="faq-content">
                  <p>PRANATA dilengkapi dengan arsitektur RAG (<i>Retrieval-Augmented Generation</i>). Sebelum menulis narasi, AI terlebih dahulu mencari konsep dan definisi yang relevan dari publikasi resmi BPS Kota Pematangsiantar, lalu menyusun narasi berdasarkan data tabel indikator dan rujukan tersebut. Cara ini mengurangi risiko halusinasi, tetapi tidak menghilangkannya sepenuhnya. Karena itu, setiap draf narasi diperiksa dan disunting oleh Admin atau Penanggung Jawab sebelum diterbitkan.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div>

              <div class="faq-item">
                <h3>Siapa yang berhak menggunakan sistem ini?</h3>
                <div class="faq-content">
                  <p>PRANATA dapat digunakan oleh masyarakat umum maupun pegawai BPS Kota Pematangsiantar. Masyarakat cukup mendaftar akun atau masuk dengan akun Google untuk melihat dashboard, visualisasi, dan narasi statistik, serta mengunduh data. Pengelolaan data, pembuatan narasi dengan AI, dan pengelolaan basis pengetahuan hanya dapat dilakukan oleh Admin dan Penanggung Jawab di lingkungan BPS Kota Pematangsiantar.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div>

              <div class="faq-item">
                <h3>Apakah data publikasi diperbarui?</h3>
                <div class="faq-content">
                  <p>Ya. Dokumen PDF publikasi BPS diunggah oleh Admin atau Penanggung Jawab melalui menu Manajemen Pengetahuan, kemudian diproses ke dalam basis pengetahuan AI melalui fitur penyimpanan pengetahuan. Data tabel indikator juga dimasukkan oleh Admin atau Penanggung Jawab melalui formulir maupun impor berkas Excel.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div>

            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="contact" class="contact section">
      <div class="container section-title" data-aos="fade-up">
        <h2>Hubungi Kami</h2>
        <p>Badan Pusat Statistik Kota Pematangsiantar</p>
      </div>
      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row gy-4 justify-content-center">

          {{-- h-100 menyamakan tinggi kedua kartu; px-4 + text-center agar alamat tidak menempel ke tepi kartu --}}
          <div class="col-lg-6 col-md-6">
            <div class="info-item h-100 px-4 text-center rounded-3 d-flex flex-column justify-content-center align-items-center" data-aos="fade-up" data-aos-delay="200">
              <i class="bi bi-geo-alt"></i>
              <h3>Alamat Kantor</h3>
              <p>Jl. Sisingamangaraja No. 258A, Pematangsiantar, Sumatera Utara</p>
            </div>
          </div>

          <div class="col-lg-6 col-md-6">
            <div class="info-item h-100 px-4 text-center rounded-3 d-flex flex-column justify-content-center align-items-center" data-aos="fade-up" data-aos-delay="400">
              <i class="bi bi-envelope"></i>
              <h3>Email</h3>
              <p>bps1273@bps.go.id</p>
            </div>
          </div>
          
        </div>

      </div>
    </section>

  </main>

  <footer id="footer" class="footer position-relative light-background">
    <div class="container footer-top">
      <div class="row gy-4">
        {{-- Di HP isi footer rata tengah (selaras dengan hak cipta), mulai md rata kiri --}}
        <div class="col-lg-6 col-md-6 footer-about text-center text-md-start">
          <a href="{{ url('/') }}" class="logo d-flex align-items-center justify-content-center justify-content-md-start">
            <span class="sitename">PRANATA BPS</span>
          </a>
          <div class="footer-contact pt-3">
            <p><strong>Badan Pusat Statistik Kota Pematangsiantar</strong></p>
            <p>Jl. Sisingamangaraja No. 258A</p>
            <p>Pematangsiantar, Sumatera Utara 21137</p>
          </div>
          <div class="social-links d-flex justify-content-center justify-content-md-start mt-4">
            <a href="https://siantarkota.bps.go.id/" target="_blank" title="Website Resmi BPS"><i class="bi bi-globe"></i></a>
            <a href="https://www.youtube.com/@bpskotapematangsiantar6980" target="_blank" rel="noopener noreferrer" title="YouTube BPS"><i class="bi bi-youtube"></i></a>
            <a href="https://www.instagram.com/bpskotapematangsiantar/" target="_blank" title="Instagram BPS"><i class="bi bi-instagram"></i></a>
          </div>
        </div>

        <div class="col-lg-3 col-md-3 footer-links text-center text-md-start">
          <h4>Tautan Cepat</h4>
          <ul class="d-flex flex-column align-items-center align-items-md-start">
            <li><a href="#hero">Beranda</a></li>
            <li><a href="#about">Tentang Aplikasi</a></li>
            <li><a href="#features">Fitur</a></li>
            <li><a href="{{ route('login') }}">Halaman Login</a></li>
          </ul>
        </div>

      </div>
    </div>

    <div class="container copyright text-center mt-4">
      <p>© <span>Copyright 2025</span> <strong class="px-1 sitename">BPS Kota Pematangsiantar.</strong> 
    </div>
  </footer>

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <div id="preloader"></div>

  <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/php-email-form/validate.js') }}"></script>
  <script src="{{ asset('assets/vendor/aos/aos.js') }}"></script>
  <script src="{{ asset('assets/vendor/glightbox/js/glightbox.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/swiper/swiper-bundle.min.js') }}"></script>

  <script src="{{ asset('assets/js/main.js') }}"></script>

</body>
</html>
