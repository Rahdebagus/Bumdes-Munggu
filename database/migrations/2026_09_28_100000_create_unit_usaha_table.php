<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_usaha', function (Blueprint $table) {
            $table->id();
            $table->string('kode_unit', 20)->unique();
            $table->string('nama_unit', 100);
            $table->string('jenis_usaha', 20); // dagang | kredit
            $table->string('penanggung_jawab', 100);
            $table->string('status', 20)->default('aktif'); // aktif | nonaktif
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_usaha');
    }
};
