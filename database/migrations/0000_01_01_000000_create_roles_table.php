<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Insert default roles
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Pimpinan', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Penanggung Jawab', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Pengguna Biasa', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
