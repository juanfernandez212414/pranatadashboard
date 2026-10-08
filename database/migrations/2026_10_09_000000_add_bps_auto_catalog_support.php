<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pendukung cermin katalog tabel dinamis BPS otomatis (App\Services\Bps\SinkronisasiBps):
// - bps_tabel_diabaikan: tabel dinamis yang indikatornya dihapus pengguna, agar tidak dibuat lagi otomatis;
// - indicators.bps_chart: jenis grafik bawaan BPS untuk tabel itu (graph_name di WebAPI: line, bar, ...),
//   ditampilkan pertama di dashboard.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bps_tabel_diabaikan', function (Blueprint $table) {
            $table->id();
            $table->string('bps_table_id', 100)->unique(); // ID var tabel dinamis
            $table->string('judul')->nullable();
            $table->timestamps();
        });

        Schema::table('indicators', function (Blueprint $table) {
            $table->string('bps_chart', 20)->nullable()->after('bps_options');
        });
    }

    public function down(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            $table->dropColumn('bps_chart');
        });
        Schema::dropIfExists('bps_tabel_diabaikan');
    }
};
