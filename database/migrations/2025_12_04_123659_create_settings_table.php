<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- Penting: Jangan lupa import ini

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Setting milik user spesifik
            $table->string('key'); // Nama setting (misal: active_ai_model)
            $table->text('value')->nullable(); // Nilai setting (misal: llama-3.3...)
            $table->timestamps();

            // Memastikan satu user hanya bisa punya 1 nilai untuk tiap jenis setting (misal: 1 active_ai_model per user)
            $table->unique(['user_id', 'key']);
        });

        // Catatan: Seeding global dihapus karena setting sekarang spesifik per-user.
        // Berikan default value langsung di kodingan Controller/Service aplikasi Anda jika data di tabel ini belum ada.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
