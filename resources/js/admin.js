// File utama Javascript untuk menangani interaktivitas (dropdown, modal, dll) di halaman Admin.

// ==========================================
// resources/js/admin.js
// ==========================================

import Alpine from 'alpinejs';

// 1. Inisialisasi Lucide Icons
// Menggunakan CDN, dipanggil di file blade layout.
window.toggleDropdown = function (dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    const arrowId = dropdownId.replace('-dropdown', '-arrow');
    const arrow = document.getElementById(arrowId);
    const isHidden = dropdown.classList.contains('hidden');

    if (isHidden) {
        dropdown.classList.remove('hidden');
        if (arrow) arrow.classList.add('rotate-180');
    } else {
        dropdown.classList.add('hidden');
        if (arrow) arrow.classList.remove('rotate-180');
    }
}

// ==========================================
// 3. Helper Format Angka - Koma sebagai Desimal
// ==========================================
/**
 * Format angka manual ke format Indonesia (titik ribuan, koma desimal).
 * TIDAK menggunakan Intl.NumberFormat atau toLocaleString agar konsisten
 * di semua browser (Chrome, Edge, Firefox, dll).
 * Contoh: 1234567 → "1.234.567", 1234.56 → "1.234,56"
 */
window.formatNumberID = function (value, maximumFractionDigits) {
    if (value === null || value === undefined || isNaN(value)) return String(value);
    const num = Number(value);
    const isNeg = num < 0;
    const absNum = Math.abs(num);

    let strNum;
    if (maximumFractionDigits !== undefined) {
        strNum = absNum.toFixed(maximumFractionDigits);
    } else {
        strNum = String(absNum);
    }

    // Pisahkan bagian integer dan desimal
    const parts = strNum.split('.');
    let intPart = parts[0];
    const decPart = parts[1];

    // Hapus trailing zeros pada desimal (mis. "2316,00" → "2.316")
    let finalDec = decPart;
    if (finalDec) {
        finalDec = finalDec.replace(/0+$/, '');
    }

    // Tambahkan titik sebagai pemisah ribuan
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    let result = intPart;
    if (finalDec && finalDec.length > 0) {
        result += ',' + finalDec;
    }

    return isNeg ? '-' + result : result;
};

/**
 * Memformat nilai angka:
 * - Desimal titik → koma: "81.9" → "81,9"
 * - Ribuan: "1234" → "1.234", "1234.56" → "1.234,56"
 * - Tahun (1900-2099) dilewati: "2022" tetap "2022"
 * - Teks label dikembalikan apa adanya
 */
window.formatCellNumber = function (value) {
    if (value === null || value === undefined) return value;
    const str = String(value).trim();
    if (str === '' || str === '-' || str === '—') return str;

    // Sudah format Indonesia (mis. "81,9" atau "1.234,56") → kembalikan apa adanya
    if (/^-?\d{1,3}(\.\d{3})*(,\d+)?$/.test(str)) return str;

    // Angka desimal dengan titik (mis. "81.9", "1234.56")
    if (/^-?\d+\.\d+$/.test(str)) {
        const parts = str.split('.');
        const intVal = parseInt(parts[0], 10);
        if (isNaN(intVal)) return str;
        // Gunakan formatNumberID manual untuk bagian integer
        return window.formatNumberID(intVal) + ',' + parts[1];
    }

    // Angka bulat (mis. "1234", "56789") → beri pemisah ribuan
    if (/^-?\d+$/.test(str)) {
        const num = parseInt(str, 10);
        if (isNaN(num)) return str;
        // Lewati tahun (1900-2099) agar tidak jadi "2.022"
        if (num >= 1900 && num <= 2099) return str;
        return window.formatNumberID(num);
    }

    // Bukan angka → kembalikan apa adanya
    return str;
};

/**
 * Mengecek apakah suatu nilai terlihat sebagai angka/data numerik.
 * Digunakan untuk menentukan alignment: angka → kanan, teks → tengah.
 */
window.isNumericCell = function (value) {
    if (value === null || value === undefined) return false;
    const str = String(value).trim();
    if (str === '' || str === '-' || str === '—') return false;
    // Format Indonesia: 1.234,56 atau 81,9
    if (/^-?\d{1,3}(\.\d{3})*(,\d+)?$/.test(str)) return true;
    // Format desimal titik: 81.9
    if (/^-?\d+\.\d+$/.test(str)) return true;
    // Angka bulat tapi BUKAN tahun → dianggap data numerik
    if (/^-?\d+$/.test(str)) {
        const num = parseInt(str, 10);
        if (num >= 1900 && num <= 2099) return false; // tahun = bukan data numerik
        return true;
    }
    return false;
};



// ==========================================
// 3. Logika Kelola Data
// ==========================================
window.kelolaData = function () {
    return {
        activeTab: 'categories',
        showCategoryModal: false,
        showSubjectModal: false,
        showIndicatorModal: false,
        showImportModal: false,
        excelFileName: null,
        selectedCategoryId: '',
        showEditCategoryModal: false,
        showEditSubjectModal: false,
        showEditIndicatorModal: false,
        editingCategory: { id: null, name: '' },
        editingSubject: { id: null, category_id: '', name: '' },

        // ✅ ACTIVE CELL (untuk highlight)
        activeCell: { tableRef: null, type: null, rowIndex: null, colIndex: null },
        setActiveCell(tableRef, type, rowIndex, colIndex) {
            this.activeCell = { tableRef, type, rowIndex, colIndex };
        },
        clearActiveCell() {
            this.activeCell = { tableRef: null, type: null, rowIndex: null, colIndex: null };
        },
        isActiveCell(tableRef, type, rowIndex, colIndex) {
            return this.activeCell.tableRef === tableRef &&
                this.activeCell.type === type &&
                this.activeCell.rowIndex === rowIndex &&
                this.activeCell.colIndex === colIndex;
        },

        // ✅ Cek apakah baris body adalah baris header bertingkat
        isHeaderRow(tableData, rowIndex) {
            if (!tableData || !tableData.headers) return false;
            let maxRowspan = 1;
            for (let h of tableData.headers) {
                if (!h.hidden && h.rowspan && h.rowspan > maxRowspan) {
                    maxRowspan = h.rowspan;
                }
            }
            let extraHeaderCount = Math.max(0, maxRowspan - 1);
            return rowIndex < extraHeaderCount;
        },

        indicatorFormData: {
            subject_id: '',
            name: '',
            unit: '',
            tableData: {
                headers: [{ value: '' }, { value: '' }, { value: '' }],
                rows: [
                    [{ value: 'Baris 1' }, { value: '' }, { value: '' }],
                    [{ value: 'Baris 2' }, { value: '' }, { value: '' }]
                ]
            }
        },
        editingIndicator: {
            bps_source: null,
            id: null,
            subject_id: '',
            name: '',
            unit: '',
            tableData: { headers: [], rows: [] }
        },
        addIndicatorColumn() {
            this.indicatorFormData.tableData.headers.push({ value: '' });
            this.indicatorFormData.tableData.rows.forEach(row => { row.push({ value: '' }); });
        },
        removeIndicatorColumn(index) {
            if (this.indicatorFormData.tableData.headers.length <= 1) return;
            const table = JSON.parse(JSON.stringify(this.indicatorFormData.tableData));
            const allRows = [table.headers, ...table.rows];
            allRows.forEach(row => {
                let cell = row[index];
                if (cell.hidden) {
                    for (let c = index - 1; c >= 0; c--) {
                        if (!row[c].hidden && (row[c].colspan || 1) > (index - c)) {
                            row[c].colspan -= 1;
                            break;
                        }
                    }
                    row.splice(index, 1);
                } else if ((cell.colspan || 1) > 1) {
                    cell.colspan -= 1;
                    row.splice(index + 1, 1);
                } else {
                    row.splice(index, 1);
                }
            });
            this.indicatorFormData.tableKey = Date.now();
            this.indicatorFormData.tableData = table;
        },
        addIndicatorRow() {
            const newRow = this.indicatorFormData.tableData.headers.map(() => ({ value: '' }));
            this.indicatorFormData.tableData.rows.push(newRow);
        },
        removeIndicatorRow(index) {
            if (this.indicatorFormData.tableData.rows.length <= 1) return;
            this.indicatorFormData.tableData.rows.splice(index, 1);
        },
        addEditingColumn() {
            this.editingIndicator.tableData.headers.push({ value: '' });
            this.editingIndicator.tableData.rows.forEach(row => { row.push({ value: '' }); });
        },
        removeEditingColumn(index) {
            if (this.editingIndicator.tableData.headers.length <= 1) return;
            const table = JSON.parse(JSON.stringify(this.editingIndicator.tableData));
            const allRows = [table.headers, ...table.rows];
            allRows.forEach(row => {
                let cell = row[index];
                if (cell.hidden) {
                    for (let c = index - 1; c >= 0; c--) {
                        if (!row[c].hidden && (row[c].colspan || 1) > (index - c)) {
                            row[c].colspan -= 1;
                            break;
                        }
                    }
                    row.splice(index, 1);
                } else if ((cell.colspan || 1) > 1) {
                    cell.colspan -= 1;
                    row.splice(index + 1, 1);
                } else {
                    row.splice(index, 1);
                }
            });
            this.editingIndicator.tableKey = Date.now();
            this.editingIndicator.tableData = table;
        },
        addEditingRow() {
            const newRow = this.editingIndicator.tableData.headers.map(() => ({ value: '' }));
            this.editingIndicator.tableData.rows.push(newRow);
        },
        removeEditingRow(index) {
            if (this.editingIndicator.tableData.rows.length <= 1) return;
            this.editingIndicator.tableData.rows.splice(index, 1);
        },
        initEditIndicator(indicator) {
            const indicatorData = indicator.data || {};
            this.editingIndicator.id = indicator.id;
            this.editingIndicator.subject_id = indicator.subject_id;
            this.editingIndicator.name = indicator.name;
            this.editingIndicator.unit = indicator.unit || '';
            // Indikator tabel dinamis BPS: datanya dari API, editor tabel disembunyikan.
            this.editingIndicator.bps_source = indicator.bps_source || null;

            if (indicatorData.headers && indicatorData.rows) {
                this.editingIndicator.tableData = JSON.parse(JSON.stringify(indicatorData));
            } else {
                this.editingIndicator.tableData = {
                    headers: [{ value: 'Key' }, { value: 'Value' }],
                    rows: Object.entries(indicatorData).map(([key, value]) => ([{ value: key }, { value: String(value) }]))
                };
                if (this.editingIndicator.tableData.rows.length === 0) {
                    this.editingIndicator.tableData.rows.push([{ value: '' }, { value: '' }]);
                }
            }

            // Format nilai sel data (body) kolom 1+ dengan koma desimal — kolom 0 (label) dilewati
            try {
                if (this.editingIndicator.tableData.rows) {
                    this.editingIndicator.tableData.rows.forEach(row => {
                        if (!Array.isArray(row)) return;
                        row.forEach((cell, cellIndex) => {
                            if (cell && cell.value !== undefined) {
                                cell.value = window.formatCellNumber(cell.value);
                            }
                        });
                    });
                }
            } catch (e) {
                // Jika format gagal, tetap buka modal tanpa mengubah nilai
                console.warn('Format cell error:', e);
            }

            this.showEditIndicatorModal = true;
        },


        // ==========================================
        // FUNGSI MERGE
        // ==========================================
        _getTable(tableRef) {
            return tableRef === 'create'
                ? this.indicatorFormData.tableData
                : this.editingIndicator.tableData;
        },

        mergeRight(tableRef, type, rowIndex, colIndex) {
            const table = this._getTable(tableRef);
            const row = type === 'header' ? table.headers : table.rows[rowIndex];
            const cell = row[colIndex];
            const targetColIndex = colIndex + (cell.colspan || 1);

            if (targetColIndex >= row.length) {
                return alert('Tidak ada kolom di sebelah kanan untuk di-merge.');
            }
            const nextCell = row[targetColIndex];
            if (nextCell.hidden) {
                return alert('Sel di sebelah kanan sudah tergabung.');
            }
            nextCell.hidden = true;
            cell.colspan = (cell.colspan || 1) + (nextCell.colspan || 1);
        },

        unmergeRight(tableRef, type, rowIndex, colIndex) {
            const table = this._getTable(tableRef);
            const row = type === 'header' ? table.headers : table.rows[rowIndex];
            const cell = row[colIndex];

            if ((cell.colspan || 1) <= 1) {
                return alert('Sel ini belum di-merge ke kanan.');
            }
            cell.colspan -= 1;
            for (let i = colIndex + 1; i < row.length; i++) {
                if (row[i].hidden) {
                    row[i].hidden = false;
                    row[i].colspan = 1;
                    break;
                }
            }
        },

        mergeDown(tableRef, type, rowIndex, colIndex) {
            const table = this._getTable(tableRef);

            if (type === 'header') {
                const cell = table.headers[colIndex];
                if (!table.rows || table.rows.length === 0) {
                    return alert('Tidak ada baris data di bawah header.');
                }
                const targetRowIndex = (cell.rowspan || 1) - 1;
                if (targetRowIndex >= table.rows.length) {
                    return alert('Tidak ada baris di bawah untuk di-merge.');
                }
                const nextCell = table.rows[targetRowIndex][colIndex];
                if (!nextCell || nextCell.hidden) {
                    return alert('Sel di bawah sudah tergabung.');
                }
                if ((cell.colspan || 1) !== (nextCell.colspan || 1)) {
                    return alert('Lebar kolom (colspan) tidak sama. Samakan dulu sebelum merge ke bawah.');
                }
                nextCell.hidden = true;
                cell.rowspan = (cell.rowspan || 1) + (nextCell.rowspan || 1);
                return;
            }

            const rows = table.rows;
            const cell = rows[rowIndex][colIndex];
            const targetRowIndex = rowIndex + (cell.rowspan || 1);

            if (targetRowIndex >= rows.length) {
                return alert('Tidak ada baris di bawah untuk di-merge.');
            }
            const nextCell = rows[targetRowIndex][colIndex];
            if (nextCell.hidden) {
                return alert('Sel di bawah sudah tergabung.');
            }
            if ((cell.colspan || 1) !== (nextCell.colspan || 1)) {
                return alert('Lebar kolom (colspan) tidak sama. Samakan dulu sebelum merge ke bawah.');
            }
            nextCell.hidden = true;
            cell.rowspan = (cell.rowspan || 1) + (nextCell.rowspan || 1);
        },

        unmergeDown(tableRef, type, rowIndex, colIndex) {
            const table = this._getTable(tableRef);

            if (type === 'header') {
                const cell = table.headers[colIndex];
                if ((cell.rowspan || 1) <= 1) {
                    return alert('Sel ini belum di-merge ke bawah.');
                }
                cell.rowspan -= 1;
                const revealIndex = cell.rowspan - 1;
                const row = table.rows[revealIndex];
                if (row && row[colIndex]) {
                    row[colIndex].hidden = false;
                    row[colIndex].rowspan = 1;
                }
                return;
            }

            const rows = table.rows;
            const cell = rows[rowIndex][colIndex];

            if ((cell.rowspan || 1) <= 1) {
                return alert('Sel ini belum di-merge ke bawah.');
            }
            cell.rowspan -= 1;
            for (let i = rowIndex + 1; i < rows.length; i++) {
                if (rows[i][colIndex] && rows[i][colIndex].hidden) {
                    rows[i][colIndex].hidden = false;
                    rows[i][colIndex].rowspan = 1;
                    break;
                }
            }
        }
    };
}

// ==========================================
// 4. Logika untuk Halaman Manajemen Pengguna
// ==========================================
window.penggunaData = function (initialSearch, searchUrl) {
    return {
        showEditModal: false,
        showDeleteModal: false,
        showAddModal: false,
        selectedUser: null,
        selectedRole: null,
        userName: '',
        formEditAction: '',
        formDeleteAction: '',

        searchQuery: initialSearch,
        searchTimeout: null,
        searchUrl: searchUrl,

        doSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                window.location.href = this.searchUrl + '?search=' + encodeURIComponent(this.searchQuery);
            }, 500);
        }
    };
};

// ==========================================
// 5. LOGIKA DASHBOARD VISUALISASI & NARASI
// ==========================================
document.addEventListener('DOMContentLoaded', () => {

    // 1. Setting Default Chart.js
    if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
        Chart.register(ChartDataLabels);

        // Gaya dasar semua grafik dashboard: huruf Inter, garis bantu tipis, tooltip & legenda rapi.
        // Dibungkus try agar perbedaan versi Chart.js dari CDN tidak menghentikan dashboard.
        try {
            Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
            Chart.defaults.font.size = 12;
            Chart.defaults.color = '#475569';
            Chart.defaults.scale.grid.color = '#EEF2F7';
            Chart.defaults.scale.border.color = '#E2E8F0';
            Chart.defaults.set('scales.category', { grid: { display: false } });
            Chart.defaults.elements.bar.borderRadius = 6;
            Chart.defaults.elements.line.cubicInterpolationMode = 'monotone';
            Object.assign(Chart.defaults.plugins.legend.labels, {
                usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 16,
            });
            Object.assign(Chart.defaults.plugins.tooltip, {
                backgroundColor: 'rgba(15, 23, 42, 0.92)', titleColor: '#FFFFFF', bodyColor: '#E2E8F0',
                padding: 12, cornerRadius: 10, boxPadding: 6, usePointStyle: true,
                titleFont: { weight: '600' },
            });
        } catch (e) {
            console.warn('Gaya bawaan grafik tidak dapat diterapkan:', e);
        }

        Chart.defaults.plugins.datalabels.color = '#fff';
        Chart.defaults.plugins.datalabels.formatter = (value, ctx) => {
            let sum = 0;
            let dataArr = ctx.chart.data.datasets[0].data;
            dataArr.map(data => sum += data);
            return (sum > 0) ? (value * 100 / sum).toFixed(1) + "%" : "0%";
        };
    }

    // 2. Inisialisasi Dashboard Pertama Kali
    if (window.DashboardConfig) {
        window.dashboardApp.init();
    }

    // 3. JALANKAN FUNGSI AJAX FILTER
    setupAjaxFilter();
});


// FUNGSI AJAIB: MENGGANTI KONTEN TANPA RELOAD HALAMAN
function setupAjaxFilter() {
    const filterForm = document.getElementById('filterForm');

    if (filterForm) {
        // Mencegah reload jika menekan tombol Enter di form
        filterForm.addEventListener('submit', (e) => e.preventDefault());

        const selects = filterForm.querySelectorAll('select');
        selects.forEach(select => {

            // Hapus event bawaan bila ada
            select.removeAttribute('onchange');

            // Pasang event AJAX
            select.addEventListener('change', function (e) {
                e.preventDefault();

                const container = document.getElementById('dashboard-ajax-container');
                if (container) container.style.opacity = '0.4'; // Beri efek buram/loading

                // Siapkan data form dan URL
                const formData = new FormData(filterForm);
                const params = new URLSearchParams(formData).toString();
                const url = filterForm.action + '?' + params;

                // Ambil halaman baru di latar belakang
                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        // Ekstrak HTML halaman yang baru ditarik
                        const parser = new DOMParser();
                        const newDoc = parser.parseFromString(html, 'text/html');
                        const newContainer = newDoc.getElementById('dashboard-ajax-container');

                        if (newContainer && container) {
                            // A. Bersihkan (destroy) grafik lama agar memori browser tidak bocor
                            if (window.dashboardApp && window.dashboardApp.instances) {
                                for (let id in window.dashboardApp.instances) {
                                    let item = window.dashboardApp.instances[id];
                                    if (item.type === 'chart') item.instance.destroy();
                                    else if (item.type === 'map') item.instance.remove();
                                }
                                window.dashboardApp.instances = {};
                            }

                            // B. TIMPA HTML LAMA DENGAN YANG BARU (Ini kunci utamanya!)
                            container.innerHTML = newContainer.innerHTML;
                            container.style.opacity = '1'; // Kembalikan ke normal

                            // C. Update variabel data grafik dari script tag halaman baru
                            const scriptTags = newDoc.querySelectorAll('script');
                            scriptTags.forEach(script => {
                                if (script.innerHTML.includes('window.DashboardConfig')) {
                                    // Buat elemen tag script baru secara virtual
                                    const newScript = document.createElement('script');
                                    newScript.textContent = script.innerHTML;

                                    // Masukkan ke dalam halaman agar browser mengeksekusinya secara alami
                                    document.body.appendChild(newScript);

                                    // Hapus kembali tag script tersebut agar kode HTML tidak menumpuk/kotor
                                    document.body.removeChild(newScript);
                                }
                            });

                            // D. Nyalakan ulang aplikasi grafik dan pasang kembali event form
                            if (window.dashboardApp) window.dashboardApp.init();
                            setupAjaxFilter(); // Penting: Pasang lagi karena HTML form-nya baru
                        }
                    })
                    .catch(error => {
                        console.error('Terjadi kesalahan:', error);
                        if (container) container.style.opacity = '1';
                    });
            });
        });
    }
}

window.toggleEditor = function (id) {
    const viewMode = document.getElementById(`narrative-view-${id}`);
    const editMode = document.getElementById(`narrative-editor-container-${id}`);
    if (editMode.classList.contains('hidden')) {
        editMode.classList.remove('hidden');
        if (viewMode) viewMode.classList.add('hidden');
    } else {
        editMode.classList.add('hidden');
        const content = document.getElementById(`editor-${id}`).value;
        if (content.trim() !== '' && viewMode) viewMode.classList.remove('hidden');
    }
}

window.generateAiNarrative = function (indicatorId) {
    const btn = document.getElementById(`btn-generate-${indicatorId}`);
    const loading = document.getElementById(`loading-msg-${indicatorId}`);
    const editor = document.getElementById(`editor-${indicatorId}`);

    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-not-allowed');
    loading.classList.remove('hidden');

    fetch(window.DashboardConfig.routes.generateNarrative, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": window.DashboardConfig.csrfToken,
            "Accept": "application/json"
        },
        body: JSON.stringify({ indicator_id: indicatorId })
    })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                editor.value = data.narrative;
                // Sukses
                Swal.fire({
                    html: `
                        <div class="flex flex-col items-center px-2">
                            <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-green-50 text-[#10b981] ring-[6px] ring-green-50/50">
                                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2 tracking-tight">Draft Narasi Selesai!</h3>
                            <p class="text-base text-gray-500 mb-8">Silakan review sebelum menyimpan.</p>
                            <button type="button" onclick="Swal.close()" class="w-full inline-flex justify-center !rounded-[1.25rem] px-5 py-3.5 text-base font-bold shadow-sm hover:opacity-90 transition-all focus:outline-none focus:ring-4 focus:ring-green-100" style="background-color: #10b981; color: #ffffff; border: none;">
                                OK
                            </button>
                        </div>
                    `,
                    showConfirmButton: false,
                    width: '24rem',
                    padding: '2.5rem 1rem 2rem 1rem',
                    customClass: {
                        popup: '!rounded-[2.5rem] bg-white shadow-2xl border border-gray-100 font-sans', // Dipaksa sangat melengkung (40px)
                        htmlContainer: '!m-0 !p-0',
                    }
                });
            } else {
                // Gagal
                Swal.fire({
                    html: `
                        <div class="flex flex-col items-center px-2">
                            <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-red-50 text-[#ef4444] ring-[6px] ring-red-50/50">
                                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2 tracking-tight">Gagal!</h3>
                            <p class="text-base text-gray-500 mb-8 leading-relaxed">${data.error || 'Terjadi kesalahan pada AI.'}</p>
                            <button type="button" onclick="Swal.close()" class="w-full inline-flex justify-center !rounded-[1.25rem] px-5 py-3.5 text-base font-bold shadow-sm hover:opacity-90 transition-all focus:outline-none focus:ring-4 focus:ring-red-100" style="background-color: #ef4444; color: #ffffff; border: none;">
                                OK
                            </button>
                        </div>
                    `,
                    showConfirmButton: false,
                    width: '24rem',
                    padding: '2.5rem 1rem 2rem 1rem',
                    customClass: {
                        popup: '!rounded-[2.5rem] bg-white shadow-2xl border border-gray-100 font-sans',
                        htmlContainer: '!m-0 !p-0',
                    }
                });
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire({
                html: `
                    <div class="flex flex-col items-center px-2">
                        <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-red-50 text-[#ef4444] ring-[6px] ring-red-50/50">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2 tracking-tight">Kesalahan!</h3>
                        <p class="text-base text-gray-500 mb-8 leading-relaxed">Gagal menghubungi server AI.</p>
                        <button type="button" onclick="Swal.close()" class="w-full inline-flex justify-center !rounded-[1.25rem] px-5 py-3.5 text-base font-bold shadow-sm hover:opacity-90 transition-all focus:outline-none focus:ring-4 focus:ring-red-100" style="background-color: #ef4444; color: #ffffff; border: none;">
                            OK
                        </button>
                    </div>
                `,
                showConfirmButton: false,
                width: '24rem',
                padding: '2.5rem 1rem 2rem 1rem',
                customClass: {
                    popup: '!rounded-[2.5rem] bg-white shadow-2xl border border-gray-100 font-sans',
                    htmlContainer: '!m-0 !p-0',
                }
            });
        })
        .finally(() => {
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
            loading.classList.add('hidden');
        });
}

window.saveNarrative = function (indicatorId) {
    const editor = document.getElementById(`editor-${indicatorId}`);
    const btnSave = document.getElementById(`btn-save-${indicatorId}`);
    const narrativeText = editor.value;

    if (!narrativeText.trim()) {
        Swal.fire({
            html: `
                <div class="flex flex-col items-center px-2">
                    <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-amber-50 text-[#f59e0b] ring-[6px] ring-amber-50/50">
                        <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2 tracking-tight">Peringatan!</h3>
                    <p class="text-base text-gray-500 mb-8">Narasi tidak boleh kosong.</p>
                    <button type="button" onclick="Swal.close()" class="w-full inline-flex justify-center !rounded-[1.25rem] px-5 py-3.5 text-base font-bold shadow-sm hover:opacity-90 transition-all focus:outline-none focus:ring-4 focus:ring-amber-100" style="background-color: #f59e0b; color: #ffffff; border: none;">
                        OK
                    </button>
                </div>
            `,
            showConfirmButton: false,
            width: '24rem',
            padding: '2.5rem 1rem 2rem 1rem',
            customClass: {
                popup: '!rounded-[2.5rem] bg-white shadow-2xl border border-gray-100 font-sans',
                htmlContainer: '!m-0 !p-0',
            }
        });
        return;
    }

    const originalText = btnSave.innerHTML;
    btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    btnSave.disabled = true;

    fetch(window.DashboardConfig.routes.saveNarrative, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": window.DashboardConfig.csrfToken,
            "Accept": "application/json"
        },
        body: JSON.stringify({ indicator_id: indicatorId, narrative: narrativeText })
    })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const viewText = document.getElementById(`narrative-text-${indicatorId}`);
                const viewContainer = document.getElementById(`narrative-view-${indicatorId}`);
                if (viewText) viewText.innerText = narrativeText;
                if (viewContainer) viewContainer.classList.remove('hidden');

                toggleEditor(indicatorId);

                Swal.fire({
                    html: `
                        <div class="flex flex-col items-center px-2">
                            <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-green-50 text-[#10b981] ring-[6px] ring-green-50/50">
                                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2 tracking-tight">Tersimpan!</h3>
                            <p class="text-base text-gray-500 mb-8">Narasi berhasil diperbarui.</p>
                            <button type="button" onclick="Swal.close()" class="w-full inline-flex justify-center !rounded-[1.25rem] px-5 py-3.5 text-base font-bold shadow-sm hover:opacity-90 transition-all focus:outline-none focus:ring-4 focus:ring-green-100" style="background-color: #10b981; color: #ffffff; border: none;">
                                OK
                            </button>
                        </div>
                    `,
                    showConfirmButton: false,
                    width: '24rem',
                    padding: '2.5rem 1rem 2rem 1rem',
                    customClass: {
                        popup: '!rounded-[2.5rem] bg-white shadow-2xl border border-gray-100 font-sans',
                        htmlContainer: '!m-0 !p-0',
                    }
                });
            } else {
                Swal.fire({
                    html: `
                        <div class="flex flex-col items-center px-2">
                            <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-red-50 text-[#ef4444] ring-[6px] ring-red-50/50">
                                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2 tracking-tight">Gagal!</h3>
                            <p class="text-base text-gray-500 mb-8 leading-relaxed">${data.message || data.error}</p>
                            <button type="button" onclick="Swal.close()" class="w-full inline-flex justify-center !rounded-[1.25rem] px-5 py-3.5 text-base font-bold shadow-sm hover:opacity-90 transition-all focus:outline-none focus:ring-4 focus:ring-red-100" style="background-color: #ef4444; color: #ffffff; border: none;">
                                OK
                            </button>
                        </div>
                    `,
                    showConfirmButton: false,
                    width: '24rem',
                    padding: '2.5rem 1rem 2rem 1rem',
                    customClass: {
                        popup: '!rounded-[2.5rem] bg-white shadow-2xl border border-gray-100 font-sans',
                        htmlContainer: '!m-0 !p-0',
                    }
                });
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire({
                html: `
                    <div class="flex flex-col items-center px-2">
                        <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-red-50 text-[#ef4444] ring-[6px] ring-red-50/50">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2 tracking-tight">Gagal!</h3>
                        <p class="text-base text-gray-500 mb-8 leading-relaxed">Terjadi kesalahan saat menyimpan data.</p>
                        <button type="button" onclick="Swal.close()" class="w-full inline-flex justify-center !rounded-[1.25rem] px-5 py-3.5 text-base font-bold shadow-sm hover:opacity-90 transition-all focus:outline-none focus:ring-4 focus:ring-red-100" style="background-color: #ef4444; color: #ffffff; border: none;">
                            OK
                        </button>
                    </div>
                `,
                showConfirmButton: false,
                width: '24rem',
                padding: '2.5rem 1rem 2rem 1rem',
                customClass: {
                    popup: '!rounded-[2.5rem] bg-white shadow-2xl border border-gray-100 font-sans',
                    htmlContainer: '!m-0 !p-0',
                }
            });
        })
        .finally(() => {
            btnSave.innerHTML = originalText;
            btnSave.disabled = false;
        });
}

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
    // Palet grafik: tiga warna pertama mengikuti logo BPS (biru, oranye, hijau).
    chartColors: ['#0B6FB8', '#E8850C', '#4C9A2A', '#D64545', '#7B61C4', '#0F9C9A'],

    init: function () {
        if (window.DashboardConfig && window.DashboardConfig.visualizations) {
            this.visualizations = window.DashboardConfig.visualizations;
        }
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

        if (hasMap) {
            const mapId = `vis-${vis.id}-choropleth`;
            const mapWrapper = document.createElement('div');
            mapWrapper.className = 'vis-wrapper vis-wrapper-map';
            mapWrapper.innerHTML = `
                <h4 class="vis-wrapper-title"><i class="fas fa-map-marked-alt fa-fw"></i> Peta Sebaran</h4>
                <div class="auto-map-container"><div id="${mapId}" style="height:100%;"></div></div>`;
            placeholder.appendChild(mapWrapper);

            const chartsGrid = document.createElement('div');
            chartsGrid.className = 'charts-grid';
            otherTypes.forEach(type => this.appendChartContainer(chartsGrid, vis, type));
            placeholder.appendChild(chartsGrid);
        } else {
            otherTypes.forEach(type => this.appendChartContainer(placeholder, vis, type));
        }

        available_types.filter(t => t !== 'table').forEach(type => {
            this.createVisualization(vis, type);
        });
    },

    appendChartContainer: function (container, vis, type) {
        const uniqueId = `vis-${vis.id}-${type}`;
        let title = '';
        if (type === 'line') title = '<i class="fas fa-chart-line fa-fw"></i> Grafik Tren';
        else if (type === 'bar') title = '<i class="fas fa-chart-bar fa-fw"></i> Grafik Batang';
        else if (type === 'pie') title = '<i class="fas fa-chart-pie fa-fw"></i> Komposisi';
        else if (type === 'pyramid') title = '<i class="fas fa-chart-area fa-fw"></i> Piramida Penduduk';
        else if (type === 'card') title = '<i class="fas fa-info-circle fa-fw"></i> Detail Nilai Terpilih';

        if (title) {
            const wrapper = document.createElement('div');
            wrapper.className = `vis-wrapper vis-wrapper-${type}`;
            wrapper.style.height = '100%'; // Pastikan kotak membentang penuh

            // Perlakuan khusus wadah card (menggunakan div biasa, bukan canvas grafik)
            if (type === 'card') {
                wrapper.innerHTML = `<h4 class="vis-wrapper-title">${title}</h4>
                                     <div id="${uniqueId}" class="flex flex-col items-center justify-center mt-4" style="flex: 1; height: 100%;"></div>`;
            } else if (type === 'pyramid' || type === 'pie') {
                wrapper.style.gridRow = 'span 2';
                wrapper.innerHTML = `<h4 class="vis-wrapper-title">${title}</h4>
                                     <div class="auto-chart-container" style="flex: 1; min-height: 450px; height: auto;"><canvas id="${uniqueId}"></canvas></div>`;
            } else if (type === 'bar') {
                const isNeracaEkonomi = vis.subject && String(vis.subject).trim().toLowerCase() === 'neraca ekonomi';
                if (isNeracaEkonomi) wrapper.style.gridColumn = '1 / -1';
                const minHeight = isNeracaEkonomi ? 350 : 285;
                wrapper.innerHTML = `<h4 class="vis-wrapper-title">${title}</h4>
                                     <div class="auto-chart-container" style="flex: 1; min-height: ${minHeight}px; height: auto;"><canvas id="${uniqueId}"></canvas></div>`;
            } else {
                if (type === 'line') {
                    const availTypes = vis.available_chart_types || vis.available_types || [];
                    const isNeracaEkonomi = vis.subject && String(vis.subject).trim().toLowerCase() === 'neraca ekonomi';
                    if (!availTypes.includes('pyramid') && !isNeracaEkonomi) {
                        wrapper.style.gridColumn = '1 / -1';
                    }
                }
                wrapper.innerHTML = `<h4 class="vis-wrapper-title">${title}</h4>
                                     <div class="auto-chart-container" style="flex: 1; min-height: 285px; height: auto;"><canvas id="${uniqueId}"></canvas></div>`;
            }
            container.appendChild(wrapper);
        }
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
        const data = vis.parsed_data;
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

        if (type !== 'line' && config.x_axis_temporal && !vis.selected_year) {
            const container = ctx.parentElement;
            container.innerHTML = `<div class="flex flex-col items-center justify-center h-full bg-blue-50 text-blue-700 p-6 rounded-lg border border-blue-200 text-center">
                <i class="fas fa-filter fa-2x mb-3 text-blue-400"></i>
                <p class="text-sm font-medium">Silakan pilih filter <b>Tahun</b> terlebih dahulu untuk menampilkan Grafik Batang.</p>
            </div>`;
            return;
        }

        // --- PENGAMAN SPAGHETTI TREN CHART ---
        if (type === 'line' && vis.filters) {
            const activeFilters = vis.active_filters || {};
            let needsSpecificFilter = false;
            let requiredFilterName = '';

            Object.keys(vis.filters).forEach(filterCol => {
                if (filterCol === config.x_axis_temporal) return;

                const colLower = filterCol.toLowerCase();
                // Jangan blokir filter Bulan karena akan kita jadikan Sumbu X
                if (colLower === 'bulan') return;

                const isAgeGroup = colLower.includes('umur') || colLower.includes('usia');
                const isTooMany = vis.filters[filterCol].options.length > 7;

                if ((isTooMany || isAgeGroup) && (!activeFilters[filterCol] || activeFilters[filterCol] === '')) {
                    needsSpecificFilter = true;
                    requiredFilterName = filterCol;
                }
            });

            if (needsSpecificFilter) {
                const container = ctx.parentElement;
                container.innerHTML = `<div class="flex flex-col items-center justify-center h-full bg-blue-50 text-blue-700 p-6 rounded-lg border border-blue-200 text-center">
                    <i class="fas fa-layer-group fa-2x mb-3 text-blue-400"></i>
                    <p class="text-sm font-medium">Silakan pilih spesifik <b>${requiredFilterName}</b> terlebih dahulu untuk melihat Grafik Tren (data terlalu padat jika "Semua").</p>
                </div>`;
                return;
            }
        }

        let data = vis.parsed_data;
        const activeFilters = vis.active_filters || {};

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

        // --- 🌟 LOGIKA CERDAS V3 (REVISI AMAN) ---
        let detectedGroup = (config.group_by && config.group_by !== xColumn) ? config.group_by : null;

        if (!detectedGroup || (activeFilters[detectedGroup] && activeFilters[detectedGroup] !== '')) {
            if (vis.filters) {
                const potentialGroups = Object.keys(vis.filters).filter(k =>
                    k !== xColumn &&
                    k !== vis.temporal_column &&
                    k.toLowerCase() !== 'bulan' &&
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
            let isIgnored = (col === vis.temporal_column || col === xColumn || col === groupColumn);

            if (val && !isIgnored) {
                data = data.filter(item => item[col] == val);
            }
        });

        // ====================================================================
        // >>> LOGIKA CERDAS BARU: PRIORITASKAN 'JUMLAH' JIKA FILTER POSISI 'SEMUA' <<<
        // ====================================================================
        if (vis.filters) {
            Object.keys(vis.filters).forEach(col => {
                if (col !== vis.temporal_column && col !== xColumn && col !== groupColumn && (!activeFilters[col] || activeFilters[col] === '')) {
                    const hasSummary = data.some(item => {
                        const s = String(item[col]).toLowerCase().trim();
                        return s === 'tahunan' || s === 'total' || s === 'jumlah' || s === 'pdrb' || s === 'produk domestik regional bruto' || s.startsWith('jumlah ') || s.startsWith('total ');
                    });

                    if (hasSummary) {
                        data = data.filter(item => {
                            const s = String(item[col]).toLowerCase().trim();
                            return s === 'tahunan' || s === 'total' || s === 'jumlah' || s === 'pdrb' || s === 'produk domestik regional bruto' || s.startsWith('jumlah ') || s.startsWith('total ');
                        });
                    }
                }
            });
        }
        // ====================================================================

        if (type === 'bar' && vis.selected_year && vis.temporal_column) {
            data = data.filter(item => item[vis.temporal_column] == vis.selected_year);
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

        // --- 🌟 FAILSAFE: Pastikan yColumns terdeteksi ---
        yColumns = config.y_axis_numeric || [];
        if (yColumns.length === 0 && data.length > 0) {
            const possibleCols = Object.keys(data[0]).filter(k => k !== xColumn && k !== vis.temporal_column);
            const fallbackCol = possibleCols.find(k => !isNaN(parseFloat(data[0][k])));
            if (fallbackCol) yColumns = [fallbackCol];
        }

        // ====================================================================
        // >>> LOGIKA CERDAS V6: PISAHKAN TAHUNAN SEBAGAI GARIS REFERENSI <<<
        // ====================================================================
        let tahunanDataByGroup = {};
        let tahunanDataByCol = {};
        const hasTahunanLabel = isMonthTrend && labels.some(lbl => String(lbl).toLowerCase() === 'tahunan');

        if (hasTahunanLabel && labels.length > 1 && type === 'line') {
            labels = labels.filter(lbl => String(lbl).toLowerCase() !== 'tahunan');

            if (groupColumn) {
                const groups = [...new Set(data.map(i => i[groupColumn]))].sort();
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
            const groups = [...new Set(data.map(i => i[groupColumn]))].sort();
            groups.forEach((grp, idx) => {
                const yCol = yColumns[0];
                const d = labels.map(lbl => data.find(item => item[xColumn] == lbl && item[groupColumn] == grp)?.[yCol] || null);

                let isDimmed = false;
                const isGroupFiltered = activeFilters[groupColumn] && activeFilters[groupColumn] !== '';
                if (isGroupFiltered && String(grp).toLowerCase().trim() !== String(activeFilters[groupColumn]).toLowerCase().trim()) {
                    isDimmed = true;
                }

                // REVISI GLOBAL: Sembunyikan garis redup sepenuhnya untuk SEMUA Grafik Tren (Line Chart)
                if (isDimmed && type === 'line') {
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


                let bgColors = isDimmed ? (this.chartColors[idx % 6] + '20') : (this.chartColors[idx % 6] + 'D9');
                let borderColors = isDimmed ? (this.chartColors[idx % 6] + '40') : this.chartColors[idx % 6];

                // Jika ini Bar Chart, dukung peredupan individual bar berdasarkan filter Sumbu X (meskipun Grouped)
                if (type === 'bar') {
                    const isXFiltered = activeFilters[xColumn] && activeFilters[xColumn] !== '';
                    if (isXFiltered) {
                        const targetX = String(activeFilters[xColumn]).toLowerCase().trim();
                        bgColors = labels.map(lbl => {
                            if (isDimmed) return this.chartColors[idx % 6] + '10'; // Super redup jika grupnya juga redup
                            const isMatch = String(lbl).toLowerCase().trim() === targetX;
                            return isMatch ? (this.chartColors[idx % 6] + 'D9') : (this.chartColors[idx % 6] + '20');
                        });
                        borderColors = labels.map(lbl => {
                            if (isDimmed) return this.chartColors[idx % 6] + '20';
                            const isMatch = String(lbl).toLowerCase().trim() === targetX;
                            return isMatch ? this.chartColors[idx % 6] : (this.chartColors[idx % 6] + '40');
                        });
                    }
                }

                datasets.push({
                    label: grp,
                    data: d,
                    isDimmed: isDimmed,
                    borderColor: borderColors,
                    backgroundColor: bgColors,
                    borderWidth: type === 'line' ? (isDimmed ? 1 : 3) : 1,
                    fill: type !== 'line',
                    tension: 0.1,
                    pointRadius: pointRadius,
                    pointHoverRadius: pointHoverRadius,
                    pointBorderWidth: pointBorderWidth,
                    pointBackgroundColor: this.chartColors[idx % 6]
                });
            });
        } else {
            let dataForAggregation = data;

            // --- 🌟 LOGIKA CERDAS V5: HAPUS PDRB/TOTAL HANYA JIKA FILTER "SEMUA" ---
            if (type === 'bar' && !groupColumn) {
                // Cek apakah user sedang memfilter kolom Sumbu X secara spesifik
                const isXFiltered = activeFilters[xColumn] && activeFilters[xColumn] !== '';

                // Jika posisi filter "Semua" (tidak difilter spesifik), barulah kita hapus PDRB/Total
                if (!isXFiltered) {
                    for (let i = labels.length - 1; i >= 0; i--) {
                        const lblStr = String(labels[i]).toLowerCase().trim();
                        if (lblStr === 'total' || lblStr === 'jumlah' || lblStr === 'pdrb' || lblStr.startsWith('jumlah ') || lblStr.startsWith('total ')) {
                            labels.splice(i, 1);
                        }
                    }
                }
            }

            // Failsafe sudah dipindah ke atas

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
                let bgColors = this.chartColors[idx % 6] + (type === 'line' ? '1F' : 'D9');
                let borderColors = this.chartColors[idx % 6];

                // Warna pelangi untuk Bar Chart / Pie Chart tunggal
                if ((type === 'bar' || type === 'pie') && !groupColumn) {
                    bgColors = labels.map((lbl, i) => {
                        const isFiltered = activeFilters[xColumn] && activeFilters[xColumn] !== '';
                        if (isFiltered && String(lbl).toLowerCase().trim() !== String(activeFilters[xColumn]).toLowerCase().trim()) {
                            return this.chartColors[i % this.chartColors.length] + '20'; // Redup warna asli
                        }
                        return this.chartColors[i % this.chartColors.length] + (type === 'pie' ? 'E6' : 'D9');
                    });
                    borderColors = labels.map((lbl, i) => {
                        const isFiltered = activeFilters[xColumn] && activeFilters[xColumn] !== '';
                        if (isFiltered && String(lbl).toLowerCase().trim() !== String(activeFilters[xColumn]).toLowerCase().trim()) {
                            return this.chartColors[i % this.chartColors.length] + '40'; // Redup warna asli
                        }
                        return this.chartColors[i % this.chartColors.length];
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
                const groups = [...new Set(data.map(i => i[groupColumn]))].sort();
                groups.forEach((grp, idx) => {
                    const val = tahunanDataByGroup[grp];
                    if (val !== null && val !== undefined && !isNaN(val)) {
                        datasets.push({
                            label: grp + ' (Tahunan)',
                            data: labels.map(() => val),
                            borderColor: this.chartColors[idx % 6],
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
                                color: this.chartColors[idx % 6],
                                backgroundColor: '#ffffff',
                                borderColor: this.chartColors[idx % 6],
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

        const allDatasetValues = datasets.flatMap(d => d.data).filter(v => v !== null && v > 0);
        const maxVal = allDatasetValues.length ? Math.max(...allDatasetValues) : 0;
        const minVal = allDatasetValues.length ? Math.min(...allDatasetValues) : 0;
        const useLogScale = (type === 'line' && maxVal > 0 && minVal > 0 && (maxVal / minVal > 100));

        const chart = new Chart(ctx, {
            type: type,
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 40, right: 40, left: 10, bottom: 10 } },
                plugins: {
                    legend: { display: datasets.length > 1 && !!groupColumn },
                    datalabels: {
                        display: function (context) {
                            const val = context.dataset.data[context.dataIndex];
                            if (val === null || val === 0 || val === undefined) return false;

                            if (type === 'line') {
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
                                return true;
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
                        padding: { top: 4, bottom: 4, left: 7, right: 7 },
                        font: { weight: '600', size: 11 },
                        clip: false,
                        clamp: true
                    },
                    tooltip: {
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
                            autoSkip: false,
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
                        beginAtZero: !useLogScale,
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

        // --- 1. SYARAT WAJIB PILIH TAHUN ---
        if (config.x_axis_temporal && !vis.selected_year) {
            const container = ctx.parentElement;
            container.innerHTML = `<div class="flex flex-col items-center justify-center h-full bg-blue-50 text-blue-700 p-6 rounded-lg border border-blue-200 text-center">
                <i class="fas fa-chart-pie fa-2x mb-3 text-blue-400"></i>
                <p class="text-sm font-medium">Silakan pilih filter <b>Tahun</b> terlebih dahulu untuk menampilkan Komposisi Data.</p>
            </div>`;
            return;
        }

        let data = vis.parsed_data;
        if (vis.selected_year && vis.temporal_column) {
            data = data.filter(item => item[vis.temporal_column] == vis.selected_year);
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
            const defaultColor = this.chartColors[idx % this.chartColors.length] + 'E6'; // 90% opacity

            if (isTotalRequested || (labelsArray.length === 1 && (labelsArray[0].toLowerCase().includes('total') || labelsArray[0].toLowerCase().includes('pdrb')))) {
                return '#4C9A2A';
            }

            let isDimmed = false;
            // Deteksi jika user sedang memfilter pie_label spesifik
            if (config.pie_label && activeFilters[config.pie_label] && activeFilters[config.pie_label] !== '') {
                const target = String(activeFilters[config.pie_label]).toLowerCase().trim();
                if (String(lbl).toLowerCase().trim() !== target) isDimmed = true;
            }

            return isDimmed ? (this.chartColors[idx % this.chartColors.length] + '20') : defaultColor;
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
                            padding: 15,
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

        // --- 3. SYARAT WAJIB PILIH TAHUN UNTUK PETA ---
        if (config.x_axis_temporal && !vis.selected_year) {
            const container = mapEl.parentElement;
            container.innerHTML = `<div class="flex flex-col items-center justify-center h-full bg-blue-50 text-blue-700 p-6 rounded-lg border border-blue-200 text-center">
                <i class="fas fa-map-marked-alt fa-2x mb-3 text-blue-400"></i>
                <p class="text-sm font-medium">Silakan pilih filter <b>Tahun</b> terlebih dahulu untuk menampilkan Peta Sebaran wilayah.</p>
            </div>`;
            return;
        }

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

        let data = vis.parsed_data;
        if (vis.selected_year && vis.temporal_column) {
            data = data.filter(item => item[vis.temporal_column] == vis.selected_year);
        }

        // --- FILTER MANUAL JS KHUSUS PETA ---
        const activeFilters = vis.active_filters || {};
        const geoCol = config.geo_column;
        const valCol = config.val_column;

        const geoColLow = String(geoCol || '').toLowerCase().trim();
        const tempColLow = String(vis.temporal_column || '').toLowerCase().trim();

        // Cek secara case-insensitive apakah filter kota utuh yang dipilih
        const selectedGeoRaw = activeFilters[Object.keys(activeFilters).find(k => String(k).toLowerCase().trim() === geoColLow)];
        const selectedGeo = selectedGeoRaw ? String(selectedGeoRaw).toLowerCase().replace(/ /g, '') : null;
        let isCitySelected = selectedGeo === 'pematangsiantar' || selectedGeo === 'kotapematangsiantar';

        Object.keys(activeFilters).forEach(col => {
            const val = activeFilters[col];
            const cLow = String(col).toLowerCase().trim();
            if (val && cLow !== tempColLow) {
                // Jika filter adalah geo_column (Kecamatan), DAN valuelnya adalah Pematangsiantar, JANGAN difilter
                if (cLow === geoColLow && isCitySelected) {
                    // Bypass filter Pematangsiantar agar semua kecamatan tetap tampil
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
                    fillOpacity: isSelected ? 1 : 0.85,
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

        setTimeout(() => {
            if (map) {
                map.invalidateSize();
                map.fitBounds(layer.getBounds(), { padding: [20, 20] });
            }
        }, 150);
        this.instances[elementId] = { type: 'map', instance: map };
    },

    createValueCard: function (vis, elementId) {
        const container = document.getElementById(elementId);
        if (!container) return;

        const config = vis.visualization_config;
        let data = vis.parsed_data;
        const activeFilters = vis.active_filters || {};

        if (vis.selected_year && vis.temporal_column) {
            data = data.filter(item => item[vis.temporal_column] == vis.selected_year);
        }

        Object.keys(activeFilters).forEach(col => {
            const val = activeFilters[col];
            if (val && col !== vis.temporal_column) {
                data = data.filter(item => String(item[col]).toLowerCase().trim() === String(val).toLowerCase().trim());
            }
        });

        const valCol = config.val_column || 'Nilai';

        // 1. PENGAMAN PERTAMA: WAJIB PILIH TAHUN
        if (config.x_axis_temporal && !vis.selected_year) {
            container.innerHTML = `
                <div class="bg-blue-50 text-blue-700 border border-blue-200 rounded-lg p-6 w-full h-full flex flex-col justify-center items-center text-center min-h-[150px]">
                    <i class="fas fa-calendar-alt fa-2x text-blue-400 mb-3"></i>
                    <p class="text-sm font-medium">Silakan pilih spesifik <b>Tahun</b> terlebih dahulu untuk melihat angka.</p>
                </div>
            `;
            return;
        }

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
                if (k !== vis.temporal_column && (!activeFilters[k] || activeFilters[k] === '')) {
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
                    if (key !== valCol && key !== vis.temporal_column && key !== 'id') {
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
                    if (activeFilters[k] && activeFilters[k] !== '' && k !== vis.temporal_column && k.toLowerCase() !== 'bulan') {
                        activeFilterNames.push(activeFilters[k]);
                    }
                });
                let labelInfo = activeFilterNames.length > 0 ? activeFilterNames.join(' - ') : 'Data Tahunan / Total';
                labelInfo += vis.selected_year ? ` (${vis.selected_year})` : '';

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
                    if (key !== valCol && key !== vis.temporal_column) {
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
                    if (activeFilters[k] && activeFilters[k] !== '' && k !== vis.temporal_column && k.toLowerCase() !== 'bulan') {
                        activeFilterNames.push(activeFilters[k]);
                    }
                });
                let labelInfo = activeFilterNames.length > 0 ? activeFilterNames.join(' - ') : 'Total Keseluruhan';
                labelInfo += vis.selected_year ? ` (${vis.selected_year})` : '';

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
            if (k !== valCol && k !== 'id' && k !== vis.temporal_column && row[k]) {
                parts.push(row[k]);
            }
        });
        let labelInfo = parts.join(' - ') + (vis.selected_year ? ` (${vis.selected_year})` : '');

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

        let data = vis.parsed_data;

        // --- 1. SYARAT WAJIB PILIH TAHUN ---
        if (config.x_axis_temporal && !vis.selected_year) {
            const container = ctx.parentElement;
            container.innerHTML = `<div class="flex flex-col items-center justify-center h-full bg-blue-50 text-blue-700 p-6 rounded-lg border border-blue-200 text-center">
                <i class="fas fa-chart-area fa-2x mb-3 text-blue-400"></i>
                <p class="text-sm font-medium">Silakan pilih filter <b>Tahun</b> terlebih dahulu untuk menampilkan Piramida Penduduk.</p>
            </div>`;
            return;
        }

        if (vis.selected_year && (vis.temporal_column || config.x_axis_temporal)) {
            const tempCol = vis.temporal_column || config.x_axis_temporal;
            data = data.filter(item => item[tempCol] == vis.selected_year);
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
            genderCol = Object.keys(vis.filters).find(k => k !== ageCol && k !== vis.temporal_column);
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

document.addEventListener('DOMContentLoaded', () => {
    if (window.DashboardConfig) {
        window.dashboardApp.init();
    }
});

// ==========================================
// 6. Inisialisasi Alpine — HARUS di paling bawah
// ==========================================
window.Alpine = Alpine;
Alpine.start();
