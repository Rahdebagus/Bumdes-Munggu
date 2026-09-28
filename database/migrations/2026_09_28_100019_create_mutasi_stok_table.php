<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutasi_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->restrictOnDelete();
            $table->foreignId('transaksi_id')->nullable()->constrained('transaksi')->restrictOnDelete();
            $table->foreignId('transaksi_barang_id')->nullable()->constrained('transaksi_barang')->restrictOnDelete();
            // opening_rincian_id: FK ditambahkan di migration FK-silang
            $table->unsignedBigInteger('opening_rincian_id')->nullable();
            $table->date('tanggal');
            $table->unsignedBigInteger('urutan'); // monotonic per produk (lock)
            $table->string('jenis', 30); // opening,pembelian,penjualan,opname,reversal
            $table->decimal('kuantitas_masuk', 18, 4)->default(0);
            $table->decimal('kuantitas_keluar', 18, 4)->default(0);
            $table->decimal('nilai_masuk', 19, 2)->default(0);
            $table->decimal('nilai_keluar', 19, 2)->default(0);
            $table->decimal('harga_pokok', 19, 6)->default(0);
            $table->decimal('saldo_kuantitas', 18, 4)->default(0);
            $table->decimal('saldo_nilai', 19, 2)->default(0);
            $table->foreignId('reversal_of_id')->nullable()->constrained('mutasi_stok')->restrictOnDelete();
            $table->string('source_key', 100);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['produk_id', 'urutan'], 'uq_mutasi_urutan');
            $table->unique('source_key', 'uq_mutasi_source');
            $table->unique('reversal_of_id', 'uq_mutasi_reversal');
            $table->index(['produk_id', 'tanggal', 'urutan'], 'idx_mutasi_produk');
        });

        // Tepat satu sumber (transaksi/opening) & tepat satu arah kuantitas positif.
        DB::statement('ALTER TABLE mutasi_stok ADD CONSTRAINT chk_mutasi_source CHECK (((transaksi_id IS NOT NULL) + (opening_rincian_id IS NOT NULL)) = 1)');
        DB::statement('ALTER TABLE mutasi_stok ADD CONSTRAINT chk_mutasi_arah CHECK (((kuantitas_masuk > 0) + (kuantitas_keluar > 0)) = 1 AND kuantitas_masuk >= 0 AND kuantitas_keluar >= 0 AND nilai_masuk >= 0 AND nilai_keluar >= 0 AND harga_pokok >= 0 AND saldo_kuantitas >= 0 AND saldo_nilai >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_stok');
    }
};
