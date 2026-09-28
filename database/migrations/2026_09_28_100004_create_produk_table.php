<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_usaha_id')->constrained('unit_usaha')->restrictOnDelete();
            $table->string('kode_produk', 20)->unique();
            $table->string('nama_produk', 100);
            $table->string('satuan', 20);
            $table->decimal('harga_beli', 19, 2)->default(0);
            $table->decimal('harga_jual', 19, 2)->default(0);
            // stok cache = Q(18,4); harga_pokok_rata = C(19,6)
            $table->decimal('stok_terkini', 18, 4)->default(0);
            $table->decimal('stok_minimum', 18, 4)->default(0);
            $table->decimal('harga_pokok_rata', 19, 6)->default(0);
            $table->unsignedBigInteger('stock_version')->default(0);
            $table->string('status', 20)->default('aktif'); // aktif | nonaktif
            $table->timestamps();

            $table->index('unit_usaha_id');
            $table->index('status');
        });

        DB::statement('ALTER TABLE produk ADD CONSTRAINT chk_produk_nonneg CHECK (harga_beli >= 0 AND harga_jual >= 0 AND stok_terkini >= 0 AND stok_minimum >= 0 AND harga_pokok_rata >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('produk');
    }
};
