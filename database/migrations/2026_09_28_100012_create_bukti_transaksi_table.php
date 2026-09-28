<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bukti_transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksi')->restrictOnDelete();
            $table->string('nomor_bukti', 30);
            $table->date('tanggal_bukti');
            $table->string('jenis_bukti', 20); // nota,kuitansi,transfer,kas_masuk,kas_keluar
            $table->string('file_bukti', 255); // path privat
            $table->string('status_verifikasi', 20)->default('belum_diperiksa'); // belum_diperiksa,valid,ditolak
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();

            // dokumen05: metadata file
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['transaksi_id', 'status_verifikasi'], 'idx_bukti_transaksi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bukti_transaksi');
    }
};
