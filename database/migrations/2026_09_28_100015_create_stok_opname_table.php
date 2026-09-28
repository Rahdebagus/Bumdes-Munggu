<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_opname', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_opname', 30)->unique();
            $table->date('tanggal');
            $table->foreignId('periode_id')->constrained('periode_akuntansi')->restrictOnDelete();
            $table->foreignId('unit_usaha_id')->constrained('unit_usaha')->restrictOnDelete();
            // draft,selesai,menunggu_persetujuan,disetujui,posted,dibatalkan
            $table->string('status', 30)->default('draft');
            $table->decimal('nilai_selisih', 19, 2)->default(0); // boleh negatif
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();

            // dokumen05
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamps();

            $table->index(['periode_id', 'unit_usaha_id', 'status'], 'idx_opname_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_opname');
    }
};
