<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksi')->restrictOnDelete();
            $table->foreignId('akun_id')->constrained('akun')->restrictOnDelete();
            $table->decimal('debit', 19, 2)->default(0);
            $table->decimal('kredit', 19, 2)->default(0);
            $table->text('keterangan')->nullable();

            // dokumen05
            $table->string('kelompok_arus_kas', 20)->nullable(); // operasi | investasi | pendanaan
            $table->string('report_group_snapshot', 50)->nullable();
            $table->unsignedInteger('nomor_baris');
            $table->timestamps();

            $table->unique(['transaksi_id', 'nomor_baris'], 'uq_detail_baris');
            $table->index(['akun_id', 'transaksi_id'], 'idx_detail_akun');
        });

        // Jurnal resmi: tiap baris tepat satu sisi positif. Keseimbangan
        // SUM(debit)=SUM(kredit) diverifikasi PostingService (tahap04).
        DB::statement('ALTER TABLE transaksi_detail ADD CONSTRAINT chk_detail_debit_kredit CHECK ((debit > 0 AND kredit = 0) OR (kredit > 0 AND debit = 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_detail');
    }
};
