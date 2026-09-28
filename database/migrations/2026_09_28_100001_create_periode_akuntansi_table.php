<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_akuntansi', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('bulan'); // 1..12
            $table->unsignedSmallInteger('tahun');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('status', 20)->default('terbuka'); // terbuka | ditutup
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();

            // dokumen05: lock saldo awal + reopen + revision
            $table->timestamp('opening_locked_at')->nullable();
            $table->foreignId('opening_locked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamps();

            $table->unique(['tahun', 'bulan']);
            $table->index('status');
        });

        DB::statement('ALTER TABLE periode_akuntansi ADD CONSTRAINT chk_periode_bulan CHECK (bulan BETWEEN 1 AND 12)');
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_akuntansi');
    }
};
