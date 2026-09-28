<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pihak', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pihak', 20)->unique();
            $table->string('jenis_pihak', 20); // pelanggan | pemasok | peminjam
            $table->string('nama', 100);
            $table->string('nomor_identitas', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->string('telepon', 20)->nullable();
            $table->string('status', 20)->default('aktif'); // aktif | nonaktif
            $table->timestamps();

            $table->index(['jenis_pihak', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pihak');
    }
};
