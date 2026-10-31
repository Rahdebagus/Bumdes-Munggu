<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Setoran pinjaman pedagang lama (rincian saldo awal jenis pinjaman) dicatat
 * borongan seperti slip kas kredit BUMDes: per nasabah ada pokok (nominal,
 * mengurangi sisa pinjaman) serta bunga & denda (pendapatan). Pelunasan
 * piutang/utang biasa tetap 0 pada kedua kolom ini. Setoran bunga saja
 * (pokok 0) diizinkan: syarat menjadi pokok + bunga + denda > 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alokasi_pelunasan', function (Blueprint $table) {
            $table->decimal('bunga', 19, 2)->default(0)->after('nominal');
            $table->decimal('denda', 19, 2)->default(0)->after('bunga');
        });

        DB::statement('ALTER TABLE alokasi_pelunasan DROP CONSTRAINT chk_alokasi_target');
        DB::statement('ALTER TABLE alokasi_pelunasan ADD CONSTRAINT chk_alokasi_target CHECK (((invoice_transaksi_id IS NOT NULL) + (opening_rincian_id IS NOT NULL)) = 1 AND nominal >= 0 AND bunga >= 0 AND denda >= 0 AND (nominal + bunga + denda) > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE alokasi_pelunasan DROP CONSTRAINT chk_alokasi_target');
        DB::statement('ALTER TABLE alokasi_pelunasan ADD CONSTRAINT chk_alokasi_target CHECK (((invoice_transaksi_id IS NOT NULL) + (opening_rincian_id IS NOT NULL)) = 1 AND nominal > 0)');

        Schema::table('alokasi_pelunasan', function (Blueprint $table) {
            $table->dropColumn(['bunga', 'denda']);
        });
    }
};
