<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksi')->restrictOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->restrictOnDelete();
            $table->decimal('kuantitas', 18, 4);          // Q > 0
            $table->decimal('harga', 19, 2)->default(0);   // N
            $table->decimal('harga_pokok', 19, 6)->default(0); // C snapshot
            $table->decimal('diskon', 19, 2)->default(0);
            $table->decimal('pajak', 19, 2)->default(0);
            $table->decimal('nilai_pokok', 19, 2)->default(0);
            $table->decimal('subtotal', 19, 2)->default(0);
            $table->unsignedInteger('nomor_baris');
            $table->timestamps();

            $table->unique(['transaksi_id', 'nomor_baris'], 'uq_barang_baris');
            $table->index(['produk_id', 'transaksi_id'], 'idx_barang_produk');
        });

        DB::statement('ALTER TABLE transaksi_barang ADD CONSTRAINT chk_barang_qty CHECK (kuantitas > 0 AND harga >= 0 AND harga_pokok >= 0 AND diskon >= 0 AND pajak >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_barang');
    }
};
