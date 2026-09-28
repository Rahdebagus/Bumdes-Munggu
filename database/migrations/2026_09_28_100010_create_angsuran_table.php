<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('angsuran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pinjaman_id')->constrained('pinjaman')->restrictOnDelete();
            $table->unsignedInteger('angsuran_ke'); // > 0
            $table->date('tanggal_jatuh_tempo');
            $table->decimal('pokok_tagihan', 19, 2)->default(0);
            $table->decimal('bunga_tagihan', 19, 2)->default(0);
            $table->decimal('denda_tagihan', 19, 2)->default(0);
            $table->decimal('pokok_dibayar', 19, 2)->default(0);   // cache
            $table->decimal('bunga_dibayar', 19, 2)->default(0);   // cache
            $table->decimal('denda_dibayar', 19, 2)->default(0);   // cache
            $table->date('tanggal_bayar')->nullable();
            $table->string('status', 20)->default('belum_dibayar'); // belum_dibayar,sebagian,lunas,terlambat
            $table->foreignId('transaksi_id')->nullable()->constrained('transaksi')->restrictOnDelete(); // pointer pembayaran terakhir
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['pinjaman_id', 'angsuran_ke'], 'uq_angsuran_ke');
            $table->index(['tanggal_jatuh_tempo', 'pinjaman_id'], 'idx_angsuran_jatuh_tempo');
        });

        DB::statement('ALTER TABLE angsuran ADD CONSTRAINT chk_angsuran CHECK (angsuran_ke > 0 AND pokok_tagihan >= 0 AND bunga_tagihan >= 0 AND denda_tagihan >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('angsuran');
    }
};
