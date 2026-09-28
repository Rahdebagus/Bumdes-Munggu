<?php

namespace Tests\Feature\OpeningBalance;

use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\OpeningImportParser;
use App\Domain\OpeningBalance\PreviewImport;
use App\Models\PeriodeAkuntansi;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class); // 20 akun dengan system_key
    }

    /** @return array<int, array<string, string>> */
    private function rows(array $rows): array
    {
        return array_map(fn ($r) => array_merge(
            ['kode_akun' => '', 'unit_kode' => '', 'saldo_debit' => '0', 'saldo_kredit' => '0', 'keterangan' => ''],
            $r,
        ), $rows);
    }

    public function test_preview_flags_invalid_rows(): void
    {
        $preview = (new PreviewImport)->handle($this->rows([
            ['kode_akun' => 'NOPE', 'saldo_debit' => '100'],                       // akun tidak ada
            ['kode_akun' => '1-1010', 'saldo_debit' => '100', 'saldo_kredit' => '100'], // dua sisi positif
        ]));

        $this->assertTrue($preview['has_errors']);
        $this->assertNotEmpty($preview['rows'][0]['errors']);
        $this->assertStringContainsString('tidak ditemukan', $preview['rows'][0]['errors'][0]);
        $this->assertStringContainsString('satu sisi', implode(' ', $preview['rows'][1]['errors']));
    }

    public function test_preview_computes_balanced_totals(): void
    {
        $preview = (new PreviewImport)->handle($this->rows([
            ['kode_akun' => '1-1010', 'saldo_debit' => '1000000'],
            ['kode_akun' => '3-3010', 'saldo_kredit' => '1000000'],
        ]));

        $this->assertFalse($preview['has_errors']);
        $this->assertTrue($preview['balanced']);
        $this->assertSame('1000000.00', $preview['total_debit']);
        $this->assertSame('1000000.00', $preview['total_kredit']);
    }

    public function test_commit_inserts_rows_atomically(): void
    {
        $periode = PeriodeAkuntansi::factory()->create();
        $actor = User::factory()->create();

        $preview = (new PreviewImport)->handle($this->rows([
            ['kode_akun' => '1-1010', 'saldo_debit' => '1000000'],
            ['kode_akun' => '3-3010', 'saldo_kredit' => '1000000'],
        ]));

        $count = (new CommitImport)->handle($periode, $preview, $actor);

        $this->assertSame(2, $count);
        $this->assertDatabaseCount('saldo_awal_akun', 2);
    }

    public function test_commit_rejected_when_rows_have_errors(): void
    {
        $periode = PeriodeAkuntansi::factory()->create();
        $actor = User::factory()->create();

        $preview = (new PreviewImport)->handle($this->rows([
            ['kode_akun' => 'NOPE', 'saldo_debit' => '100'],
        ]));

        $this->expectException(ValidationException::class);
        (new CommitImport)->handle($periode, $preview, $actor);
    }

    public function test_reimport_replaces_previous_rows(): void
    {
        $periode = PeriodeAkuntansi::factory()->create();
        $actor = User::factory()->create();
        $commit = new CommitImport;

        $commit->handle($periode, (new PreviewImport)->handle($this->rows([
            ['kode_akun' => '1-1010', 'saldo_debit' => '1000000'],
            ['kode_akun' => '3-3010', 'saldo_kredit' => '1000000'],
        ])), $actor);
        $this->assertDatabaseCount('saldo_awal_akun', 2);

        // Impor ulang (belum dikunci) mengganti isi periode.
        $commit->handle($periode, (new PreviewImport)->handle($this->rows([
            ['kode_akun' => '1-1020', 'saldo_debit' => '500000'],
        ])), $actor);

        $this->assertDatabaseCount('saldo_awal_akun', 1);
        $this->assertDatabaseHas('saldo_awal_akun', ['periode_id' => $periode->id]);
    }

    public function test_parser_reads_csv(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'opening_').'.csv';
        file_put_contents($path, "kode_akun,unit_kode,saldo_debit,saldo_kredit,keterangan\n1-1010,,1000000,0,kas awal\n");

        $rows = (new OpeningImportParser)->parse($path);
        @unlink($path);

        $this->assertCount(1, $rows);
        $this->assertSame('1-1010', $rows[0]['kode_akun']);
        $this->assertSame('1000000', $rows[0]['saldo_debit']);
    }
}
