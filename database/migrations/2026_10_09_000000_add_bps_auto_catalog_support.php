<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pendukung cermin katalog tabel dinamis BPS otomatis (App\Services\Bps\SinkronisasiBps):
// - bps_tabel_diabaikan: tabel dinamis yang indikatornya dihapus pengguna, agar tidak dibuat lagi otomatis;
// - indicators.bps_chart: jenis grafik bawaan BPS untuk tabel itu (graph_name di WebAPI: line, bar, ...),
//   ditampilkan pertama di dashboard.
// Tautan SIMDASI/tabel statis dari versi sebelumnya dilepas: datanya tetap, tetapi tidak lagi dianggap
// indikator API (hanya tabel dinamis yang dipakai).
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

        DB::table('indicators')->whereIn('bps_source', ['simdasi', 'statis'])
            ->update(['bps_source' => null, 'bps_table_id' => null, 'bps_options' => null, 'bps_synced_at' => null]);
    }

    public function down(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            $table->dropColumn('bps_chart');
        });
        Schema::dropIfExists('bps_tabel_diabaikan');
    }
};
