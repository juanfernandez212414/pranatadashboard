<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tautan indikator ke tabel sumbernya di WebAPI BPS, agar datanya bisa diperbarui (disinkronkan) dari API.
// Indikator yang diisi manual atau lewat impor Excel kolom-kolomnya tetap kosong.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            $table->string('bps_source', 20)->nullable()->after('data');      // dinamis | simdasi | statis
            $table->string('bps_table_id', 100)->nullable()->after('bps_source'); // ID var / id_tabel SIMDASI / ID tabel statis
            $table->json('bps_options')->nullable()->after('bps_table_id');    // saringan tabel dinamis (baris, karakteristik, turunan tahun)
            $table->timestamp('bps_synced_at')->nullable()->after('bps_options');
            $table->index(['bps_source', 'bps_table_id']);
        });
    }

    public function down(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            $table->dropIndex(['bps_source', 'bps_table_id']);
            $table->dropColumn(['bps_source', 'bps_table_id', 'bps_options', 'bps_synced_at']);
        });
    }
};
