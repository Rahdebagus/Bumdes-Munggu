<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun', function (Blueprint $table) {
            $table->id();
            $table->string('kode_akun', 20)->unique();
            $table->string('nama_akun', 100);
            $table->string('jenis_akun', 30); // aset,kewajiban,modal,pendapatan,hpp,beban
            $table->string('saldo_normal', 10); // debit | kredit
            $table->boolean('is_active')->default(true);

            // dokumen05
            $table->string('system_key', 50)->nullable()->unique();
            $table->string('report_group', 50)->nullable();
            $table->boolean('is_cash')->default(false);
            $table->timestamps();

            $table->index('jenis_akun');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akun');
    }
};
