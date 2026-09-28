<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldo_awal_rincian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saldo_awal_akun_id')->constrained('saldo_awal_akun')->restrictOnDelete();
            $table->string('jenis', 30); // stok,piutang_usaha,utang_usaha,pinjaman,aset,akumulasi_aset
            $table->string('kode_referensi', 50);
            $table->foreignId('pihak_id')->nullable()->constrained('pihak')->restrictOnDelete();
            $table->foreignId('produk_id')->nullable()->constrained('produk')->restrictOnDelete();
            $table->foreignId('aset_tetap_id')->nullable()->constrained('aset_tetap')->restrictOnDelete();
            $table->foreignId('pinjaman_id')->nullable()->constrained('pinjaman')->restrictOnDelete();
            $table->date('tanggal_asal')->nullable();
            $table->date('jatuh_tempo')->nullable();
            $table->decimal('kuantitas', 18, 4)->nullable();
            $table->decimal('harga_pokok', 19, 6)->nullable();
            $table->decimal('nominal', 19, 2)->default(0); // >= 0
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['saldo_awal_akun_id', 'kode_referensi'], 'uq_rincian_referensi');
            $table->index('jenis');
        });

        DB::statement('ALTER TABLE saldo_awal_rincian ADD CONSTRAINT chk_rincian_nominal CHECK (nominal >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('saldo_awal_rincian');
    }
};
