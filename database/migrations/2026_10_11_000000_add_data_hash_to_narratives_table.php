<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sidik (hash) data indikator saat narasi disimpan. Bila data indikator berubah sesudahnya (sinkron BPS malam,
// impor, edit), narasi ditandai "data sudah diperbarui" di dashboard publik dan diberi tanda "perlu diperbarui"
// untuk Admin/PJ. Narasi lama (sebelum kolom ini ada) bernilai null dan tidak ditandai.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('narratives', function (Blueprint $table) {
            $table->string('data_hash', 40)->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('narratives', function (Blueprint $table) {
            $table->dropColumn('data_hash');
        });
    }
};
