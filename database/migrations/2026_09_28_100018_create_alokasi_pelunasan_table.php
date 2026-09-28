<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alokasi_pelunasan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembayaran_transaksi_id')->constrained('transaksi')->restrictOnDelete(); // pemasukan/pengeluaran
            $table->foreignId('invoice_transaksi_id')->nullable()->constrained('transaksi')->restrictOnDelete();
            // opening_rincian_id: FK ditambahkan di migration FK-silang
            $table->unsignedBigInteger('opening_rincian_id')->nullable();
            $table->decimal('nominal', 19, 2); // > 0
            $table->foreignId('reversal_of_id')->nullable()->constrained('alokasi_pelunasan')->restrictOnDelete();
            // Kunci target deterministik I:<id> / O:<id>
            $table->string('target_key', 60)->storedAs(
                "CASE WHEN invoice_transaksi_id IS NOT NULL THEN CONCAT('I:', invoice_transaksi_id) "
                ."WHEN opening_rincian_id IS NOT NULL THEN CONCAT('O:', opening_rincian_id) ELSE NULL END"
            );
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['pembayaran_transaksi_id', 'target_key'], 'uq_alokasi_target');
            $table->unique('reversal_of_id', 'uq_alokasi_reversal');
            $table->index('invoice_transaksi_id', 'idx_alokasi_invoice');
            $table->index('opening_rincian_id', 'idx_alokasi_opening');
        });

        // Tepat satu target terisi; nominal > 0.
        DB::statement('ALTER TABLE alokasi_pelunasan ADD CONSTRAINT chk_alokasi_target CHECK (((invoice_transaksi_id IS NOT NULL) + (opening_rincian_id IS NOT NULL)) = 1 AND nominal > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('alokasi_pelunasan');
    }
};
