<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_desa', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_laporan', 30)->unique();
            $table->foreignId('periode_id')->constrained('periode_akuntansi')->restrictOnDelete();
            // Nilai neto boleh negatif (koreksi) — tidak ada CHECK >= 0.
            $table->decimal('omzet_dagang', 19, 2)->default(0);
            $table->decimal('pendapatan_kredit', 19, 2)->default(0);
            $table->decimal('pendapatan_lain', 19, 2)->default(0);
            $table->decimal('total_omzet', 19, 2)->default(0);
            $table->json('snapshot_data')->nullable(); // MariaDB: LONGTEXT + json_valid
            $table->string('status', 20)->default('draft'); // draft,diajukan,disetujui,dikirim,diterima,revisi
            $table->text('catatan_pimpinan')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('file_laporan', 255)->nullable();
            $table->date('tanggal_dikirim')->nullable();
            $table->date('tanggal_diterima')->nullable();
            $table->string('bukti_penerimaan', 255)->nullable();

            // dokumen05: versi + snapshot hash
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('previous_version_id')->nullable()->constrained('laporan_desa')->restrictOnDelete();
            $table->unsignedBigInteger('data_revision')->default(0);
            $table->char('snapshot_hash', 64)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['periode_id', 'version'], 'uq_laporan_periode_version');
            $table->index(['periode_id', 'status'], 'idx_laporan_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_desa');
    }
};
