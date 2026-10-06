<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Audit trail: Siapa yang membuat
            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->onDelete('cascade'); // jika subject dihapus, indikator ikut hilang
            $table->string('name');
            $table->string('unit')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};
