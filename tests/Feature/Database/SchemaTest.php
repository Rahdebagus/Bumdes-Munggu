<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    /** 18 tabel inti + 4 pendukung = 22 tabel bisnis (users termasuk inti). */
    public function test_all_business_tables_exist(): void
    {
        $tables = [
            // 18 inti (selain users yang dibuat tahap01)
            'unit_usaha', 'periode_akuntansi', 'akun', 'pihak', 'produk', 'saldo_awal_akun',
            'transaksi', 'transaksi_detail', 'transaksi_barang', 'pinjaman', 'angsuran',
            'aset_tetap', 'bukti_transaksi', 'laporan_desa', 'log_aktivitas',
            'stok_opname', 'stok_opname_detail',
            // 4 pendukung
            'pembayaran_angsuran', 'alokasi_pelunasan', 'mutasi_stok', 'saldo_awal_rincian',
        ];

        foreach ($tables as $t) {
            $this->assertTrue(Schema::hasTable($t), "Tabel {$t} tidak ada");
        }

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumns('users', ['nama', 'role', 'status']));
    }

    public function test_generated_and_key_columns_present(): void
    {
        $this->assertTrue(Schema::hasColumn('saldo_awal_akun', 'scope_unit_id'));
        $this->assertTrue(Schema::hasColumn('alokasi_pelunasan', 'target_key'));
        $this->assertTrue(Schema::hasColumn('akun', 'system_key'));
        $this->assertTrue(Schema::hasColumn('transaksi', 'request_key'));
    }
}
