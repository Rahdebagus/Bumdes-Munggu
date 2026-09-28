<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only: hanya created_at (tanpa updated_at).
        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('modul', 50);
            $table->string('aksi', 30);
            $table->string('referensi_tipe', 30)->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->json('nilai_lama')->nullable();
            $table->json('nilai_baru')->nullable();
            $table->text('alasan')->nullable();

            // dokumen05: idempotency transition (bukan data sensitif)
            $table->char('request_key', 36)->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->json('result_ref')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->unique('request_key', 'uq_log_request_key');
            $table->index(['referensi_tipe', 'referensi_id', 'created_at'], 'idx_log_referensi');
            $table->index(['user_id', 'created_at'], 'idx_log_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
    }
};
