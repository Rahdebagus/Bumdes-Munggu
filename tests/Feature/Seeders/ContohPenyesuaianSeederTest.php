<?php

namespace Tests\Feature\Seeders;

use App\Domain\Accounting\PostDocument;
use App\Domain\Accounting\PostingService;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\PreviewImport;
use App\Domain\Reporting\ReportService;
use App\Enums\TransaksiJenis;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
use Database\Seeders\ContohPenyesuaianSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ContohPenyesuaianSeederTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '2026-01-01';

    private const TO = '2026-01-31';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->admin = User::factory()->create();
        $periode = PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka']);

        $rows = [
            ['kode_akun' => '1.1.01.01', 'unit_kode' => '', 'saldo_debit' => '5000000', 'saldo_kredit' => '0', 'keterangan' => ''],
            ['kode_akun' => '3.1.01.00', 'unit_kode' => '', 'saldo_debit' => '0', 'saldo_kredit' => '5000000', 'keterangan' => ''],
        ];
        (new CommitImport)->handle($periode, (new PreviewImport)->handle($rows), $this->admin);
        app(LockOpeningBalance::class)->handle($periode, $this->admin);

        // Omzet 500.000 → taksiran pajak 0,5% = 2.500.
        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-10', 'kategori_kode' => 'jasa',
            'akun_kas_id' => $this->akun('1.1.01.01'), 'akun_lawan_id' => (int) Akun::where('system_key', 'sales')->value('id'),
            'nominal' => '500000',
        ], $this->admin);
        app(PostDocument::class)->handle($draft, $this->admin);
    }

    private function akun(string $kode): int
    {
        return (int) Akun::where('kode_akun', $kode)->value('id');
    }

    private function contoh()
    {
        return Transaksi::where('keterangan', 'like', ContohPenyesuaianSeeder::PREFIX.'%')->orderBy('id')->get();
    }

    public function test_membuat_draft_contoh_tanpa_mengubah_buku(): void
    {
        $svc = app(ReportService::class);
        $revisiSebelum = $svc->dataRevision(self::FROM, self::TO);

        $this->seed(ContohPenyesuaianSeeder::class);

        $contoh = $this->contoh();
        $this->assertCount(3, $contoh->where('jenis_transaksi', TransaksiJenis::Penyesuaian));
        $this->assertCount(1, $contoh->where('jenis_transaksi', TransaksiJenis::Pengeluaran));
        $this->assertTrue($contoh->every(fn ($t) => $t->status->value === 'draft'));
        $this->assertSame($revisiSebelum, $svc->dataRevision(self::FROM, self::TO), 'Seeder hanya draft; buku tidak berubah.');

        $pajak = $contoh->firstWhere('keterangan', ContohPenyesuaianSeeder::PREFIX.' Taksiran PPh final 0,5% dari omzet periode');
        $this->assertSame('2500.00', (string) TransaksiDetail::where('transaksi_id', $pajak->id)->where('akun_id', $this->akun('8.0'))->value('debit'));

        // Dijalankan ulang → tidak menggandakan.
        $this->seed(ContohPenyesuaianSeeder::class);
        $this->assertCount(4, $this->contoh());
    }

    public function test_setelah_diposting_rekonsiliasi_tetap_cocok(): void
    {
        $this->seed(ContohPenyesuaianSeeder::class);

        foreach ($this->contoh() as $trx) {
            $trx->jenis_transaksi === TransaksiJenis::Penyesuaian
                ? app(PostingService::class)->post($trx, $trx->version, $this->admin, 'contoh-'.$trx->id, 'h')
                : app(PostDocument::class)->handle($trx, $this->admin);
        }

        $net = app(ReportService::class)->accountNet(self::FROM, self::TO, includeOpening: false);
        $this->assertSame('1500000.00', $net[$this->akun('6.1.1')]);
        $this->assertSame('150000.00', $net[$this->akun('6.1.2')]);
        $this->assertSame('0.00', $net[$this->akun('6.1.3')], 'Koreksi menetralkan Biaya Kantor.');
        $this->assertSame('-1500000.00', $net[$this->akun('2.1.00.02')]);
        $this->assertSame('-2500.00', $net[$this->akun('2.1.00.03')]);

        $checks = collect(app(ReportService::class)->reconciliation(self::FROM, self::TO, null, self::FROM));
        $this->assertTrue($checks->every(fn ($c) => $c['match']), 'Semua rekonsiliasi harus cocok.');
    }

    public function test_menolak_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        (new ContohPenyesuaianSeeder)->run();
    }
}
