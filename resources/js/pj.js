// File Javascript khusus untuk menangani interaktivitas di halaman Penanggung Jawab.

// ==========================================
// resources/js/admin.js
// ==========================================

import Alpine from 'alpinejs';
import './dashboard.js'; // grafik dashboard (window.dashboardApp), dipakai bersama admin, PJ, pengguna

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

                // Keterangan narasi: waktu simpan terbaru; peringatan "data sudah berubah" tidak berlaku lagi.
                const tanggal = document.getElementById(`narrative-tanggal-${indicatorId}`);
                if (tanggal && data.diperbarui) tanggal.textContent = data.diperbarui;
                document.getElementById(`narrative-meta-${indicatorId}`)?.classList.remove('hidden');
                document.getElementById(`narrative-basi-${indicatorId}`)?.remove();

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

// ==========================================
// 6. Inisialisasi Alpine — HARUS di paling bawah
// ==========================================
window.Alpine = Alpine;
Alpine.start();
