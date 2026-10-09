// ==========================================
// resources/js/dashboard.js
// ==========================================
// Grafik dashboard (peta, tren, batang, komposisi, kotak nilai, piramida, tabel perbandingan) yang
// dipakai bersama halaman Admin (admin.js), Penanggung Jawab (pj.js), dan Pengguna/publik (pengguna.js).
// Dijalankan lewat window.dashboardApp.init() dari masing-masing berkas tersebut.

// Selesai ketika huruf Inter untuk grafik sudah termuat. Chart.js mengukur lebar label saat grafik dibuat;
// bila diukur dengan huruf cadangan, label sumbu Y terpotong. Paling lama menunggu 1,5 detik.
const hurufGrafikSiap = () => {
    if (!document.fonts) return Promise.resolve();
    const muat = Promise.all(['12px Inter', '600 12px Inter', 'bold 11px Inter'].map(f => document.fonts.load(f)));
    return Promise.race([muat, new Promise(selesai => setTimeout(selesai, 1500))]).catch(() => { });
};

window.dashboardApp = {
    visualizations: [],
    instances: {},
    // Palet kategori 8 warna (biru, oranye, hijau tosca, kuning, merah muda, hijau, ungu, merah) yang lolos uji
    // pembeda buta warna untuk garis/batang bersebelahan. Urutannya tetap dan tidak diulang: satu anggota
    // (mis. satu kecamatan) memakai warna yang sama di semua grafik (lihat warnaAnggota).
    chartColors: ['#2A78D6', '#EB6834', '#1BAF7A', '#EDA100', '#E87BA4', '#008300', '#4A3AA7', '#E34948'],

    // Skala berurutan satu warna (biru muda -> biru tua) untuk tabel perbandingan: makin gelap makin besar.
    rampBiru: ['#CDE2FB', '#B7D3F6', '#9EC5F4', '#86B6EF', '#6DA7EC', '#5598E7', '#3987E5', '#2A78D6', '#256ABF', '#1C5CAB', '#184F95', '#104281', '#0D366B'],

    init: function () {
        if (window.DashboardConfig && window.DashboardConfig.visualizations) {
            this.visualizations = window.DashboardConfig.visualizations;
        }
        // Data lengkap tiap indikator disimpan sebelum disaring: memilih satu kecamatan/tahun pada filter
        // menyorot pilihan itu, sedangkan kecamatan/tahun lain tetap tampil sebagai pembanding.
        this.visualizations.forEach(vis => {
            if (!vis.data_lengkap) vis.data_lengkap = vis.parsed_data || [];
        });
        hurufGrafikSiap().then(() => {
            this.visualizations.forEach(vis => {
                this.renderAllVisualizations(vis);
            });
        });
    },

    renderAllVisualizations: function (vis) {
        const placeholder = document.getElementById(`vis-placeholder-${vis.id}`);
        if (!placeholder) return;
        placeholder.innerHTML = '';

        let available_types = vis.available_types;
        // Komposisi (donat) hanya bermakna untuk jumlah yang bisa dijumlahkan (penduduk, PDRB, distribusi);
        // rasio, indeks, laju, tingkat, atau kepadatan antarkecamatan tidak membentuk "bagian dari 100%".
        if (!this.bisaDijumlah(vis)) {
            available_types = available_types.filter(t => t !== 'pie');
        }
        if (available_types.includes('pyramid')) {
            available_types = available_types.filter(t => ['pyramid', 'card', 'table', 'choropleth', 'line'].includes(t));
        }

        const hasMap = available_types.includes('choropleth');
        let otherTypes = available_types.filter(t => t !== 'choropleth' && t !== 'table');

        const isNeracaEkonomi = vis.subject && String(vis.subject).trim().toLowerCase() === 'neraca ekonomi';

        if (isNeracaEkonomi && otherTypes.includes('bar')) {
            otherTypes = otherTypes.filter(t => t !== 'bar');
            otherTypes.unshift('bar');
        }

        if (otherTypes.includes('card')) {
            otherTypes = otherTypes.filter(t => t !== 'card');
            otherTypes.push('card');
        }

        placeholder.className = hasMap ? 'vis-layout-container has-map' : 'vis-layout-container';

        // Bila ada peta, grafik tren (semua kecamatan sepanjang tahun) dibuat selebar kartu di atas peta agar
        // garis tiap kecamatan mudah dibedakan. Peta & grafik lain berada di baris berikutnya.
        const trenDiAtas = hasMap && otherTypes.includes('line');
        if (trenDiAtas) {
            otherTypes = otherTypes.filter(t => t !== 'line');
            const trenWrapper = this.appendChartContainer(placeholder, vis, 'line');
            if (trenWrapper) trenWrapper.style.gridColumn = '1 / -1';
        }

        if (hasMap) {
            const mapId = `vis-${vis.id}-choropleth`;
            const mapWrapper = document.createElement('div');
            mapWrapper.className = 'vis-wrapper vis-wrapper-map';
            mapWrapper.innerHTML = `
                <h4 class="vis-wrapper-title"><i class="fas fa-map-marked-alt fa-fw"></i> Peta Sebaran${this.htmlLabelJudul(mapId)}</h4>
                <div class="auto-map-container"><div id="${mapId}" style="height:100%;"></div></div>`;
            placeholder.appendChild(mapWrapper);

            const chartsGrid = document.createElement('div');
            chartsGrid.className = 'charts-grid';
            otherTypes.forEach(type => this.appendChartContainer(chartsGrid, vis, type));
            placeholder.appendChild(chartsGrid);

            // CSS mengunci peta & kolom grafik di baris 1; bila tren di atas, keduanya turun ke baris berikutnya.
            if (trenDiAtas) {
                mapWrapper.style.gridRow = 'auto';
                chartsGrid.style.gridRow = 'auto';
            }
        } else {
            otherTypes.forEach(type => this.appendChartContainer(placeholder, vis, type));
        }

        // Tabel warna semua kecamatan (atau kategori) x semua tahun, selebar kartu di bagian bawah.
        const adaPerbandingan = this.adaTabelPerbandingan(vis);
        if (adaPerbandingan) this.appendChartContainer(placeholder, vis, 'heatmap');

        available_types.filter(t => t !== 'table').forEach(type => {
            this.createVisualization(vis, type);
        });
        if (adaPerbandingan) this.createHeatmap(vis, `vis-${vis.id}-heatmap`);
    },

    // Wadah teks kecil di kanan judul grafik (mis. "Tahun 2025 (terbaru)") dan catatan di bawah judul;
    // isinya diatur oleh fungsi pembuat grafik lewat aturLabel.
    htmlLabelJudul: function (elementId) {
        return `<span id="${elementId}-label" style="display:none; margin-left:auto; font-size:11px; font-weight:600; color:#475569; background:#F1F5F9; border:1px solid #E2E8F0; border-radius:999px; padding:2px 10px; white-space:nowrap;"></span>`;
    },

    htmlCatatanJudul: function (elementId) {
        return `<p id="${elementId}-catatan" style="display:none; margin:8px 0 0; font-size:12px; line-height:1.5; color:#64748B;"></p>`;
    },

    aturLabel: function (elementId, label, catatan) {
        const el = document.getElementById(`${elementId}-label`);
        if (el) {
            el.textContent = label || '';
            el.style.display = label ? '' : 'none';
        }
        const ct = document.getElementById(`${elementId}-catatan`);
        if (ct) {
            ct.textContent = catatan || '';
            ct.style.display = catatan ? '' : 'none';
        }
    },

    appendChartContainer: function (container, vis, type) {
        const uniqueId = `vis-${vis.id}-${type}`;
        let title = '';
        if (type === 'line') title = '<i class="fas fa-chart-line fa-fw"></i> Grafik Tren';
        else if (type === 'bar') title = '<i class="fas fa-chart-bar fa-fw"></i> Grafik Batang';
        else if (type === 'pie') title = '<i class="fas fa-chart-pie fa-fw"></i> Komposisi';
        else if (type === 'pyramid') title = '<i class="fas fa-chart-area fa-fw"></i> Piramida Penduduk';
        else if (type === 'card') title = '<i class="fas fa-info-circle fa-fw"></i> Detail Nilai Terpilih';
        else if (type === 'heatmap') title = '<i class="fas fa-th fa-fw"></i> <span data-judul-perbandingan></span>';

        if (title) {
            title += this.htmlLabelJudul(uniqueId);
            const catatan = this.htmlCatatanJudul(uniqueId);
            const wrapper = document.createElement('div');
            wrapper.className = `vis-wrapper vis-wrapper-${type}`;
            wrapper.style.height = '100%'; // Pastikan kotak membentang penuh

            // Perlakuan khusus wadah card (menggunakan div biasa, bukan canvas grafik)
            if (type === 'card') {
                wrapper.innerHTML = `<h4 class="vis-wrapper-title">${title}</h4>${catatan}
                                     <div id="${uniqueId}" class="flex flex-col items-center justify-center mt-4" style="flex: 1; height: 100%;"></div>`;
            } else if (type === 'heatmap') {
                wrapper.style.gridColumn = '1 / -1';
                wrapper.innerHTML = `<h4 class="vis-wrapper-title">${title}</h4>${catatan}<div id="${uniqueId}"></div>`;
                // Nama kolom berasal dari data (judul tabel BPS), jadi diisi sebagai teks, bukan HTML.
                const dimensi = this.dimensiPerbandingan(vis);
                wrapper.querySelector('[data-judul-perbandingan]').textContent = `Perbandingan ${dimensi ? dimensi.kolom : ''} × Tahun`;
            } else if (type === 'pyramid' || type === 'pie') {
                wrapper.style.gridRow = 'span 2';
                wrapper.innerHTML = `<h4 class="vis-wrapper-title">${title}</h4>${catatan}
                                     <div class="auto-chart-container" style="flex: 1; min-height: 450px; height: auto;"><canvas id="${uniqueId}"></canvas></div>`;
            } else if (type === 'bar') {
                const isNeracaEkonomi = vis.subject && String(vis.subject).trim().toLowerCase() === 'neraca ekonomi';
                if (isNeracaEkonomi) wrapper.style.gridColumn = '1 / -1';
                const minHeight = isNeracaEkonomi ? 350 : 285;
                wrapper.innerHTML = `<h4 class="vis-wrapper-title">${title}</h4>${catatan}
                                     <div class="auto-chart-container" style="flex: 1; min-height: ${minHeight}px; height: auto;"><canvas id="${uniqueId}"></canvas></div>`;
            } else {
                let minHeight = 285;
                if (type === 'line') {
                    const availTypes = vis.available_chart_types || vis.available_types || [];
                    const isNeracaEkonomi = vis.subject && String(vis.subject).trim().toLowerCase() === 'neraca ekonomi';
                    if (!availTypes.includes('pyramid') && !isNeracaEkonomi) {
                        wrapper.style.gridColumn = '1 / -1';
                        minHeight = 340; // tren selebar kartu: ruang untuk legenda banyak kecamatan
                    }
                }
                wrapper.innerHTML = `<h4 class="vis-wrapper-title">${title}</h4>${catatan}
                                     <div class="auto-chart-container" style="flex: 1; min-height: ${minHeight}px; height: auto;"><canvas id="${uniqueId}"></canvas></div>`;
            }
            container.appendChild(wrapper);
            return wrapper;
        }
        return null;
    },

    // ====================================================================
    // PEMBANTU PERBANDINGAN ANTAR KECAMATAN/KATEGORI DAN ANTAR TAHUN
    // ====================================================================

    // Ukuran yang bisa dijumlahkan antaranggota (bukan rasio/indeks/laju/tingkat/kepadatan/rata-rata).
    bisaDijumlah: function (vis) {
        const teks = `${vis.name || ''} ${vis.unit || ''}`.toLowerCase();
        return !/(rasio|indeks|laju|tingkat|kepadatan|rata-rata|rerata|pertumbuhan|inflasi|angka partisipasi|harapan hidup|\/\s*km|per\s*km)/.test(teks);
    },

    // Label baris ringkasan (total kota, jumlah, PDRB, tahunan): bukan anggota yang dibandingkan.
    adalahAgregat: function (nilai) {
        const s = String(nilai ?? '').toLowerCase().replace(/\s+/g, ' ').trim();
        return s === 'total' || s === 'jumlah' || s === 'tahunan' || s === 'pdrb' || s === 'produk domestik regional bruto'
            || s === 'pematangsiantar' || s === 'kota pematangsiantar' || s === 'pematang siantar' || s === 'kota pematang siantar'
            || s.startsWith('jumlah ') || s.startsWith('total ');
    },

    // Seluruh data indikator (sebelum disaring filter). Filter diterapkan di tiap grafik, sehingga pilihan
    // bisa disorot tanpa membuang pembanding.
    dataLengkap: function (vis) {
        return vis.data_lengkap || vis.parsed_data || [];
    },

    kolomTahun: function (vis) {
        const data = this.dataLengkap(vis);
        if (data.length && Object.prototype.hasOwnProperty.call(data[0], 'Tahun')) return 'Tahun';
        return vis.temporal_column || (vis.visualization_config || {}).x_axis_temporal || null;
    },

    kolomNilai: function (vis) {
        const config = vis.visualization_config || {};
        return config.val_column || (config.y_axis_numeric || [])[0] || 'Nilai';
    },

    daftarTahun: function (vis) {
        const kolom = this.kolomTahun(vis);
        if (!kolom) return [];
        return [...new Set(this.dataLengkap(vis).map(r => r[kolom]).filter(t => t !== null && t !== undefined && t !== '').map(String))].sort();
    },

    // Tahun terbaru yang berisi angka; diutamakan tahun yang punya angka rinci (bukan hanya baris total).
    tahunTerbaru: function (vis) {
        const kolomTahun = this.kolomTahun(vis);
        if (!kolomTahun) return null;
        const kolomNilai = this.kolomNilai(vis);
        let terbaru = null, terbaruRinci = null;
        this.dataLengkap(vis).forEach(r => {
            if (isNaN(parseFloat(r[kolomNilai]))) return;
            const t = String(r[kolomTahun]);
            if (terbaru === null || t > terbaru) terbaru = t;
            const rinci = Object.keys(r).some(k => k !== kolomTahun && k !== kolomNilai && k.toLowerCase() !== 'bulan' && !this.adalahAgregat(r[k]));
            if (rinci && (terbaruRinci === null || t > terbaruRinci)) terbaruRinci = t;
        });
        return terbaruRinci || terbaru;
    },

    // Tahun untuk grafik satu tahun (batang, komposisi, peta, kotak nilai, piramida): pilihan filter Tahun,
    // atau tahun terbaru selama filter Tahun masih "Semua".
    tahunTampil: function (vis) {
        return vis.selected_year || this.tahunTerbaru(vis);
    },

    labelTahun: function (vis) {
        const tahun = this.tahunTampil(vis);
        if (!tahun) return '';
        return vis.selected_year ? `Tahun ${tahun}` : `Tahun ${tahun} (terbaru)`;
    },

    // Anggota non-ringkasan sebuah kolom, urut abjad (sama dengan urutan legenda grafik).
    anggotaKolom: function (vis, kolom) {
        return [...new Set(this.dataLengkap(vis).map(r => r[kolom]).filter(v => v !== null && v !== undefined && v !== ''))]
            .filter(v => !this.adalahAgregat(v)).sort();
    },

    // Warna tetap per anggota (mis. per kecamatan) di semua grafik. null bila anggotanya lebih dari 8,
    // karena warna kategori tidak boleh diulang.
    warnaAnggota: function (vis, kolom, nilai) {
        const anggota = this.anggotaKolom(vis, kolom);
        if (anggota.length > this.chartColors.length) return null;
        const i = anggota.indexOf(nilai);
        return i === -1 ? null : this.chartColors[i];
    },

    // Dimensi yang dibandingkan antar tahun: kolom wilayah (Kecamatan) bila ada, selain itu kolom kategori utama.
    // Hasil: { kolom, anggota: [urutan seperti tabel BPS], agregat: label baris total atau null }, atau null.
    dimensiPerbandingan: function (vis) {
        const config = vis.visualization_config || {};
        const kolomTahun = this.kolomTahun(vis);
        const kandidat = Object.keys(vis.filters || {}).filter(k => k !== kolomTahun && k.toLowerCase() !== 'bulan');
        const urutan = [config.geo_column, config.x_axis_categorical, config.group_by, ...kandidat]
            .filter((k, i, semua) => k && kandidat.includes(k) && semua.indexOf(k) === i);

        for (const kolom of urutan) {
            const nilai = [...new Set(this.dataLengkap(vis).map(r => r[kolom]).filter(v => v !== null && v !== undefined && v !== ''))];
            const anggota = nilai.filter(v => !this.adalahAgregat(v));
            if (anggota.length >= 2) {
                const agregat = nilai.find(v => this.adalahAgregat(v));
                return { kolom, anggota, agregat: agregat === undefined ? null : agregat };
            }
        }
        return null;
    },

    adaTabelPerbandingan: function (vis) {
        return !!this.dimensiPerbandingan(vis) && this.daftarTahun(vis).length >= 2;
    },

    // Data tabel perbandingan: kolom selain dimensi & tahun dikunci ke pilihan filter, atau ke baris
    // total/jumlah/tahunan bila ada, atau ke nilai pertamanya. 'kunci' mencatat penguncian untuk keterangan.
    dataPerbandingan: function (vis, dimensi) {
        const kolomTahun = this.kolomTahun(vis);
        const pilihan = vis.active_filters || {};
        let data = this.dataLengkap(vis);
        const kunci = [];

        Object.keys(vis.filters || {}).forEach(kolom => {
            if (kolom === dimensi.kolom || kolom === kolomTahun) return;
            const nilai = [...new Set(data.map(r => r[kolom]).filter(v => v !== null && v !== undefined && v !== ''))];
            if (nilai.length <= 1) return;

            let dipakai = pilihan[kolom];
            let otomatis = false;
            if (!dipakai) {
                dipakai = nilai.find(v => this.adalahAgregat(v));
                if (dipakai === undefined) dipakai = nilai[0];
                otomatis = true;
            }
            data = data.filter(r => String(r[kolom]) === String(dipakai));
            kunci.push({ kolom, nilai: dipakai, otomatis });
        });

        return { data, kunci };
    },

    updateVisualization: function (indicatorId) {
        const visCard = document.getElementById(`vis-card-${indicatorId}`);
        if (!visCard) return;

        const selects = visCard.querySelectorAll('.auto-filter-select');
        let filters = {};
        selects.forEach(sel => filters[sel.name] = sel.value);

        visCard.style.opacity = '0.5';
        const params = new URLSearchParams(Object.entries(filters).map(([k, v]) => [`filters[${k}]`, v])).toString();

        fetch(`${window.DashboardConfig.routes.getFilteredData}?indicator_id=${indicatorId}&${params}`)
            .then(res => res.json())
            .then(result => {
                const visIndex = this.visualizations.findIndex(v => v.id == indicatorId);
                this.visualizations[visIndex].parsed_data = result.data;
                this.visualizations[visIndex].unit = result.unit;
                this.visualizations[visIndex].selected_year = result.selected_year;
                this.visualizations[visIndex].temporal_column = result.temporal_column;

                // SIMPAN STATUS FILTER AKTIF
                this.visualizations[visIndex].active_filters = result.active_filters || filters;

                this.destroyVisualizations(indicatorId, this.visualizations[visIndex].available_types);
                this.renderAllVisualizations(this.visualizations[visIndex]);
                visCard.style.opacity = '1';
            })
            .catch(err => {
                console.error(err);
                visCard.style.opacity = '1';
            });
    },

    destroyVisualizations: function (indicatorId, types) {
        types.forEach(type => {
            const uniqueId = `vis-${indicatorId}-${type}`;
            const instanceData = this.instances[uniqueId];
            if (instanceData) {
                if (instanceData.type === 'chart') instanceData.instance.destroy();
                else if (instanceData.type === 'map') {
                    instanceData.instance.remove();
                    if (instanceData.legend) instanceData.legend.remove();
                }
                delete this.instances[uniqueId];
            }
        });
        const placeholder = document.getElementById(`vis-placeholder-${indicatorId}`);
        if (placeholder) placeholder.innerHTML = '';
    },

    createVisualization: function (vis, type) {
        const uniqueId = `vis-${vis.id}-${type}`;
        const data = this.dataLengkap(vis);
        if (!data || data.length === 0) return;

        if (['line', 'bar'].includes(type)) this.createJsChart(vis, type, uniqueId);
        else if (type === 'pie') this.createPieChart(vis, uniqueId);
        else if (type === 'choropleth') this.createLeafletMap(vis, uniqueId);
        else if (type === 'pyramid') this.createPyramidChart(vis, uniqueId);
        else if (type === 'card') this.createValueCard(vis, uniqueId); // Panggil fungsi card baru
    },

    createJsChart: function (vis, type, elementId) {
        const ctx = document.getElementById(elementId);
        if (!ctx) return;
        const config = vis.visualization_config;
        const activeFilters = vis.active_filters || {};
        const dimensi = this.dimensiPerbandingan(vis);

        // --- PENGAMAN GARIS TERLALU BANYAK PADA GRAFIK TREN ---
        // Kolom dengan lebih dari 8 anggota (mis. 17 lapangan usaha atau 16 kelompok umur) yang filternya masih
        // "Semua": tampilkan baris total/jumlahnya. Bila tidak ada baris total, tampilkan 8 anggota bernilai
        // terbesar (sisanya dapat dimunculkan lewat legenda). Rincian semua anggota ada di tabel perbandingan.
        const kolomRingkas = {};
        let kolomTeratas = null;
        if (type === 'line' && vis.filters) {
            Object.keys(vis.filters).forEach(filterCol => {
                if (filterCol === config.x_axis_temporal) return;
                // Jangan ringkas filter Bulan karena akan kita jadikan Sumbu X
                if (filterCol.toLowerCase() === 'bulan') return;
                if (activeFilters[filterCol]) return;

                const opsi = vis.filters[filterCol].options;
                const anggota = opsi.filter(o => !this.adalahAgregat(o));
                if (anggota.length <= this.chartColors.length) return;

                const agregat = opsi.find(o => this.adalahAgregat(o));
                if (agregat !== undefined) kolomRingkas[filterCol] = agregat;
                else if (!kolomTeratas) kolomTeratas = filterCol;
            });
        }

        let data = this.dataLengkap(vis);
        Object.keys(kolomRingkas).forEach(col => {
            data = data.filter(item => String(item[col]) === String(kolomRingkas[col]));
        });

        // --- 🌟 LOGIKA CERDAS V2: SWITCH TREN TAHUNAN VS BULANAN ---
        let xColumn = (type === 'line' && config.x_axis_temporal) ? config.x_axis_temporal : config.x_axis_categorical;

        // JIKA BUKAN LINE CHART TAPI KATEGORI KOSONG (Misal Bar Chart Data Memanjang), GUNAKAN TAHUN
        if (!xColumn && config.x_axis_temporal) {
            xColumn = config.x_axis_temporal;
        }

        const hasBulanFilter = vis.filters ? Object.keys(vis.filters).find(k => k.toLowerCase() === 'bulan') : null;
        const selectedBulan = hasBulanFilter ? activeFilters[hasBulanFilter] : null;

        let isMonthTrend = false;

        // JIKA Tahun dipilih DAN ada filter Bulan:
        // Maka Sumbu X dipaksa jadi Bulan (baik untuk "Semua" maupun bulan spesifik)
        if (type === 'line' && vis.selected_year && hasBulanFilter) {
            xColumn = hasBulanFilter;
            isMonthTrend = true;

            // Isolasi data HANYA untuk tahun yang dipilih saja
            data = data.filter(item => item[vis.temporal_column] == vis.selected_year);
        }

        let yColumns = config.y_axis_numeric || [];
        const kolomTahun = this.kolomTahun(vis);

        // --- 🌟 LOGIKA CERDAS V3 (REVISI AMAN) ---
        let detectedGroup = (config.group_by && config.group_by !== xColumn) ? config.group_by : null;

        // Grafik tren membandingkan anggota dimensi utama (mis. semua kecamatan) selama filternya "Semua".
        if (type === 'line' && dimensi && dimensi.kolom !== xColumn && !activeFilters[dimensi.kolom] && !(dimensi.kolom in kolomRingkas)) {
            detectedGroup = dimensi.kolom;
        }
        if (detectedGroup && detectedGroup in kolomRingkas) detectedGroup = null;

        if (!detectedGroup || (activeFilters[detectedGroup] && activeFilters[detectedGroup] !== '')) {
            if (vis.filters) {
                const potentialGroups = Object.keys(vis.filters).filter(k =>
                    k !== xColumn &&
                    k !== kolomTahun &&
                    k !== vis.temporal_column &&
                    k.toLowerCase() !== 'bulan' &&
                    !(k in kolomRingkas) &&
                    (!activeFilters[k] || activeFilters[k] === '')
                );

                if (potentialGroups.length > 0) {
                    const preferredCol = potentialGroups.find(k =>
                        ['kategori', 'variabel', 'jenis', 'indikator', 'satuan'].includes(k.toLowerCase())
                    );
                    detectedGroup = preferredCol || potentialGroups[0];
                }
            }
        }

        // Kunci variabelnya di sini agar tidak memicu SyntaxError di baris bawahnya
        const groupColumn = detectedGroup;

        Object.keys(activeFilters).forEach(col => {
            const val = activeFilters[col];
            // Abaikan saringan data jika kolom tersebut sedang dipakai sebagai Sumbu X 
            // ATAU sebagai Group (agar item lain bisa diredupkan, bukan dihilangkan)
            let isIgnored = (col === vis.temporal_column || col === kolomTahun || col === xColumn || col === groupColumn);

            if (val && !isIgnored) {
                data = data.filter(item => item[col] == val);
            }
        });

        // ====================================================================
        // >>> LOGIKA CERDAS BARU: PRIORITASKAN 'JUMLAH' JIKA FILTER POSISI 'SEMUA' <<<
        // ====================================================================
        // Baris ringkasan termasuk total kota ("Pematangsiantar"), sehingga kolom kecamatan yang tidak sedang
        // dibandingkan memakai angka kota, bukan angka kecamatan pertama.
        if (vis.filters) {
            Object.keys(vis.filters).forEach(col => {
                if (col !== vis.temporal_column && col !== kolomTahun && col !== xColumn && col !== groupColumn && (!activeFilters[col] || activeFilters[col] === '')) {
                    const hasSummary = data.some(item => this.adalahAgregat(item[col]));

                    if (hasSummary) {
                        data = data.filter(item => this.adalahAgregat(item[col]));
                    }
                }
            });
        }
        // ====================================================================

        // Grafik batang menampilkan satu tahun: pilihan filter Tahun, atau tahun terbaru selama masih "Semua".
        const batangSatuTahun = type === 'bar' && kolomTahun && xColumn !== kolomTahun;
        if (batangSatuTahun) {
            const tahun = this.tahunTampil(vis);
            data = data.filter(item => item[kolomTahun] == tahun);
        }

        const monthOrder = { 'januari': 1, 'februari': 2, 'maret': 3, 'april': 4, 'mei': 5, 'juni': 6, 'juli': 7, 'agustus': 8, 'september': 9, 'oktober': 10, 'november': 11, 'desember': 12, 'tahunan': 13 };
        let labels = [...new Set(data.map(i => i[xColumn]))].sort((a, b) => {
            const aLower = String(a).toLowerCase();
            const bLower = String(b).toLowerCase();
            if (monthOrder[aLower] && monthOrder[bLower]) {
                return monthOrder[aLower] - monthOrder[bLower];
            }
            return a > b ? 1 : (a < b ? -1 : 0);
        });

        // Batang total (mis. kota Pematangsiantar = jumlah semua kecamatan) tidak dijajarkan dengan batang
        // kecamatan karena skalanya jauh lebih besar; angkanya ada di Detail Nilai dan tabel perbandingan.
        if (type === 'bar' && xColumn !== kolomTahun) {
            const pilihanX = activeFilters[xColumn];
            const labelRinci = labels.filter(lbl => !this.adalahAgregat(lbl));
            if (labelRinci.length >= 2 && !(pilihanX && this.adalahAgregat(pilihanX))) labels = labelRinci;
        }

        // --- 🌟 FAILSAFE: Pastikan yColumns terdeteksi ---
        yColumns = config.y_axis_numeric || [];
        if (yColumns.length === 0 && data.length > 0) {
            const possibleCols = Object.keys(data[0]).filter(k => k !== xColumn && k !== vis.temporal_column);
            const fallbackCol = possibleCols.find(k => !isNaN(parseFloat(data[0][k])));
            if (fallbackCol) yColumns = [fallbackCol];
        }

        // Anggota grup (mis. kecamatan), urut abjad. Baris total (mis. kota) tidak dijadikan garis/batang
        // sejajar selama ada minimal dua anggota rinci; pada grafik tren ia menjadi garis acuan bila sebanding.
        let groups = groupColumn ? [...new Set(data.map(i => i[groupColumn]))].sort() : [];
        let grupAgregat = null;
        if (groupColumn) {
            const grupRinci = groups.filter(g => !this.adalahAgregat(g));
            const pilihanGrup = activeFilters[groupColumn];
            if (grupRinci.length >= 2 && grupRinci.length < groups.length && !(pilihanGrup && this.adalahAgregat(pilihanGrup))) {
                grupAgregat = groups.find(g => this.adalahAgregat(g));
                groups = grupRinci;
            }
        }

        // Lebih dari 8 garis tanpa baris total: 8 anggota bernilai terbesar pada titik terakhir yang tampil,
        // sisanya disembunyikan (bisa dimunculkan dengan klik legenda) agar warna kategori tidak berulang.
        let grupTampil = null;
        if (type === 'line' && groupColumn && groups.length > this.chartColors.length) {
            const lblTerakhir = labels[labels.length - 1];
            const nilaiTerakhir = g => {
                const v = parseFloat(data.find(item => item[xColumn] == lblTerakhir && item[groupColumn] == g)?.[yColumns[0]]);
                return isNaN(v) ? -Infinity : v;
            };
            grupTampil = [...groups].sort((a, b) => nilaiTerakhir(b) - nilaiTerakhir(a)).slice(0, this.chartColors.length).sort();
        }

        // Warna tetap per anggota grup (sama dengan grafik lain); anggota tersembunyi berwarna abu-abu.
        const warnaGrup = (grp, idx) => {
            if (grupTampil) {
                const i = grupTampil.indexOf(grp);
                return i === -1 ? '#94A3B8' : this.chartColors[i];
            }
            return this.warnaAnggota(vis, groupColumn, grp) || this.chartColors[idx % this.chartColors.length];
        };

        // ====================================================================
        // >>> LOGIKA CERDAS V6: PISAHKAN TAHUNAN SEBAGAI GARIS REFERENSI <<<
        // ====================================================================
        let tahunanDataByGroup = {};
        let tahunanDataByCol = {};
        const hasTahunanLabel = isMonthTrend && labels.some(lbl => String(lbl).toLowerCase() === 'tahunan');

        if (hasTahunanLabel && labels.length > 1 && type === 'line') {
            labels = labels.filter(lbl => String(lbl).toLowerCase() !== 'tahunan');

            if (groupColumn) {
                groups.forEach(grp => {
                    tahunanDataByGroup[grp] = data.find(item => String(item[xColumn]).toLowerCase() === 'tahunan' && item[groupColumn] == grp)?.[yColumns[0]] || null;
                });
            } else {
                yColumns.forEach(yCol => {
                    const matchedRows = data.filter(item => String(item[xColumn]).toLowerCase() === 'tahunan');
                    if (matchedRows.length > 0) {
                        tahunanDataByCol[yCol] = matchedRows.reduce((s, i) => s + (isNaN(parseFloat(i[yCol])) ? 0 : parseFloat(i[yCol])), 0);
                    } else {
                        tahunanDataByCol[yCol] = null;
                    }
                });
            }
        }

        let datasets = [];

        if (groupColumn) {
            groups.forEach((grp, idx) => {
                const yCol = yColumns[0];
                const warna = warnaGrup(grp, idx);
                const d = labels.map(lbl => data.find(item => item[xColumn] == lbl && item[groupColumn] == grp)?.[yCol] || null);

                let isDimmed = false;
                const isGroupFiltered = activeFilters[groupColumn] && activeFilters[groupColumn] !== '';
                if (isGroupFiltered && String(grp).toLowerCase().trim() !== String(activeFilters[groupColumn]).toLowerCase().trim()) {
                    isDimmed = true;
                }

                // Tren per kecamatan: kecamatan yang tidak dipilih tetap tampil sebagai garis abu-abu tipis di
                // belakang (pembanding). Grup lain (mis. Kategori) yang tidak dipilih tetap disembunyikan.
                const pembanding = isDimmed && type === 'line' && dimensi && groupColumn === dimensi.kolom;
                if (isDimmed && type === 'line' && !pembanding) {
                    return;
                }
                if (pembanding) {
                    datasets.push({
                        label: grp,
                        data: d,
                        isDimmed: true,
                        borderColor: '#CBD5E1',
                        backgroundColor: '#CBD5E1',
                        borderWidth: 1.5,
                        fill: false,
                        tension: 0.1,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        order: 1
                    });
                    return;
                }

                // Untuk Bar/Pie Chart, tetap tampilkan sebagai redup jika grup < 6
                if (isDimmed && type !== 'line' && groups.length >= 6) {
                    return;
                }

                let pointRadius = 3, pointHoverRadius = 5, pointBorderWidth = 1;

                if (type === 'line') {
                    if (isMonthTrend && selectedBulan) {
                        pointRadius = labels.map(lbl => String(lbl).toLowerCase() === String(selectedBulan).toLowerCase() ? 8 : (isDimmed ? 1 : 3));
                        pointHoverRadius = labels.map(lbl => String(lbl).toLowerCase() === String(selectedBulan).toLowerCase() ? 10 : 5);
                        pointBorderWidth = labels.map(lbl => String(lbl).toLowerCase() === String(selectedBulan).toLowerCase() ? 3 : 1);
                    } else if (!isMonthTrend && vis.selected_year && xColumn === vis.temporal_column) {
                        pointRadius = labels.map(lbl => lbl == vis.selected_year ? 8 : (isDimmed ? 1 : 3));
                        pointHoverRadius = labels.map(lbl => lbl == vis.selected_year ? 10 : 5);
                        pointBorderWidth = labels.map(lbl => lbl == vis.selected_year ? 3 : 1);
                    } else {
                        pointRadius = isDimmed ? 1 : 3;
                    }
                }


                let bgColors = isDimmed ? (warna + '20') : (warna + 'D9');
                let borderColors = isDimmed ? (warna + '40') : warna;

                // Jika ini Bar Chart, dukung peredupan individual bar berdasarkan filter Sumbu X (meskipun Grouped)
                if (type === 'bar') {
                    const isXFiltered = activeFilters[xColumn] && activeFilters[xColumn] !== '';
                    if (isXFiltered) {
                        const targetX = String(activeFilters[xColumn]).toLowerCase().trim();
                        bgColors = labels.map(lbl => {
                            if (isDimmed) return warna + '10'; // Super redup jika grupnya juga redup
                            const isMatch = String(lbl).toLowerCase().trim() === targetX;
                            return isMatch ? (warna + 'D9') : (warna + '20');
                        });
                        borderColors = labels.map(lbl => {
                            if (isDimmed) return warna + '20';
                            const isMatch = String(lbl).toLowerCase().trim() === targetX;
                            return isMatch ? warna : (warna + '40');
                        });
                    }
                }

                // Banyak garis: garis lebih tipis agar persilangannya tetap terbaca.
                const banyakGaris = type === 'line' && groups.length > 4;

                datasets.push({
                    label: grp,
                    data: d,
                    isDimmed: isDimmed,
                    hidden: grupTampil ? !grupTampil.includes(grp) : false,
                    borderColor: borderColors,
                    backgroundColor: bgColors,
                    borderWidth: type === 'line' ? (isDimmed ? 1 : (banyakGaris ? 2 : 3)) : 1,
                    fill: type !== 'line',
                    tension: 0.1,
                    pointRadius: pointRadius,
                    pointHoverRadius: pointHoverRadius,
                    pointBorderWidth: pointBorderWidth,
                    pointBackgroundColor: warna
                });
            });
        } else {
            let dataForAggregation = data;

            // Warna batang tunggal: satu warna per kecamatan/kategori yang sama dengan grafik lain (paling banyak
            // 8). Batang per tahun atau lebih dari 8 kategori memakai satu warna.
            const warnaBatang = (lbl, i) => {
                if (xColumn === kolomTahun || labels.length > this.chartColors.length) return this.chartColors[0];
                return this.warnaAnggota(vis, xColumn, lbl) || this.chartColors[i % this.chartColors.length];
            };

            yColumns.forEach((yCol, idx) => {
                // --- 🌟 FORCE PARSE FLOAT: Jamin data dihitung sebagai angka murni ---
                const d = labels.map(lbl => {
                    const matchedRows = dataForAggregation.filter(item => item[xColumn] == lbl);
                    return matchedRows.reduce((s, i) => {
                        let val = parseFloat(i[yCol]);
                        if (isNaN(val)) val = 0; // Ubah nilai rusak jadi 0 agar grafik tidak error
                        return s + val;
                    }, 0);
                });

                // Batang: warna pekat. Garis: arsiran tipis di bawah garis.
                let bgColors = this.chartColors[idx % this.chartColors.length] + (type === 'line' ? '1F' : 'D9');
                let borderColors = this.chartColors[idx % this.chartColors.length];

                // Bar Chart tunggal: warna per batang (pilihan filter disorot, lainnya diredupkan)
                if ((type === 'bar' || type === 'pie') && !groupColumn) {
                    bgColors = labels.map((lbl, i) => {
                        const isFiltered = activeFilters[xColumn] && activeFilters[xColumn] !== '';
                        if (isFiltered && String(lbl).toLowerCase().trim() !== String(activeFilters[xColumn]).toLowerCase().trim()) {
                            return warnaBatang(lbl, i) + '20'; // Redup warna asli
                        }
                        return warnaBatang(lbl, i) + (type === 'pie' ? 'E6' : 'D9');
                    });
                    borderColors = labels.map((lbl, i) => {
                        const isFiltered = activeFilters[xColumn] && activeFilters[xColumn] !== '';
                        if (isFiltered && String(lbl).toLowerCase().trim() !== String(activeFilters[xColumn]).toLowerCase().trim()) {
                            return warnaBatang(lbl, i) + '40'; // Redup warna asli
                        }
                        return warnaBatang(lbl, i);
                    });
                }

                let pointRadius = 3, pointHoverRadius = 5, pointBorderWidth = 1;

                if (type === 'line') {
                    if (isMonthTrend && selectedBulan) {
                        pointRadius = labels.map(lbl => String(lbl).toLowerCase() === String(selectedBulan).toLowerCase() ? 8 : 3);
                        pointHoverRadius = labels.map(lbl => String(lbl).toLowerCase() === String(selectedBulan).toLowerCase() ? 10 : 5);
                        pointBorderWidth = labels.map(lbl => String(lbl).toLowerCase() === String(selectedBulan).toLowerCase() ? 3 : 1);
                    } else if (!isMonthTrend && vis.selected_year && xColumn === vis.temporal_column) {
                        pointRadius = labels.map(lbl => lbl == vis.selected_year ? 8 : 3);
                        pointHoverRadius = labels.map(lbl => lbl == vis.selected_year ? 10 : 5);
                        pointBorderWidth = labels.map(lbl => lbl == vis.selected_year ? 3 : 1);
                    }
                }

                datasets.push({
                    label: yCol,
                    data: d,
                    borderColor: borderColors,
                    backgroundColor: bgColors,
                    // Garis tunggal diberi arsiran tipis; garis ganda tanpa arsiran agar tidak saling menutupi.
                    fill: type === 'line' ? yColumns.length === 1 : true,
                    pointBackgroundColor: '#ffffff',
                    tension: 0.1,
                    pointRadius: pointRadius,
                    pointHoverRadius: pointHoverRadius,
                    pointBorderWidth: pointBorderWidth
                });
            });
        }

        // TAHUNAN SEBAGAI GARIS PUTUS-PUTUS
        if (hasTahunanLabel && labels.length > 1 && type === 'line') {
            if (groupColumn) {
                groups.forEach((grp, idx) => {
                    const val = tahunanDataByGroup[grp];
                    if (val !== null && val !== undefined && !isNaN(val)) {
                        datasets.push({
                            label: grp + ' (Tahunan)',
                            data: labels.map(() => val),
                            borderColor: warnaGrup(grp, idx),
                            borderDash: [5, 5],
                            fill: false,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            hitRadius: 15,
                            borderWidth: 2,
                            datalabels: {
                                display: (context) => {
                                    const isTahunanSelected = selectedBulan && String(selectedBulan).toLowerCase() === 'tahunan';
                                    return isTahunanSelected && context.dataIndex === Math.floor(labels.length / 2);
                                },
                                align: 'bottom',
                                color: warnaGrup(grp, idx),
                                backgroundColor: '#ffffff',
                                borderColor: warnaGrup(grp, idx),
                                borderWidth: 1,
                                borderRadius: 4,
                                font: { weight: 'bold', size: 10 },
                                formatter: () => `Tahunan: ${val}`
                            }
                        });
                    }
                });
            } else {
                yColumns.forEach((col, idx) => {
                    const val = tahunanDataByCol[col];
                    if (val !== null && val !== undefined && !isNaN(val)) {
                        datasets.push({
                            label: (yColumns.length > 1 ? col : 'Nilai') + ' (Tahunan)',
                            data: labels.map(() => val),
                            borderColor: '#EF4444',
                            borderDash: [5, 5],
                            fill: false,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            hitRadius: 15,
                            borderWidth: 2,
                            datalabels: {
                                display: (context) => {
                                    const isTahunanSelected = selectedBulan && String(selectedBulan).toLowerCase() === 'tahunan';
                                    return isTahunanSelected && context.dataIndex === Math.floor(labels.length / 2);
                                },
                                align: 'bottom',
                                color: '#EF4444',
                                backgroundColor: '#fee2e2',
                                borderRadius: 4,
                                font: { weight: 'bold', size: 10 },
                                formatter: () => `Tahunan: ${val}`
                            }
                        });
                    }
                });
            }
        }

        // Garis acuan total/kota (putus-putus abu-abu) bila skalanya sebanding dengan anggota, mis. kepadatan
        // atau rasio kota di antara kecamatan. Total hasil penjumlahan (jauh lebih besar) tidak digambar agar
        // garis kecamatan tidak tertekan ke bawah; angkanya ada di tabel perbandingan.
        if (type === 'line' && grupAgregat !== null && !isMonthTrend) {
            const yCol = yColumns[0];
            const dAgregat = labels.map(lbl => {
                const v = parseFloat(data.find(item => item[xColumn] == lbl && item[groupColumn] == grupAgregat)?.[yCol]);
                return isNaN(v) ? null : v;
            });
            const angkaRinci = datasets.flatMap(ds => ds.data).map(v => parseFloat(v)).filter(v => !isNaN(v));
            const angkaAgregat = dAgregat.filter(v => v !== null);
            if (angkaRinci.length && angkaAgregat.length && Math.max(...angkaAgregat) <= Math.max(...angkaRinci) * 1.5) {
                datasets.push({
                    label: grupAgregat,
                    data: dAgregat,
                    isAcuan: true,
                    borderColor: '#64748B',
                    backgroundColor: '#64748B',
                    borderDash: [6, 4],
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    hitRadius: 10
                });
            }
        }

        // Keterangan di judul: tahun grafik batang, jumlah garis grafik tren, dan alasan ringkasan.
        if (type === 'bar') {
            this.aturLabel(elementId, batangSatuTahun ? this.labelTahun(vis) : '');
        } else if (type === 'line') {
            let label = '';
            let catatan = '';
            if (groupColumn && !activeFilters[groupColumn] && groups.length >= 2) {
                label = grupTampil ? `${grupTampil.length} dari ${groups.length} ${groupColumn}` : `${groups.length} ${groupColumn}`;
            } else if (datasets.some(ds => ds.isDimmed)) {
                label = `${activeFilters[groupColumn]} disorot`;
                catatan = `Garis abu-abu: ${groupColumn.toLowerCase()} lain sebagai pembanding.`;
            }
            const ringkas = Object.keys(kolomRingkas);
            const dimensiTabel = this.adaTabelPerbandingan(vis) ? this.dimensiPerbandingan(vis) : null;
            if (ringkas.length) {
                catatan = `Menampilkan ${ringkas.map(k => kolomRingkas[k]).join(', ')}. Pilih ${ringkas.join(' / ')} pada filter untuk melihat rinciannya`
                    + (dimensiTabel && ringkas.includes(dimensiTabel.kolom) ? `; semua ${dimensiTabel.kolom} per tahun ada di tabel perbandingan di bawah.` : '.');
            } else if (grupTampil) {
                catatan = `Menampilkan ${grupTampil.length} ${groupColumn} dengan nilai terbesar pada ${labels[labels.length - 1]}. Klik nama di legenda untuk memunculkan yang lain.`;
            }
            this.aturLabel(elementId, label, catatan);
        }

        const allDatasetValues = datasets.flatMap(d => d.data).filter(v => v !== null && v > 0);
        const maxVal = allDatasetValues.length ? Math.max(...allDatasetValues) : 0;
        const minVal = allDatasetValues.length ? Math.min(...allDatasetValues) : 0;
        const useLogScale = (type === 'line' && maxVal > 0 && minVal > 0 && (maxVal / minVal > 100));

        // Tren banyak garis: satu tooltip memuat semua kecamatan pada tahun yang ditunjuk, urut dari terbesar.
        const tooltipPerTahun = type === 'line' && datasets.length > 1;
        // Banyak batang (mis. 8 kecamatan): label angka diperkecil, dan yang bertumpuk disembunyikan.
        const labelBatangRapat = type === 'bar' && labels.length * Math.max(1, datasets.length) > 5;
        // Layar sempit (HP): legenda banyak kecamatan diperkecil dan grafiknya dipertinggi agar garis tetap terbaca.
        const lebarWadah = ctx.parentElement ? ctx.parentElement.clientWidth : 0;
        const layarSempit = lebarWadah > 0 && lebarWadah < 520;
        if (layarSempit && type === 'line' && datasets.length > 4) ctx.parentElement.style.minHeight = '420px';

        const chart = new Chart(ctx, {
            type: type,
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 40, right: 40, left: 10, bottom: 10 } },
                ...(tooltipPerTahun ? { interaction: { mode: 'index', intersect: false } } : {}),
                plugins: {
                    legend: {
                        display: datasets.filter(ds => !ds.isDimmed).length > 1 && !!groupColumn,
                        // Garis pembanding abu-abu tidak dicantumkan di legenda.
                        labels: {
                            filter: (item, chartData) => !chartData.datasets[item.datasetIndex].isDimmed,
                            ...(layarSempit ? { font: { size: 10 }, padding: 8, boxWidth: 6, boxHeight: 6 } : {})
                        }
                    },
                    datalabels: {
                        display: function (context) {
                            const val = context.dataset.data[context.dataIndex];
                            if (val === null || val === 0 || val === undefined) return false;
                            if (context.dataset.isAcuan) return false;

                            if (type === 'line') {
                                if (context.dataset.isDimmed) return false;
                                // Lebih dari 4 garis: angka dibaca lewat tooltip/tabel agar label tidak bertumpuk.
                                if (context.chart.data.datasets.filter(ds => !ds.isDimmed && !ds.isAcuan).length > 4) return false;
                                if (isMonthTrend) {
                                    // Hanya munculkan label di bulan yang dipilih.
                                    // Jika bulan belum dipilih ("Semua"), jangan tampilkan label sama sekali.
                                    if (selectedBulan) {
                                        return String(context.chart.data.labels[context.dataIndex]).toLowerCase() === String(selectedBulan).toLowerCase();
                                    }
                                    return false;
                                }
                                if (vis.selected_year) return context.chart.data.labels[context.dataIndex] == vis.selected_year;
                            }
                            if (type === 'bar') {
                                if (context.dataset.isDimmed) return false;
                                const lbl = context.chart.data.labels[context.dataIndex];
                                const isFiltered = activeFilters[xColumn] && activeFilters[xColumn] !== '';
                                if (isFiltered && String(lbl).toLowerCase().trim() !== String(activeFilters[xColumn]).toLowerCase().trim()) {
                                    return false;
                                }
                                return labelBatangRapat ? 'auto' : true;
                            }
                            return false;
                        },
                        formatter: function (value) { return window.formatNumberID(value); },
                        align: function (context) {
                            if (type === 'bar') return 'end';
                            return context.datasetIndex % 2 === 0 ? 'top' : 'bottom';
                        },
                        anchor: type === 'bar' ? 'end' : 'center',
                        backgroundColor: 'rgba(15, 23, 42, 0.85)',
                        color: 'white',
                        borderRadius: 6,
                        padding: labelBatangRapat ? { top: 2, bottom: 2, left: 4, right: 4 } : { top: 4, bottom: 4, left: 7, right: 7 },
                        font: { weight: '600', size: labelBatangRapat ? 10 : 11 },
                        clip: false,
                        clamp: true
                    },
                    tooltip: {
                        ...(tooltipPerTahun ? { itemSort: (a, b) => (b.parsed.y ?? -Infinity) - (a.parsed.y ?? -Infinity) } : {}),
                        filter: function (tooltipItem) {
                            try {
                                const dataset = datasets[tooltipItem.datasetIndex];

                                // Sembunyikan jika dataset diredupkan (Grouped chart)
                                if (dataset && dataset.isDimmed) return false;

                                // Sembunyikan jika item individual diredupkan (Single Bar/Pie chart)
                                if (dataset && Array.isArray(dataset.backgroundColor)) {
                                    const bgColor = dataset.backgroundColor[tooltipItem.dataIndex];
                                    if (typeof bgColor === 'string' && bgColor.endsWith('20')) {
                                        return false;
                                    }
                                }
                            } catch (e) { }
                            return true;
                        },
                        callbacks: {
                            title: function (context) {
                                const isOnlyTahunan = context.every(item => String(item.dataset.label).includes('(Tahunan)'));
                                if (isOnlyTahunan) return '';
                                return context[0].label;
                            },
                            label: function (context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.y !== null && context.parsed.y !== undefined) {
                                    label += window.formatNumberID(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            // Tahun boleh dilompati bila tidak muat (layar sempit); nama kategori selalu tampil.
                            autoSkip: type === 'line' && xColumn === kolomTahun,
                            maxRotation: 45,
                            minRotation: 0, // miring hanya bila label tidak muat
                            callback: function (value) {
                                let label = value;
                                if (this.getLabelForValue) {
                                    label = this.getLabelForValue(value);
                                }
                                if (typeof label === 'string' && label.length > 25) {
                                    return label.substring(0, 25) + '...';
                                }
                                return label;
                            }
                        }
                    },
                    y: {
                        type: useLogScale ? 'logarithmic' : 'linear',
                        // Batang selalu dari 0. Tren banyak garis (tanpa arsiran) mengikuti rentang datanya, agar
                        // selisih antarkecamatan (mis. rasio 90-104) tidak tertekan menjadi satu pita tipis.
                        beginAtZero: !useLogScale && !(type === 'line' && datasets.length > 1),
                        grace: type === 'bar' ? '15%' : '5%',
                        ticks: {
                            callback: function (value) {
                                if (useLogScale) {
                                    const strVal = value.toString();
                                    if (strVal.charAt(0) === '1' && strVal.replace(/0/g, '') === '1') {
                                        return window.formatNumberID(value);
                                    }
                                    return '';
                                }
                                return window.formatNumberID(value);
                            }
                        }
                    }
                }
            }
        });
        this.instances[elementId] = { type: 'chart', instance: chart };
    },

    createPieChart: function (vis, elementId) {
        const ctx = document.getElementById(elementId);
        if (!ctx) return;
        const config = vis.visualization_config;

        // --- 1. SATU TAHUN: pilihan filter Tahun, atau tahun terbaru selama filter Tahun masih "Semua" ---
        let data = this.dataLengkap(vis);
        const kolomTahun = this.kolomTahun(vis);
        if (kolomTahun) {
            const tahun = this.tahunTampil(vis);
            data = data.filter(item => item[kolomTahun] == tahun);
            this.aturLabel(elementId, this.labelTahun(vis));
        }

        const activeFilters = vis.active_filters || {};

        // --- 🌟 PENGAMAN V6: WAJIBKAN FILTER KHUSUS TRIWULAN ---
        if (vis.filters) {
            let missingCat = null;
            const isTriwulan = (vis.name || '').toLowerCase().includes('triwulan');

            Object.keys(vis.filters).forEach(k => {
                if (k !== vis.temporal_column && k !== config.pie_label && (!activeFilters[k] || activeFilters[k] === '')) {
                    if (k.toLowerCase().includes('triwulan') || (isTriwulan && k.toLowerCase() === 'kategori')) {
                        missingCat = k;
                    }
                }
            });

            if (missingCat) {
                const container = ctx.parentElement;
                container.innerHTML = `<div class="flex flex-col items-center justify-center h-full bg-blue-50 text-blue-700 p-6 rounded-lg border border-blue-200 text-center">
                    <i class="fas fa-layer-group fa-2x mb-3 text-blue-400"></i>
                    <p class="text-sm font-medium">Silakan pilih spesifik <b>${missingCat}</b> terlebih dahulu untuk menampilkan Komposisi Data.</p>
                </div>`;
                return;
            }
        }

        // --- 2. JALUR KHUSUS: DETEKSI APAKAH USER MEMILIH "TOTAL" ---
        let isTotalRequested = false;
        let requestedTotalLabel = '';

        if (config.pie_label && activeFilters[config.pie_label]) {
            const val = activeFilters[config.pie_label];
            const valLower = String(val).toLowerCase().trim();
            if (valLower === 'total' || valLower === 'jumlah' || valLower === 'pdrb' || valLower === 'produk domestik regional bruto' || valLower.startsWith('jumlah ') || valLower.startsWith('total ')) {
                isTotalRequested = true;
                requestedTotalLabel = valLower;
            }
        }

        let aggregated = {};
        let kolomLabelPie = config.pie_label; // kolom yang menjadi irisan (untuk warna tetap per anggota)

        // Terapkan SEMUA filter KECUALI pie_label terlebih dahulu agar tidak ada duplikasi data (misal: sub-total + total)
        Object.keys(activeFilters).forEach(col => {
            const val = activeFilters[col];
            if (val && col !== vis.temporal_column && col !== config.pie_label) {
                data = data.filter(item => String(item[col]).toLowerCase().trim() === String(val).toLowerCase().trim());
            }
        });

        // --- 3. PERBAIKAN: CEK FORMAT DATA (LONG VS WIDE) ---
        if (config.pie_label) {
            if (isTotalRequested) {
                data.forEach(item => {
                    const lbl = item[config.pie_label];
                    if (lbl) {
                        const lblLower = String(lbl).toLowerCase().trim();
                        if (lblLower === requestedTotalLabel || lblLower === 'total' || lblLower === 'jumlah' || lblLower === 'pdrb' || lblLower === 'produk domestik regional bruto') {
                            const val = parseFloat(item[config.pie_value]) || 0;
                            aggregated['Total Keseluruhan'] = (aggregated['Total Keseluruhan'] || 0) + val;
                        }
                    }
                });
            } else {
                // JANGAN filter pie_label agar semua slice tetap tampil (untuk diredupkan nanti)
                // const val = activeFilters[config.pie_label];
                // if (val && val !== '') {
                //     data = data.filter(item => String(item[config.pie_label]).toLowerCase().trim() === String(val).toLowerCase().trim());
                // }

                let currentPieLabel = config.pie_label;
                // Jika pie_label utama sedang difilter spesifik, cari kolom kategori lain yang belum difilter
                if (activeFilters[config.pie_label] && activeFilters[config.pie_label] !== '') {
                    if (vis.filters) {
                        const fallbackCol = Object.keys(vis.filters).find(k =>
                            k !== config.pie_label &&
                            k !== vis.temporal_column &&
                            (!activeFilters[k] || activeFilters[k] === '')
                        );
                        if (fallbackCol) currentPieLabel = fallbackCol;
                    }
                }
                kolomLabelPie = currentPieLabel;

                data.forEach(item => {
                    let isAggregateRow = false;
                    Object.keys(item).forEach(key => {
                        if (key !== currentPieLabel && key !== vis.temporal_column && key !== config.pie_value) {
                            if (vis.filters && vis.filters[key] && (!activeFilters[key] || activeFilters[key] === '')) {
                                const valStr = String(item[key]).toLowerCase().trim();
                                if (valStr === 'total' || valStr === 'jumlah' || valStr === 'pdrb' || valStr === 'produk domestik regional bruto' || valStr === 'pematangsiantar' || valStr === 'kota pematangsiantar' || valStr.startsWith('jumlah ') || valStr.startsWith('total ')) {
                                    isAggregateRow = true;
                                }
                            }
                        }
                    });

                    if (!isAggregateRow) {
                        const lbl = item[currentPieLabel];
                        const val = parseFloat(item[config.pie_value]) || 0;

                        if (lbl) {
                            const labelStr = String(lbl).toLowerCase().trim();
                            if (labelStr !== 'total' && labelStr !== 'jumlah' && labelStr !== 'pdrb' && labelStr !== 'produk domestik regional bruto' && labelStr !== 'pematangsiantar' && labelStr !== 'kota pematangsiantar' && !labelStr.startsWith('jumlah ') && !labelStr.startsWith('total ')) {
                                aggregated[lbl] = (aggregated[lbl] || 0) + val;
                            }
                        }
                    }
                });
            }
        } else if (config.y_axis_numeric && config.y_axis_numeric.length > 1) {
            data.forEach(item => {
                config.y_axis_numeric.forEach(yCol => {
                    const lblLower = String(yCol).toLowerCase().trim();
                    if (lblLower !== 'total' && lblLower !== 'jumlah' && lblLower !== 'pdrb' && lblLower !== 'produk domestik regional bruto' && lblLower !== 'pematangsiantar' && lblLower !== 'kota pematangsiantar' && !lblLower.startsWith('jumlah ') && !lblLower.startsWith('total ')) {
                        const val = parseFloat(item[yCol]) || 0;
                        aggregated[yCol] = (aggregated[yCol] || 0) + val;
                    }
                });
            });
        }

        const labelsArray = Object.keys(aggregated);
        const dataArray = Object.values(aggregated);

        // --- 4. PENYESUAIAN WARNA KHUSUS & PEREDUPAN ---
        let pieColors = labelsArray.map((lbl, idx) => {
            // Warna tetap per anggota (mis. per kecamatan), sama dengan grafik tren & batang.
            const warna = (kolomLabelPie && this.warnaAnggota(vis, kolomLabelPie, lbl)) || this.chartColors[idx % this.chartColors.length];
            const defaultColor = warna + 'E6'; // 90% opacity

            if (isTotalRequested || (labelsArray.length === 1 && (labelsArray[0].toLowerCase().includes('total') || labelsArray[0].toLowerCase().includes('pdrb')))) {
                return '#4C9A2A';
            }

            let isDimmed = false;
            // Deteksi jika user sedang memfilter pie_label spesifik
            if (config.pie_label && activeFilters[config.pie_label] && activeFilters[config.pie_label] !== '') {
                const target = String(activeFilters[config.pie_label]).toLowerCase().trim();
                if (String(lbl).toLowerCase().trim() !== target) isDimmed = true;
            }

            return isDimmed ? (warna + '20') : defaultColor;
        });

        // --- 5. RENDER GRAFIK ---
        const chart = new Chart(ctx, {
            type: 'doughnut',
            plugins: [{
                id: 'pieLabelLines',
                beforeLayout: (chart) => {
                    const isSmall = chart.width < 500;
                    chart.options.layout.padding = {
                        top: 30,
                        bottom: 30,
                        left: isSmall ? 95 : 160,
                        right: isSmall ? 95 : 160
                    };
                },
                afterDraw: (chart) => {
                    const ctx = chart.ctx;
                    const isSmall = chart.width < 500;
                    const radialOffset = isSmall ? 8 : 20;
                    const horizontalLine = isSmall ? 10 : 20;
                    const fontSize = isSmall ? 9 : 11;

                    let lastRightY = -9999;
                    let lastLeftY = 9999;

                    chart.data.datasets.forEach((dataset, i) => {
                        chart.getDatasetMeta(i).data.forEach((arc, index) => {
                            const bgColor = dataset.backgroundColor[index];
                            if (typeof bgColor === 'string' && bgColor.endsWith('20')) return;
                            const val = dataset.data[index];
                            if (val === 0 || chart.getDataVisibility(index) === false) return;

                            const centerPoint = arc.getCenterPoint();
                            const angle = Math.atan2(centerPoint.y - arc.y, centerPoint.x - arc.x);
                            const radius = arc.outerRadius;

                            const startX = arc.x + Math.cos(angle) * radius;
                            const startY = arc.y + Math.sin(angle) * radius;

                            const elbowX = arc.x + Math.cos(angle) * (radius + radialOffset);
                            let elbowY = arc.y + Math.sin(angle) * (radius + radialOffset);

                            const isRight = Math.cos(angle) >= 0;

                            // Collision Detection (Anti-Tindih)
                            const minSpace = isSmall ? 24 : 30;
                            if (isRight) {
                                if (lastRightY !== -9999 && elbowY < lastRightY + minSpace) {
                                    elbowY = lastRightY + minSpace;
                                }
                                lastRightY = elbowY;
                            } else {
                                if (lastLeftY !== 9999 && elbowY > lastLeftY - minSpace) {
                                    elbowY = lastLeftY - minSpace;
                                }
                                lastLeftY = elbowY;
                            }

                            const endX = elbowX + (isRight ? horizontalLine : -horizontalLine);
                            const endY = elbowY;

                            ctx.save();
                            ctx.beginPath();
                            ctx.moveTo(startX, startY);
                            ctx.lineTo(elbowX, elbowY);
                            ctx.lineTo(endX, endY);
                            ctx.strokeStyle = '#94A3B8';
                            ctx.lineWidth = 1.2;
                            ctx.stroke();

                            const arrowSize = 5;
                            ctx.beginPath();
                            ctx.moveTo(endX, endY);
                            if (isRight) {
                                ctx.lineTo(endX - arrowSize, endY - arrowSize);
                                ctx.lineTo(endX - arrowSize, endY + arrowSize);
                            } else {
                                ctx.lineTo(endX + arrowSize, endY - arrowSize);
                                ctx.lineTo(endX + arrowSize, endY + arrowSize);
                            }
                            ctx.fillStyle = '#94A3B8';
                            ctx.fill();

                            let sum = 0;
                            dataset.data.forEach(d => sum += d);
                            let percentage = (sum > 0) ? (val * 100 / sum).toFixed(1) + "%" : "0%";
                            let formattedValue = window.formatNumberID(val);

                            ctx.fillStyle = '#1F2937';
                            ctx.font = `bold ${fontSize}px Inter, sans-serif`;
                            ctx.textAlign = isRight ? 'left' : 'right';
                            ctx.textBaseline = 'middle';

                            const textX = endX + (isRight ? 6 : -6);
                            ctx.fillText(percentage, textX, endY - (isSmall ? 5 : 6));
                            ctx.font = `normal ${fontSize}px Inter, sans-serif`;
                            ctx.fillText('(' + formattedValue + ')', textX, endY + (isSmall ? 6 : 8));

                            ctx.restore();
                        });
                    });
                }
            }],
            data: {
                labels: labelsArray,
                datasets: [{
                    data: dataArray,
                    backgroundColor: pieColors,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                layout: {
                    padding: { top: 30, bottom: 30, left: 140, right: 140 }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        display: labelsArray.length > 0,
                        labels: {
                            padding: labelsArray.length > 6 ? 8 : 15,
                            font: { size: 11, family: "'Inter', sans-serif" },
                            generateLabels: function (chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const meta = chart.getDatasetMeta(0);
                                        const style = meta.controller.getStyle(i);
                                        const isSmall = chart.width < 500;
                                        const maxLen = isSmall ? 20 : 35;
                                        let truncated = label;
                                        if (truncated.length > maxLen) {
                                            truncated = truncated.substring(0, maxLen - 3) + '...';
                                        }
                                        return {
                                            text: truncated,
                                            fillStyle: style.backgroundColor,
                                            strokeStyle: style.borderColor,
                                            lineWidth: style.borderWidth,
                                            hidden: !chart.getDataVisibility(i),
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    datalabels: {
                        display: false
                    },
                    tooltip: {
                        filter: function (tooltipItem) {
                            const bgColor = tooltipItem.dataset.backgroundColor[tooltipItem.dataIndex];
                            if (typeof bgColor === 'string' && bgColor.endsWith('20')) return false;
                            return true;
                        },
                        callbacks: {
                            label: function (context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += window.formatNumberID(context.parsed);
                                return label;
                            }
                        }
                    }
                }
            }
        });
        this.instances[elementId] = { type: 'chart', instance: chart };
    },

    createLeafletMap: function (vis, elementId) {
        const mapEl = document.getElementById(elementId);
        if (!mapEl || !vis.visualization_config.geojson) return;

        const config = vis.visualization_config;
        const geojson = config.geojson;
        // Membekukan Peta: Nonaktifkan semua interaksi agar menjadi murni visualisasi statis
        const map = L.map(elementId, {
            attributionControl: false,
            zoomControl: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            dragging: false,
            touchZoom: false,
            boxZoom: false,
            keyboard: false,
            zoomSnap: 0.1,
            zoomDelta: 0.1
        }).setView([2.96, 99.06], 12);

        // L.tileLayer dihapus sepenuhnya agar peta daerah tetangga tidak terlihat. Hanya menampilkan batas poligon GeoJSON.

        // Peta menampilkan satu tahun: pilihan filter Tahun, atau tahun terbaru selama filter Tahun masih "Semua".
        let data = this.dataLengkap(vis);
        const kolomTahun = this.kolomTahun(vis);
        if (kolomTahun) {
            const tahun = this.tahunTampil(vis);
            data = data.filter(item => item[kolomTahun] == tahun);
            this.aturLabel(elementId, this.labelTahun(vis));
        }

        // --- FILTER MANUAL JS KHUSUS PETA ---
        const activeFilters = vis.active_filters || {};
        const geoCol = config.geo_column;
        const valCol = config.val_column;

        const geoColLow = String(geoCol || '').toLowerCase().trim();
        const tempColLow = String(kolomTahun || vis.temporal_column || '').toLowerCase().trim();

        // Cek secara case-insensitive apakah filter kota utuh yang dipilih
        const selectedGeoRaw = activeFilters[Object.keys(activeFilters).find(k => String(k).toLowerCase().trim() === geoColLow)];
        const selectedGeo = selectedGeoRaw ? String(selectedGeoRaw).toLowerCase().replace(/ /g, '') : null;
        let isCitySelected = selectedGeo === 'pematangsiantar' || selectedGeo === 'kotapematangsiantar';

        Object.keys(activeFilters).forEach(col => {
            const val = activeFilters[col];
            const cLow = String(col).toLowerCase().trim();
            if (val && cLow !== tempColLow) {
                // Filter geo_column (Kecamatan) tidak menyaring data: semua kecamatan tetap diwarnai sebagai
                // pembanding, kecamatan terpilih diberi garis emas (isSelected di bawah).
                if (cLow === geoColLow) {
                    // Bypass filter kecamatan/Pematangsiantar agar semua kecamatan tetap tampil
                } else {
                    data = data.filter(item => String(item[col]).toLowerCase().trim() === String(val).toLowerCase().trim());
                }
            }
        });

        if (vis.filters) {
            Object.keys(vis.filters).forEach(col => {
                const cLow = String(col).toLowerCase().trim();
                if (cLow !== tempColLow && (!activeFilters[col] || activeFilters[col] === '')) {
                    if (cLow === geoColLow && isCitySelected) return; // Sama, bypass summary filter jika mode kota

                    const hasSummary = data.some(item => {
                        const s = String(item[col]).toLowerCase().trim();
                        return s === 'tahunan' || s === 'total' || s === 'jumlah' || s.startsWith('jumlah ') || s.startsWith('total ');
                    });

                    if (hasSummary) {
                        data = data.filter(item => {
                            const s = String(item[col]).toLowerCase().trim();
                            return s === 'tahunan' || s === 'total' || s === 'jumlah' || s.startsWith('jumlah ') || s.startsWith('total ');
                        });
                    }
                }
            });
        }

        let stats = {};
        let allVals = [];
        let cityValue = null;

        data.forEach(row => {
            const k = this.normalizeKecamatan(row[geoCol]);
            const v = parseFloat(row[valCol]);
            if (!isNaN(v)) {
                stats[k] = v;
                const kRaw = String(k).replace(/ /g, '');
                if (kRaw === 'PEMATANGSIANTAR' || kRaw === 'KOTAPEMATANGSIANTAR') {
                    cityValue = v;
                } else {
                    allVals.push(v);
                }
            }
        });

        // Deteksi otomatis mode kota jika data geografis yang tersisa HANYA berisi Pematangsiantar
        // (Misalnya indikator yang memang tidak memiliki data level kecamatan)
        if (!isCitySelected && cityValue !== null && Object.keys(stats).length === 1) {
            isCitySelected = true;
        }

        if (allVals.length === 0 && cityValue !== null) {
            allVals.push(cityValue);
        }

        const min = Math.min(...allVals);
        const max = Math.max(...allVals);
        const range = max - min;

        // Skala warna bergradasi Premium
        const getColor = (d) => {
            if (d === null || d === undefined || isNaN(d)) return '#f1f5f9'; // Abu-abu terang untuk kosong
            if (range === 0) return '#002D72'; // Biru gelap BPS

            const pct = (d - min) / range;
            return pct > 0.8 ? '#002D72' : // BPS Dark Blue
                pct > 0.6 ? '#1d4ed8' : // blue-700
                    pct > 0.4 ? '#3b82f6' : // blue-500
                        pct > 0.2 ? '#93c5fd' : // blue-300
                            '#dbeafe';  // blue-100
        };

        if (isCitySelected) {
            mapEl.classList.add('city-glow-active');
        } else {
            mapEl.classList.remove('city-glow-active');
        }

        const layer = L.geoJson(geojson, {
            style: f => {
                const n = f.properties.NAMOBJ;
                const normName = this.normalizeKecamatan(n);
                let val = stats[normName];

                if (val === undefined && cityValue !== null && (Object.keys(stats).length === 1 || isCitySelected)) {
                    val = cityValue;
                }

                // Jika isCitySelected aktif, paksa warna menggunakan Data Kota agar tidak belang-belang
                if (isCitySelected && cityValue !== null) {
                    val = cityValue;
                }

                // Bandingkan dengan yang sudah dihapus spasinya
                let isSelected = selectedGeo && normName.toLowerCase() === selectedGeo;

                // Jika isCitySelected, matikan isSelected individual agar tidak ada garis emas di dalam
                if (isCitySelected) {
                    isSelected = false;
                }

                let fillCol = getColor(val);
                let strokeCol = isSelected ? '#fbbf24' : '#ffffff';
                let strokeW = isSelected ? 3.5 : 1.5;

                // Jika isCitySelected, hilangkan garis batas antar kecamatan dengan menyamakannya dengan warna isian
                if (isCitySelected) {
                    fillCol = '#002D72'; // Force dark blue for city mode
                    strokeCol = fillCol;
                    strokeW = 1;
                }

                return {
                    fillColor: fillCol,
                    weight: strokeW,
                    color: strokeCol,
                    // Ada kecamatan terpilih: kecamatan lain dipudarkan agar pilihan menonjol.
                    fillOpacity: isSelected ? 1 : (selectedGeo && !isCitySelected ? 0.4 : 0.85),
                    className: 'map-polygon-transition' // Kelas CSS untuk animasi smooth
                };
            },
            onEachFeature: (f, l) => {
                const n = f.properties.NAMOBJ;
                const normName = this.normalizeKecamatan(n);
                let val = stats[normName];
                let isCityValue = false;

                if (val === undefined && cityValue !== null && (Object.keys(stats).length === 1 || isCitySelected)) {
                    val = cityValue;
                    isCityValue = true;
                }

                // Jika isCitySelected aktif, paksa tooltip menampilkan Data Kota agar tidak membingungkan
                if (isCitySelected && cityValue !== null) {
                    val = cityValue;
                    isCityValue = true;
                }

                if (val !== undefined) {
                    const formattedVal = window.formatNumberID(val, 2);
                    let popupText = '';

                    if (isCityValue) {
                        popupText = `
                        <div style="text-align: center; font-family: 'Inter', sans-serif;">
                            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 2px;">Kota</div>
                            <div style="font-weight: 700; font-size: 14px; color: #1e293b; margin-bottom: 6px;">PEMATANGSIANTAR</div>
                            <div style="font-weight: 800; color: #002D72; font-size: 16px; background: #f0f9ff; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                ${formattedVal} <span style="font-size: 12px; font-weight: 600; color: #3b82f6;">${vis.unit}</span>
                            </div>
                        </div>
                        `;
                    } else {
                        popupText = `
                        <div style="text-align: center; font-family: 'Inter', sans-serif;">
                            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 2px;">Kecamatan</div>
                            <div style="font-weight: 700; font-size: 14px; color: #1e293b; margin-bottom: 6px;">${n}</div>
                            <div style="font-weight: 800; color: #002D72; font-size: 16px; background: #f0f9ff; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                ${formattedVal} <span style="font-size: 12px; font-weight: 600; color: #3b82f6;">${vis.unit}</span>
                            </div>
                        </div>
                        `;
                    }

                    // Menggunakan Tooltip alih-alih Popup agar muncul saat hover
                    if (!isCitySelected) {
                        l.bindTooltip(popupText, {
                            sticky: true,
                            className: 'premium-map-tooltip',
                            direction: 'top',
                            offset: [0, -10]
                        });
                    }

                    // Efek Hover (Micro-animation)
                    l.on({
                        mouseover: (e) => {
                            const targetLayer = e.target;
                            if (!isCitySelected) {
                                targetLayer.setStyle({
                                    weight: 3,
                                    color: '#fbbf24', // Warna emas (Amber) saat dihover
                                    fillOpacity: 1
                                });
                                if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                                    targetLayer.bringToFront();
                                }
                            } else {
                                // Jika mode kota keseluruhan, biarkan statis (tidak berubah warna)
                            }
                        },
                        mouseout: (e) => {
                            if (!isCitySelected) {
                                layer.resetStyle(e.target);
                            }
                        }
                    });
                }
            }
        }).addTo(map);

        // Jika mode kota, gunakan satu tooltip bersama yang dibounce untuk mencegah kedipan batas internal
        if (isCitySelected && cityValue !== null) {
            const formattedVal = window.formatNumberID(cityValue, 2);
            const popupText = `
                <div style="text-align: center; font-family: 'Inter', sans-serif;">
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 2px;">Kota</div>
                    <div style="font-weight: 700; font-size: 14px; color: #1e293b; margin-bottom: 6px;">PEMATANGSIANTAR</div>
                    <div style="font-weight: 800; color: #002D72; font-size: 16px; background: #f0f9ff; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                        ${formattedVal} <span style="font-size: 12px; font-weight: 600; color: #3b82f6;">${vis.unit}</span>
                    </div>
                </div>
            `;

            const cityTooltip = L.tooltip({ className: 'premium-map-tooltip', direction: 'top', offset: [0, -10], opacity: 1 })
                .setContent(popupText);

            let tooltipTimeout;

            layer.on('mouseover', function (e) {
                clearTimeout(tooltipTimeout);
                if (!map.hasLayer(cityTooltip)) {
                    cityTooltip.setLatLng(e.latlng).addTo(map);
                }
            });
            layer.on('mousemove', function (e) {
                if (map.hasLayer(cityTooltip)) {
                    cityTooltip.setLatLng(e.latlng);
                }
            });
            layer.on('mouseout', function (e) {
                tooltipTimeout = setTimeout(() => {
                    if (map.hasLayer(cityTooltip)) {
                        map.removeLayer(cityTooltip);
                    }
                }, 50);
            });
        }

        // Legenda lima kelas warna (sama lebar antara nilai kecamatan terendah dan tertinggi tahun ini),
        // agar perbedaan antarkecamatan bisa dibaca tanpa menunjuk satu per satu.
        let legenda = null;
        if (!isCitySelected && allVals.length >= 2 && range > 0) {
            legenda = L.control({ position: 'bottomright' });
            legenda.onAdd = () => {
                const div = L.DomUtil.create('div', 'legend');
                div.style.fontSize = '12px';
                L.DomUtil.create('div', 'legend-title', div).textContent = vis.unit || 'Nilai';
                const desimal = range < 10 ? 2 : 0;
                [0.8, 0.6, 0.4, 0.2, 0].forEach((batas, i) => {
                    const bawah = min + range * batas;
                    const atas = i === 0 ? max : min + range * (batas + 0.2);
                    const baris = L.DomUtil.create('div', '', div);
                    baris.style.clear = 'both';
                    L.DomUtil.create('i', '', baris).style.background = getColor(bawah + range * 0.1);
                    baris.appendChild(document.createTextNode(`${window.formatNumberID(bawah, desimal)} – ${window.formatNumberID(atas, desimal)}`));
                });
                return div;
            };
            legenda.addTo(map);
        }

        setTimeout(() => {
            if (map) {
                map.invalidateSize();
                map.fitBounds(layer.getBounds(), { padding: [20, 20] });
            }
        }, 150);
        this.instances[elementId] = { type: 'map', instance: map, legend: legenda };
    },

    createValueCard: function (vis, elementId) {
        const container = document.getElementById(elementId);
        if (!container) return;

        const config = vis.visualization_config;
        let data = this.dataLengkap(vis);
        const activeFilters = vis.active_filters || {};

        // 1. SATU TAHUN: pilihan filter Tahun, atau tahun terbaru selama filter Tahun masih "Semua"
        const kolomTahun = this.kolomTahun(vis);
        const tahun = kolomTahun ? this.tahunTampil(vis) : null;
        if (kolomTahun) {
            data = data.filter(item => item[kolomTahun] == tahun);
            this.aturLabel(elementId, this.labelTahun(vis));
        }

        Object.keys(activeFilters).forEach(col => {
            const val = activeFilters[col];
            if (val && col !== kolomTahun) {
                data = data.filter(item => String(item[col]).toLowerCase().trim() === String(val).toLowerCase().trim());
            }
        });

        const valCol = config.val_column || 'Nilai';

        // 1b. PENGAMAN BULAN (DIPERBAIKI)
        const hasBulanCol = vis.filters ? Object.keys(vis.filters).find(k => k.toLowerCase() === 'bulan') : null;
        const selectedBulanCard = hasBulanCol ? (activeFilters[hasBulanCol] || '') : '';

        if (hasBulanCol && !selectedBulanCard) {
            // Cek apakah ada baris "Tahunan" atau "Total" yang bisa dipakai sebagai fallback "Semua"
            const hasSummaryRow = data.some(item => {
                const v = String(item[hasBulanCol]).toLowerCase().trim();
                return v === 'tahunan' || v === 'total' || v === 'jumlah';
            });

            if (!hasSummaryRow) {
                container.innerHTML = `
                    <div class="bg-blue-50 text-blue-700 border border-blue-200 rounded-lg p-6 w-full h-full flex flex-col justify-center items-center text-center min-h-[150px]">
                        <i class="fas fa-calendar-check fa-2x text-blue-400 mb-3"></i>
                        <p class="text-sm font-medium">Silakan pilih spesifik <b>Bulan</b> terlebih dahulu untuk melihat angka.</p>
                    </div>
                `;
                return;
            }
        }

        // --- 🌟 PENGAMAN V6: WAJIBKAN FILTER KHUSUS TRIWULAN ---
        if (vis.filters) {
            let missingCat = null;
            const isTriwulan = (vis.name || '').toLowerCase().includes('triwulan');

            Object.keys(vis.filters).forEach(k => {
                if (k !== kolomTahun && (!activeFilters[k] || activeFilters[k] === '')) {
                    if (k.toLowerCase().includes('triwulan') || (isTriwulan && k.toLowerCase() === 'kategori')) {
                        missingCat = k;
                    }
                }
            });

            if (missingCat) {
                container.innerHTML = `
                    <div class="bg-blue-50 text-blue-700 border border-blue-200 rounded-lg p-6 w-full h-full flex flex-col justify-center items-center text-center min-h-[150px]">
                        <i class="fas fa-layer-group fa-2x text-blue-400 mb-3"></i>
                        <p class="text-sm font-medium">Silakan pilih spesifik <b>${missingCat}</b> terlebih dahulu untuk melihat angka.</p>
                    </div>
                `;
                return;
            }
        }

        // 2. PENGAMAN KEDUA: GUNAKAN BARIS JUMLAH/TAHUNAN JIKA ADA / CEGAH SUM GANDA
        if (data.length > 1) {
            const unitLower = (vis.unit || '').toLowerCase();
            const isRateOrIndex = unitLower.includes('%') || unitLower.includes('persen') || unitLower.includes('indeks') || unitLower.includes('tingkat') || unitLower.includes('rasio') || unitLower.includes('laju');

            // --- 🌟 SOLUSI CERDAS: Cari Baris Grand Total Sejati ---
            const totalRow = data.find(item => {
                let hasAggregate = false;
                let allUnfilteredAreAggregate = true;

                Object.entries(item).forEach(([key, v]) => {
                    if (key !== valCol && key !== kolomTahun && key !== 'id') {
                        if (vis.filters && vis.filters[key] && (!activeFilters[key] || activeFilters[key] === '')) {
                            const s = String(v).toLowerCase().trim();
                            const isAgg = s === 'total' || s === 'jumlah' || s === 'tahunan' || s === 'pdrb' || s === 'produk domestik regional bruto' || s === 'pematangsiantar' || s === 'kota pematangsiantar' || s.startsWith('jumlah ') || s.startsWith('total ');
                            if (isAgg) {
                                hasAggregate = true;
                            } else {
                                allUnfilteredAreAggregate = false;
                            }
                        }
                    }
                });
                return hasAggregate && allUnfilteredAreAggregate;
            });

            // JIKA KITA MENEMUKAN BARIS "JUMLAH" ATAU "TAHUNAN", TAMPILKAN BARIS ITU SAJA!
            if (totalRow) {
                let displayValue = parseFloat(totalRow[valCol]) || totalRow[valCol] || 0;
                let formattedNumber = typeof displayValue === 'number'
                    ? window.formatNumberID(displayValue, 2)
                    : displayValue;

                let activeFilterNames = [];
                Object.keys(activeFilters).forEach(k => {
                    if (activeFilters[k] && activeFilters[k] !== '' && k !== kolomTahun && k.toLowerCase() !== 'bulan') {
                        activeFilterNames.push(activeFilters[k]);
                    }
                });
                let labelInfo = activeFilterNames.length > 0 ? activeFilterNames.join(' - ') : 'Data Tahunan / Total';
                labelInfo += tahun ? ` (${tahun})` : '';

                container.innerHTML = `
                    <div class="bg-gradient-to-br from-[#EEF5FC] to-white border border-blue-100 rounded-xl p-6 w-full flex flex-col justify-center items-center min-h-[150px]">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 text-center">${labelInfo}</span>
                        <span class="text-4xl font-extrabold text-[#002D72] tracking-tight">${formattedNumber}</span>
                        ${vis.unit ? `<span class="text-xs font-semibold text-[#0B6FB8] mt-2 bg-blue-50 px-3 py-1 rounded-full border border-blue-100">${vis.unit}</span>` : ''}
                    </div>
                `;
                return;
            }

            // --- JIKA BARIS TOTAL TIDAK ADA DI DATA ---
            if (isRateOrIndex) {
                container.innerHTML = `
                    <div class="bg-blue-50 text-blue-700 border border-blue-200 rounded-lg p-6 w-full h-full flex flex-col justify-center items-center text-center min-h-[150px]">
                        <i class="fas fa-list fa-2x text-blue-400 mb-3"></i>
                        <p class="text-sm font-medium">Pilih spesifik filter untuk melihat angka.<br>
                        <span class="text-xs text-red-400 mt-1 block">(Data persentase/indeks tidak dapat dijumlahkan)</span></p>
                    </div>
                `;
                return;
            }

            // Lakukan SUM manual HANYA JIKA tidak ada baris Total/Tahunan dari BPS dan datanya bukan Persentase
            let sumTotal = 0;
            let validRowsCount = 0;

            data.forEach(item => {
                let isAggregateRow = false;

                Object.keys(item).forEach(key => {
                    if (key !== valCol && key !== kolomTahun) {
                        if (vis.filters && vis.filters[key] && (!activeFilters[key] || activeFilters[key] === '')) {
                            const valStr = String(item[key]).toLowerCase().trim();
                            // DITAMBAHKAN: 'tahunan'
                            if (valStr === 'total' || valStr === 'jumlah' || valStr === 'tahunan' || valStr === 'pdrb' || valStr === 'produk domestik regional bruto' || valStr === 'pematangsiantar' || valStr === 'kota pematangsiantar' || valStr.startsWith('jumlah ') || valStr.startsWith('total ')) {
                                isAggregateRow = true;
                            }
                        }
                    }
                });

                if (!isAggregateRow) {
                    const val = parseFloat(item[valCol]);
                    if (!isNaN(val)) {
                        sumTotal += val;
                        validRowsCount++;
                    }
                }
            });

            if (validRowsCount > 0) {
                let formattedNumber = window.formatNumberID(sumTotal, 2);

                let activeFilterNames = [];
                Object.keys(activeFilters).forEach(k => {
                    if (activeFilters[k] && activeFilters[k] !== '' && k !== kolomTahun && k.toLowerCase() !== 'bulan') {
                        activeFilterNames.push(activeFilters[k]);
                    }
                });
                let labelInfo = activeFilterNames.length > 0 ? activeFilterNames.join(' - ') : 'Total Keseluruhan';
                labelInfo += tahun ? ` (${tahun})` : '';

                container.innerHTML = `
                    <div class="bg-gradient-to-br from-[#EEF5FC] to-white border border-blue-100 rounded-xl p-6 w-full flex flex-col justify-center items-center min-h-[150px]">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 text-center">${labelInfo}</span>
                        <span class="text-4xl font-extrabold text-[#002D72] tracking-tight">${formattedNumber}</span>
                        ${vis.unit ? `<span class="text-xs font-semibold text-[#0B6FB8] mt-2 bg-blue-50 px-3 py-1 rounded-full border border-blue-100">${vis.unit}</span>` : ''}
                    </div>
                `;
                return;
            } else {
                container.innerHTML = `
                    <div class="bg-blue-50 text-blue-700 border border-blue-200 rounded-lg p-6 w-full h-full flex flex-col justify-center items-center text-center min-h-[150px]">
                        <p class="text-sm font-medium">Tidak ada data untuk dijumlahkan.</p>
                    </div>
                `;
                return;
            }
        }

        // 3. JIKA DATA KOSONG
        if (data.length === 0) {
            container.innerHTML = `<div class="bg-blue-50 text-blue-700 border border-blue-200 rounded-lg p-6 w-full h-full flex flex-col justify-center items-center text-center min-h-[150px]"><p class="text-sm font-medium">Tidak ada data untuk filter ini.</p></div>`;
            return;
        }

        // 4. JIKA LOLOS PENGAMAN (Data valid = persis 1 baris)
        const row = data[0];
        let displayValue = parseFloat(row[valCol]) || row[valCol] || 0;

        let parts = [];
        Object.keys(row).forEach(k => {
            if (k !== valCol && k !== 'id' && k !== kolomTahun && row[k]) {
                parts.push(row[k]);
            }
        });
        let labelInfo = parts.join(' - ') + (tahun ? ` (${tahun})` : '');

        let formattedNumber = typeof displayValue === 'number'
            ? window.formatNumberID(displayValue, 2)
            : displayValue;

        container.innerHTML = `
            <div class="bg-gradient-to-br from-[#EEF5FC] to-white border border-blue-100 rounded-xl p-6 w-full flex flex-col justify-center items-center min-h-[150px]">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 text-center">${labelInfo || vis.name}</span>
                <span class="text-4xl font-extrabold text-[#002D72] tracking-tight">${formattedNumber}</span>
                ${vis.unit ? `<span class="text-xs font-semibold text-[#0B6FB8] mt-2 bg-blue-50 px-3 py-1 rounded-full border border-blue-100">${vis.unit}</span>` : ''}
            </div>
        `;
    },

    createPyramidChart: function (vis, elementId) {
        const ctx = document.getElementById(elementId);
        if (!ctx) return;
        const config = vis.visualization_config;

        let data = this.dataLengkap(vis);

        // --- 1. SATU TAHUN: pilihan filter Tahun, atau tahun terbaru selama filter Tahun masih "Semua" ---
        const kolomTahun = this.kolomTahun(vis);
        if (kolomTahun) {
            const tahun = this.tahunTampil(vis);
            data = data.filter(item => item[kolomTahun] == tahun);
            this.aturLabel(elementId, this.labelTahun(vis));
        }
        const ageCol = config.age_column;
        if (!ageCol) return;

        // Mengurutkan label umur dengan cerdas (0-4, 5-9 ... 75+)
        let ageLabels = [...new Set(data.map(i => i[ageCol]))].sort((a, b) => {
            const numA = parseInt(String(a).match(/\d+/)?.[0] || 0);
            const numB = parseInt(String(b).match(/\d+/)?.[0] || 0);
            return numA - numB;
        });

        // Membersihkan label Total yang ikut masuk ke Kolom Umur
        ageLabels = ageLabels.filter(lbl => {
            const s = String(lbl).toLowerCase();
            return !s.includes('jumlah') && !s.includes('total') && !s.includes('tidak diketahui');
        });

        // Membalik urutan agar kelompok umur tertua berada di paling atas piramida
        ageLabels.reverse();

        const activeFilters = vis.active_filters || {};

        // Cari kolom Gender (kolom kategorikal selain Umur dan Tahun)
        let genderCol = null;
        if (vis.filters) {
            genderCol = Object.keys(vis.filters).find(k => k !== ageCol && k !== kolomTahun);
        }

        let datasets = [];
        const yCol = config.y_axis_numeric[0];

        // Highlight logic for selected age
        const selectedAge = activeFilters[ageCol] ? String(activeFilters[ageCol]).toLowerCase() : null;
        const getColors = (baseColor, dimColor) => ageLabels.map(age => {
            if (!selectedAge || selectedAge === 'semua' || selectedAge === '') return baseColor;
            return String(age).toLowerCase() === selectedAge ? baseColor : dimColor;
        });

        if (genderCol) {
            const selectedGender = activeFilters[genderCol] ? String(activeFilters[genderCol]).toLowerCase() : 'semua';

            if (selectedGender === 'semua' || selectedGender === '') {
                // PIRAMIDA UTUH: Perempuan (Kiri) vs Laki-laki (Kanan)
                const femaleData = ageLabels.map(age => {
                    const row = data.find(i => i[ageCol] == age && String(i[genderCol]).toLowerCase().includes('perempuan') && !String(i[genderCol]).toLowerCase().includes('+'));
                    return row ? -(row[yCol] || 0) : 0; // Minus untuk Perempuan di sisi kiri
                });
                const maleData = ageLabels.map(age => {
                    const row = data.find(i => i[ageCol] == age && String(i[genderCol]).toLowerCase().includes('laki') && !String(i[genderCol]).toLowerCase().includes('+'));
                    return row ? (row[yCol] || 0) : 0; // Positif untuk Laki-laki di sisi kanan
                });

                datasets = [
                    { label: 'Perempuan', data: femaleData, backgroundColor: getColors('#E8577D', '#FBE1E8') },
                    { label: 'Laki-Laki', data: maleData, backgroundColor: getColors('#0B6FB8', '#D7E7F5') }
                ];
            } else {
                // PIRAMIDA SETENGAH (Bar Horizontal Khusus)
                const isTotal = selectedGender.includes('total') || selectedGender.includes('jumlah') || selectedGender.includes('+');
                const halfData = ageLabels.map(age => {
                    const row = data.find(i => i[ageCol] == age && String(i[genderCol]).toLowerCase() === selectedGender);
                    return row ? (row[yCol] || 0) : 0;
                });

                const baseColor = isTotal ? '#4C9A2A' : (selectedGender.includes('laki') && !selectedGender.includes('+') ? '#0B6FB8' : '#E8577D');
                const dimColor = isTotal ? '#DDEFD5' : (selectedGender.includes('laki') && !selectedGender.includes('+') ? '#D7E7F5' : '#FBE1E8');

                datasets = [{
                    label: activeFilters[genderCol],
                    data: halfData,
                    backgroundColor: getColors(baseColor, dimColor)
                }];
            }
        } else {
            // Default jika data hanya total
            const halfData = ageLabels.map(age => {
                const row = data.find(i => i[ageCol] == age);
                return row ? (row[yCol] || 0) : 0;
            });
            datasets = [{ label: 'Jumlah Penduduk', data: halfData, backgroundColor: getColors('#4C9A2A', '#DDEFD5') }];
        }

        const chart = new Chart(ctx, {
            type: 'bar',
            data: { labels: ageLabels, datasets: datasets },
            options: {
                indexAxis: 'y', // Memutar sumbu jadi horizontal (Kunci utama Piramida)
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { left: 10, right: 10 } },
                plugins: {
                    legend: { position: 'bottom' },
                    datalabels: {
                        display: true,
                        color: '#fff',
                        font: { weight: 'bold', size: 10 },
                        formatter: (value) => {
                            if (value === 0 || value === null) return '';
                            // Memutlakkan nilai negatif agar tampil normal
                            return window.formatNumberID(Math.abs(value));
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                return ctx.dataset.label + ': ' + window.formatNumberID(Math.abs(ctx.raw));
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        ticks: {
                            callback: (value) => window.formatNumberID(Math.abs(value))
                        }
                    },
                    y: { stacked: true }
                }
            }
        });
        this.instances[elementId] = { type: 'chart', instance: chart };
    },
    // ====================================================================
    // TABEL PERBANDINGAN (HEATMAP): SEMUA KECAMATAN/KATEGORI x SEMUA TAHUN
    // ====================================================================
    // Tiap sel berisi angka dan diwarnai biru muda -> biru tua menurut besarnya (satu skala untuk seluruh
    // tabel), sehingga perbedaan antarkecamatan (baris) dan antartahun (kolom) terlihat sekaligus. Baris total
    // (mis. Kota Pematangsiantar) di bawah tanpa warna agar tidak menenggelamkan skala kecamatan. Pilihan
    // filter menyorot baris/kolomnya tanpa menyembunyikan yang lain.
    createHeatmap: function (vis, elementId) {
        const wadah = document.getElementById(elementId);
        const dimensi = this.dimensiPerbandingan(vis);
        const kolomTahun = this.kolomTahun(vis);
        if (!wadah || !dimensi || !kolomTahun) return;

        this.pasangGayaHeatmap();
        wadah.textContent = '';

        const kolomNilai = this.kolomNilai(vis);
        const { data, kunci } = this.dataPerbandingan(vis, dimensi);
        const daftarTahun = [...new Set(data.map(r => r[kolomTahun]).filter(t => t !== null && t !== undefined && t !== '').map(String))].sort();
        const nilai = new Map();
        data.forEach(r => {
            const v = parseFloat(r[kolomNilai]);
            if (!isNaN(v)) nilai.set(`${r[dimensi.kolom]}\u0000${r[kolomTahun]}`, v);
        });
        const ambil = (anggota, tahun) => nilai.get(`${anggota}\u0000${tahun}`);

        const angkaRinci = dimensi.anggota.flatMap(a => daftarTahun.map(t => ambil(a, t))).filter(v => v !== undefined);
        if (!angkaRinci.length) {
            wadah.textContent = 'Tidak ada angka untuk dibandingkan pada pilihan ini.';
            return;
        }
        const min = Math.min(...angkaRinci);
        const max = Math.max(...angkaRinci);
        const satuan = vis.unit ? ` ${vis.unit}` : '';
        const ramp = this.rampBiru;
        const warnaSel = v => {
            const i = max === min ? Math.floor(ramp.length / 2) : Math.round((v - min) / (max - min) * (ramp.length - 1));
            return { latar: ramp[i], teks: i >= 7 ? '#FFFFFF' : '#0F172A' };
        };

        const pilihan = vis.active_filters || {};
        const anggotaPilih = pilihan[dimensi.kolom] ? String(pilihan[dimensi.kolom]) : '';
        const tahunPilih = vis.selected_year ? String(vis.selected_year) : '';

        const gulir = document.createElement('div');
        gulir.className = 'pranata-heatmap';
        const tabel = document.createElement('table');
        const keterangan = document.createElement('caption');
        keterangan.textContent = `${vis.name}: ${dimensi.kolom} menurut tahun`;
        tabel.appendChild(keterangan);

        const kepala = tabel.createTHead().insertRow();
        const sudut = document.createElement('th');
        sudut.scope = 'col';
        sudut.textContent = dimensi.kolom;
        kepala.appendChild(sudut);
        daftarTahun.forEach(t => {
            const th = document.createElement('th');
            th.scope = 'col';
            th.textContent = t;
            if (t === tahunPilih) th.classList.add('dipilih');
            kepala.appendChild(th);
        });

        const badan = tabel.createTBody();
        const barisTabel = [...dimensi.anggota.map(a => ({ anggota: a, agregat: false }))];
        if (dimensi.agregat !== null) barisTabel.push({ anggota: dimensi.agregat, agregat: true });

        barisTabel.forEach(({ anggota, agregat }) => {
            const tr = badan.insertRow();
            if (agregat) tr.className = 'agregat';
            const barisDipilih = !anggotaPilih || String(anggota) === anggotaPilih;

            const th = document.createElement('th');
            th.scope = 'row';
            th.textContent = anggota;
            th.title = anggota;
            if (anggotaPilih && barisDipilih) th.classList.add('dipilih');
            else if (!barisDipilih) th.classList.add('redup');
            tr.appendChild(th);

            daftarTahun.forEach(t => {
                const td = tr.insertCell();
                const v = ambil(anggota, t);
                if (v === undefined) {
                    td.className = 'kosong';
                    td.textContent = '–';
                } else {
                    td.textContent = window.formatNumberID(v, 2);
                    td.title = `${anggota} · ${t}: ${window.formatNumberID(v)}${satuan}`;
                    td.dataset.nilai = v;
                    if (!agregat) {
                        const w = warnaSel(v);
                        td.style.background = w.latar;
                        td.style.color = w.teks;
                    }
                }
                if (!barisDipilih || (tahunPilih && t !== tahunPilih)) td.classList.add('redup');
            });
        });

        gulir.appendChild(tabel);
        wadah.appendChild(gulir);

        // Tabel lebih lebar dari kartu (banyak tahun): mulai dari tahun terbaru (atau tahun yang dipilih),
        // tahun sebelumnya dilihat dengan menggeser. Kolom nama tetap terlihat saat digeser.
        if (gulir.scrollWidth > gulir.clientWidth + 1) {
            const kolomTuju = tahunPilih ? [...kepala.cells].find(c => c.textContent === tahunPilih) : null;
            gulir.scrollLeft = kolomTuju
                ? Math.max(0, kolomTuju.offsetLeft - gulir.clientWidth / 2)
                : gulir.scrollWidth;
            const geser = document.createElement('p');
            geser.className = 'pranata-heatmap-geser';
            geser.textContent = '← Geser tabel ke kiri untuk melihat tahun-tahun sebelumnya.';
            wadah.appendChild(geser);
        }

        // Legenda skala warna + angka terendah/tertinggi.
        const legenda = document.createElement('div');
        legenda.className = 'pranata-heatmap-legenda';
        const rendah = document.createElement('span');
        rendah.textContent = `Rendah ${window.formatNumberID(min, 2)}`;
        const batang = document.createElement('span');
        batang.className = 'pranata-heatmap-gradasi';
        batang.style.background = `linear-gradient(to right, ${ramp[0]}, ${ramp[4]}, ${ramp[8]}, ${ramp[ramp.length - 1]})`;
        const tinggi = document.createElement('span');
        tinggi.textContent = `${window.formatNumberID(max, 2)}${satuan} Tinggi`;
        legenda.append(rendah, batang, tinggi);
        if (dimensi.agregat !== null) {
            const catatanTotal = document.createElement('span');
            catatanTotal.className = 'pranata-heatmap-catatan';
            catatanTotal.textContent = `Baris "${dimensi.agregat}" adalah angka keseluruhan, tidak ikut diwarnai.`;
            legenda.appendChild(catatanTotal);
        }
        wadah.appendChild(legenda);

        // Keterangan judul: ukuran tabel, cara membaca, dan kolom lain yang dikunci (mis. Kategori: Jumlah).
        const dikunci = kunci.map(k => `${k.kolom}: ${k.nilai}`);
        this.aturLabel(elementId, `${dimensi.anggota.length} ${dimensi.kolom} × ${daftarTahun.length} tahun`,
            'Warna makin gelap = nilai makin besar. Arahkan kursor ke sel untuk melihat angka lengkapnya.'
            + (dikunci.length ? ` Menampilkan ${dikunci.join(', ')} (ubah lewat filter di atas).` : ''));
    },

    // Gaya tabel perbandingan, dipasang sekali per halaman (tidak bergantung pada hasil build CSS).
    pasangGayaHeatmap: function () {
        if (document.getElementById('pranata-heatmap-gaya')) return;
        const gaya = document.createElement('style');
        gaya.id = 'pranata-heatmap-gaya';
        gaya.textContent = `
            .pranata-heatmap { overflow-x: auto; margin-top: 12px; }
            .pranata-heatmap table { border-collapse: separate; border-spacing: 2px; width: 100%; font-size: 12px; font-variant-numeric: tabular-nums; }
            .pranata-heatmap caption { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
            .pranata-heatmap th { font-weight: 600; color: #475569; padding: 5px 6px; white-space: nowrap; background: #FFFFFF; }
            .pranata-heatmap thead th { text-align: right; }
            .pranata-heatmap th:first-child { text-align: left; position: sticky; left: 0; z-index: 1; color: #1F2937; box-shadow: 6px 0 6px -6px rgba(15, 23, 42, .25); max-width: 240px; overflow: hidden; text-overflow: ellipsis; }
            .pranata-heatmap td { padding: 5px 6px; text-align: right; white-space: nowrap; border-radius: 4px; transition: opacity .15s; }
            .pranata-heatmap td[data-nilai]:hover { outline: 2px solid #0F172A; outline-offset: -2px; }
            .pranata-heatmap td.kosong { background: #F8FAFC; color: #94A3B8; text-align: center; }
            .pranata-heatmap tr.agregat th, .pranata-heatmap tr.agregat td { font-weight: 700; }
            .pranata-heatmap tr.agregat td { background: #F1F5F9; color: #0F172A; }
            .pranata-heatmap td.redup { opacity: .35; }
            .pranata-heatmap th.redup { color: #94A3B8; }
            .pranata-heatmap th.dipilih { color: #002D72; text-decoration: underline; text-underline-offset: 3px; }
            .pranata-heatmap-legenda { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; margin-top: 12px; font-size: 12px; color: #475569; }
            .pranata-heatmap-gradasi { display: inline-block; width: 140px; height: 10px; border-radius: 999px; }
            .pranata-heatmap-catatan { color: #64748B; }
            .pranata-heatmap-geser { color: #64748B; font-size: 12px; margin-top: 6px; }
        `;
        document.head.appendChild(gaya);
    },

    normalizeKecamatan: function (name) {
        if (!name) return null;
        name = name.toUpperCase().trim().replace(/ /g, '');
        // PERBAIKAN: Hapus kata KOTA agar KOTAPEMATANGSIANTAR menjadi PEMATANGSIANTAR
        name = name.replace('KOTA', '');

        const mapping = {
            'SIANTARMARIHAT': 'SIANTARMARIHAT',
            'SIANTARMARIBUN': 'SIANTARMARIMBUN',
            'SIANTARMARIMBUN': 'SIANTARMARIMBUN',
            'SIANTARSELATAN': 'SIANTARSELATAN',
            'SIANTARBARAT': 'SIANTARBARAT',
            'SIANTARUTARA': 'SIANTARUTARA',
            'SIANTARTIMUR': 'SIANTARTIMUR',
            'SIANTARMARTOBA': 'SIANTARMARTOBA',
            'SIANTARSITALASARI': 'SIANTARSITALASARI',
            'PEMATANGSIANTAR': 'PEMATANGSIANTAR'
        };
        return mapping[name] || name;
    }
};
