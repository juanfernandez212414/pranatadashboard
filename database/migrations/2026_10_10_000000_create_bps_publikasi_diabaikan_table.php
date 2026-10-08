<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dokumen yang dihapus pengguna di Manajemen Pengetahuan, agar publikasi BPS itu tidak dilatihkan lagi secara
// otomatis (jadwal malam bps:publikasi dan tombol "Ambil & Latih Publikasi Baru"). Tombol "Latih AI" per
// publikasi tetap bisa melatihnya lagi. Lihat App\Services\Bps\PublikasiBps.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bps_publikasi_diabaikan', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 200)->unique(); // PublikasiBps::kunciNama(nama dokumen)
            $table->string('nama')->nullable();     // nama dokumen yang dihapus
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bps_publikasi_diabaikan');
    }
};
