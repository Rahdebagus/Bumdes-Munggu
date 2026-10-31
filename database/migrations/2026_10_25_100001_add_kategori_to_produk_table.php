<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori produk (konsumsi/perdagangan/atk/upakara/jasa/pupuk) menentukan akun
 * penjualan & HPP saat posting. lacak_stok=false untuk jasa (fotocopy, cetak):
 * tanpa mutasi stok, biaya pembelian langsung ke HPP kategori. Penanda harga
 * jual estimasi ("perlu verifikasi") disimpan agar admin dapat mengoreksinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->string('kategori', 20)->nullable()->after('nama_produk');
            $table->boolean('lacak_stok')->default(true)->after('satuan');
            $table->string('sumber_harga_jual', 100)->nullable()->after('harga_jual');
            $table->boolean('harga_jual_perlu_verifikasi')->default(false)->after('sumber_harga_jual');

            $table->index('kategori');
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropIndex(['kategori']);
            $table->dropColumn(['kategori', 'lacak_stok', 'sumber_harga_jual', 'harga_jual_perlu_verifikasi']);
        });
    }
};
