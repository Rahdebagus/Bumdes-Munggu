<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_opname_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_opname_id')->constrained('stok_opname')->restrictOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->restrictOnDelete();
            $table->decimal('stok_sistem', 18, 4)->default(0);
            $table->decimal('stok_fisik', 18, 4)->default(0);
            $table->decimal('selisih', 18, 4)->default(0); // fisik - sistem (boleh negatif)
            $table->decimal('harga_pokok', 19, 6)->default(0);
            $table->decimal('nilai_selisih', 19, 2)->default(0); // boleh negatif
            $table->text('keterangan')->nullable();
            $table->foreignId('transaksi_id')->nullable()->constrained('transaksi')->restrictOnDelete();
            $table->unsignedBigInteger('stock_version_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['stok_opname_id', 'produk_id'], 'uq_opname_detail_produk');
        });

        DB::statement('ALTER TABLE stok_opname_detail ADD CONSTRAINT chk_opname_detail CHECK (stok_sistem >= 0 AND stok_fisik >= 0 AND harga_pokok >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_opname_detail');
    }
};
