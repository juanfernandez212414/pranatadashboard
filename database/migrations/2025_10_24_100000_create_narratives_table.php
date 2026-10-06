<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('narratives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Audit trail
            $table->foreignId('indicator_id')
                ->constrained('indicators')
                ->onDelete('cascade'); // Jika indicator dihapus, narasinya ikut terhapus
            $table->text('content')->nullable(); // Isi dari narasi tersebut
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('narratives');
    }
};
