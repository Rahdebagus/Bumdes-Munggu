<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran_angsuran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksi')->restrictOnDelete(); // jenis angsuran
            $table->foreignId('angsuran_id')->constrained('angsuran')->restrictOnDelete();
            $table->decimal('pokok', 19, 2)->default(0);
            $table->decimal('bunga', 19, 2)->default(0);
            $table->decimal('denda', 19, 2)->default(0);
            $table->foreignId('reversal_of_id')->nullable()->constrained('pembayaran_angsuran')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['transaksi_id', 'angsuran_id'], 'uq_pembayaran_angsuran');
            $table->unique('reversal_of_id', 'uq_pembayaran_reversal');
            $table->index('angsuran_id', 'idx_pembayaran_angsuran_id');
        });

        // total > 0, tiap komponen >= 0 (arah reversal diturunkan dari transaksi.reversal_of_id)
        DB::statement('ALTER TABLE pembayaran_angsuran ADD CONSTRAINT chk_pembayaran_total CHECK (pokok >= 0 AND bunga >= 0 AND denda >= 0 AND (pokok + bunga + denda) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_angsuran');
    }
};
