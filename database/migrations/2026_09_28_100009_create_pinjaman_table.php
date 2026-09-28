<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinjaman', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pinjaman', 30)->unique();
            $table->foreignId('unit_usaha_id')->constrained('unit_usaha')->restrictOnDelete();
            $table->foreignId('peminjam_id')->constrained('pihak')->restrictOnDelete();
            $table->date('tanggal_pinjaman');
            $table->decimal('pokok_pinjaman', 19, 2);       // > 0
            $table->decimal('persentase_bunga', 8, 2)->default(0); // >= 0
            $table->unsignedInteger('tenor');               // bulan, > 0
            $table->decimal('total_bunga', 19, 2)->default(0);
            $table->decimal('sisa_pokok', 19, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft,aktif,lunas,macet,dibatalkan
            $table->foreignId('transaksi_id')->nullable()->constrained('transaksi')->restrictOnDelete(); // pencairan

            // dokumen05
            $table->string('basis_bunga', 30)->nullable(); // flat_total | flat_bulanan (wajib saat aktivasi)
            $table->date('tanggal_angsuran_pertama')->nullable();
            $table->string('policy_version', 30)->nullable();
            // opening_rincian_id: FK ditambahkan di migration FK-silang (tabel belum ada)
            $table->unsignedBigInteger('opening_rincian_id')->nullable();
            $table->timestamps();

            $table->unique('transaksi_id', 'uq_pinjaman_pencairan');
            $table->unique('opening_rincian_id', 'uq_pinjaman_opening');
            $table->index(['peminjam_id', 'status'], 'idx_pinjaman_peminjam');
        });

        DB::statement('ALTER TABLE pinjaman ADD CONSTRAINT chk_pinjaman_nilai CHECK (pokok_pinjaman > 0 AND tenor > 0 AND persentase_bunga >= 0 AND total_bunga >= 0 AND sisa_pokok >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pinjaman');
    }
};
