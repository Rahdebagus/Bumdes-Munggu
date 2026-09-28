<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldo_awal_akun', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('periode_akuntansi')->restrictOnDelete();
            $table->foreignId('unit_usaha_id')->nullable()->constrained('unit_usaha')->restrictOnDelete();
            $table->foreignId('akun_id')->constrained('akun')->restrictOnDelete();
            $table->decimal('saldo_debit', 19, 2)->default(0);
            $table->decimal('saldo_kredit', 19, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // dokumen05: jejak lock per batch
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('source_batch')->nullable();

            // Generated STORED untuk unique multi-NULL-safe (MariaDB 10.4).
            $table->unsignedBigInteger('scope_unit_id')->storedAs('COALESCE(unit_usaha_id, 0)');
            $table->timestamps();

            $table->unique(['periode_id', 'scope_unit_id', 'akun_id'], 'uq_saldo_awal_scope');
            $table->index('akun_id');
        });

        DB::statement('ALTER TABLE saldo_awal_akun ADD CONSTRAINT chk_saldo_awal_sides CHECK (saldo_debit >= 0 AND saldo_kredit >= 0 AND NOT (saldo_debit > 0 AND saldo_kredit > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('saldo_awal_akun');
    }
};
