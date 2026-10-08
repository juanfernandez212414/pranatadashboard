{{-- Keterangan di bawah narasi yang sudah diterbitkan: kapan terakhir disimpan, cakupannya, dan peringatan bila
     data indikator berubah sesudah narasi disimpan (mis. diperbarui sinkron BPS malam). $petugas = tampilan
     Admin/PJ (pesannya berisi langkah memperbarui narasi). Elemen tetap dirender walau belum ada narasi agar
     admin.js/pj.js bisa mengisinya setelah narasi disimpan. --}}
@props(['vis', 'petugas' => false])
@php($info = $vis['narasi_info'] ?? null)

<p id="narrative-meta-{{ $vis['id'] }}" class="mt-4 text-xs text-gray-500 {{ $info ? '' : 'hidden' }}">
    Diterbitkan petugas BPS Kota Pematangsiantar · diperbarui
    <span id="narrative-tanggal-{{ $vis['id'] }}">{{ $info['diperbarui'] ?? '' }}</span> WIB ·
    mencakup seluruh data indikator ini (tidak mengikuti filter grafik).
</p>
@if ($info['dataBerubah'] ?? false)
    <p id="narrative-basi-{{ $vis['id'] }}" class="mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
        @if ($petugas)
            Data indikator ini sudah berubah sejak narasi disimpan. Perbarui lewat "Kelola Narasi": buat draft baru, lalu Simpan &amp; Terbitkan.
        @else
            Data indikator ini sudah diperbarui setelah narasi ditulis, jadi sebagian angka di narasi bisa berbeda dengan grafik.
        @endif
    </p>
@endif
