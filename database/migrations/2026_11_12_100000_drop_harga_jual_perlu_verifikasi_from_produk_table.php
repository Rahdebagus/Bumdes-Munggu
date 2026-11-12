<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Penanda "harga jual perlu verifikasi" dihapus: harga jual di master produk dianggap final. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropColumn('harga_jual_perlu_verifikasi');
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->boolean('harga_jual_perlu_verifikasi')->default(false)->after('sumber_harga_jual');
        });
    }
};
