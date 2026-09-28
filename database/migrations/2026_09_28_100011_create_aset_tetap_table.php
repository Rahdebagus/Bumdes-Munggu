<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aset_tetap', function (Blueprint $table) {
            $table->id();
            $table->string('kode_aset', 20)->unique();
            $table->foreignId('unit_usaha_id')->nullable()->constrained('unit_usaha')->restrictOnDelete();
            $table->string('nama_aset', 100);
            $table->date('tanggal_perolehan');
            $table->decimal('harga_perolehan', 19, 2)->default(0);
            $table->decimal('nilai_residu', 19, 2)->default(0); // <= harga_perolehan
            $table->unsignedInteger('umur_manfaat'); // bulan, > 0
            $table->string('metode_penyusutan', 20)->default('garis_lurus');
            $table->string('status', 20)->default('aktif'); // aktif,dilepas,dihapus

            // dokumen05: akun terkait diisi modul aset (tahap07)
            $table->foreignId('akun_aset_id')->nullable()->constrained('akun')->restrictOnDelete();
            $table->foreignId('akun_akumulasi_id')->nullable()->constrained('akun')->restrictOnDelete();
            $table->foreignId('akun_beban_id')->nullable()->constrained('akun')->restrictOnDelete();
            $table->date('tanggal_mulai_susut')->nullable();
            $table->foreignId('transaksi_perolehan_id')->nullable()->constrained('transaksi')->restrictOnDelete();
            $table->decimal('akumulasi_awal', 19, 2)->default(0);
            // opening_rincian_id: FK ditambahkan di migration FK-silang
            $table->unsignedBigInteger('opening_rincian_id')->nullable();
            $table->timestamps();

            $table->unique('opening_rincian_id', 'uq_aset_opening');
            $table->index('status');
        });

        DB::statement('ALTER TABLE aset_tetap ADD CONSTRAINT chk_aset_nilai CHECK (harga_perolehan >= 0 AND nilai_residu >= 0 AND nilai_residu <= harga_perolehan AND umur_manfaat > 0 AND akumulasi_awal >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('aset_tetap');
    }
};
