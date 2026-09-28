<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FK silang ke saldo_awal_rincian ditambahkan setelah semua tabel tersedia
 * (dokumen05 §Aturan migration langkah 3). Relasi melingkar aset/pinjaman
 * dengan opening_rincian; blueprint default memakai kedua pointer + validator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pinjaman', function (Blueprint $table) {
            $table->foreign('opening_rincian_id')->references('id')->on('saldo_awal_rincian')->restrictOnDelete();
        });

        Schema::table('aset_tetap', function (Blueprint $table) {
            $table->foreign('opening_rincian_id')->references('id')->on('saldo_awal_rincian')->restrictOnDelete();
        });

        Schema::table('alokasi_pelunasan', function (Blueprint $table) {
            $table->foreign('opening_rincian_id')->references('id')->on('saldo_awal_rincian')->restrictOnDelete();
        });

        Schema::table('mutasi_stok', function (Blueprint $table) {
            $table->foreign('opening_rincian_id')->references('id')->on('saldo_awal_rincian')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pinjaman', fn (Blueprint $table) => $table->dropForeign(['opening_rincian_id']));
        Schema::table('aset_tetap', fn (Blueprint $table) => $table->dropForeign(['opening_rincian_id']));
        Schema::table('alokasi_pelunasan', fn (Blueprint $table) => $table->dropForeign(['opening_rincian_id']));
        Schema::table('mutasi_stok', fn (Blueprint $table) => $table->dropForeign(['opening_rincian_id']));
    }
};
