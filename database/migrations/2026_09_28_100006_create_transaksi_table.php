<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_transaksi', 30)->unique();
            $table->date('tanggal');
            $table->foreignId('periode_id')->constrained('periode_akuntansi')->restrictOnDelete();
            $table->foreignId('unit_usaha_id')->nullable()->constrained('unit_usaha')->restrictOnDelete();
            $table->foreignId('pihak_id')->nullable()->constrained('pihak')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // pembuat
            $table->string('jenis_transaksi', 30); // pembelian,penjualan,pemasukan,pengeluaran,pencairan,angsuran,penyusutan,penyesuaian
            $table->string('sumber_tipe', 30)->nullable(); // polimorfik allowlist (bukan FK)
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->decimal('total', 19, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('draft'); // draft,posted,reversed

            // dokumen05: pembayaran / kas / kategori / pajak
            $table->string('metode_pembayaran', 20)->nullable(); // tunai | kredit
            $table->foreignId('akun_kas_id')->nullable()->constrained('akun')->restrictOnDelete();
            $table->foreignId('akun_lawan_id')->nullable()->constrained('akun')->restrictOnDelete();
            $table->string('kategori_kode', 50)->nullable();
            $table->date('jatuh_tempo')->nullable();
            $table->decimal('subtotal', 19, 2)->default(0);
            $table->decimal('diskon_total', 19, 2)->default(0);
            $table->decimal('pajak_total', 19, 2)->default(0);

            // reversal + idempotency + optimistic version (diisi service tahap04)
            $table->foreignId('reversal_of_id')->nullable()->constrained('transaksi')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->char('request_key', 36)->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->text('reversal_reason')->nullable();
            $table->date('reversal_date')->nullable();
            $table->string('policy_version', 30)->nullable();
            $table->string('posting_key', 100)->nullable();
            $table->timestamps();

            $table->unique('reversal_of_id', 'uq_transaksi_reversal'); // satu pembalik per sumber
            $table->unique('request_key', 'uq_transaksi_request_key');
            $table->unique('posting_key', 'uq_transaksi_posting_key');
            $table->index(['periode_id', 'unit_usaha_id', 'tanggal', 'id'], 'idx_transaksi_periode');
            $table->index(['pihak_id', 'tanggal'], 'idx_transaksi_pihak');
            $table->index(['status', 'tanggal'], 'idx_transaksi_status');
            $table->index(['sumber_tipe', 'sumber_id'], 'idx_transaksi_sumber');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
