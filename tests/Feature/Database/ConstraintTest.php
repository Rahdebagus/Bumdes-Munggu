<?php

namespace Tests\Feature\Database;

use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\SaldoAwalAkun;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_journal_line_is_accepted(): void
    {
        $trx = Transaksi::factory()->create();
        $akun = Akun::factory()->create();

        $detail = TransaksiDetail::create([
            'transaksi_id' => $trx->id,
            'akun_id' => $akun->id,
            'debit' => 100_000,
            'kredit' => 0,
            'nomor_baris' => 1,
        ]);

        $this->assertDatabaseHas('transaksi_detail', ['id' => $detail->id]);
    }

    public function test_journal_line_rejects_both_debit_and_kredit_positive(): void
    {
        $trx = Transaksi::factory()->create();
        $akun = Akun::factory()->create();

        $this->expectException(QueryException::class);
        TransaksiDetail::create([
            'transaksi_id' => $trx->id,
            'akun_id' => $akun->id,
            'debit' => 100_000,
            'kredit' => 100_000, // melanggar chk_detail_debit_kredit
            'nomor_baris' => 1,
        ]);
    }

    public function test_periode_rejects_month_out_of_range(): void
    {
        $this->expectException(QueryException::class);
        DB::table('periode_akuntansi')->insert([
            'bulan' => 13, // melanggar chk_periode_bulan
            'tahun' => 2026,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-01-31',
            'status' => 'terbuka',
            'revision' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_saldo_awal_rejects_both_sides_positive(): void
    {
        $periode = PeriodeAkuntansi::factory()->create();
        $akun = Akun::factory()->create();
        $user = User::factory()->create();

        $this->expectException(QueryException::class);
        SaldoAwalAkun::create([
            'periode_id' => $periode->id,
            'akun_id' => $akun->id,
            'created_by' => $user->id,
            'saldo_debit' => 100_000,
            'saldo_kredit' => 100_000, // melanggar chk_saldo_awal_sides
        ]);
    }

    public function test_saldo_awal_scope_unique_via_generated_column(): void
    {
        $periode = PeriodeAkuntansi::factory()->create();
        $akun = Akun::factory()->create();
        $user = User::factory()->create();

        SaldoAwalAkun::create([
            'periode_id' => $periode->id,
            'akun_id' => $akun->id,
            'created_by' => $user->id,
            'saldo_debit' => 100_000,
            'saldo_kredit' => 0,
            'unit_usaha_id' => null,
        ]);

        // scope_unit_id = COALESCE(NULL,0) = 0 → duplikat periode+akun+scope
        $this->expectException(QueryException::class);
        SaldoAwalAkun::create([
            'periode_id' => $periode->id,
            'akun_id' => $akun->id,
            'created_by' => $user->id,
            'saldo_debit' => 50_000,
            'saldo_kredit' => 0,
            'unit_usaha_id' => null,
        ]);
    }

    public function test_nomor_transaksi_is_unique(): void
    {
        Transaksi::factory()->create(['nomor_transaksi' => 'TRX-DUP']);

        $this->expectException(QueryException::class);
        Transaksi::factory()->create(['nomor_transaksi' => 'TRX-DUP']);
    }
}
