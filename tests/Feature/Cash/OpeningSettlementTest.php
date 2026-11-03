<?php

namespace Tests\Feature\Cash;

use App\Domain\Accounting\PostDocument;
use App\Domain\Accounting\ReverseTransaction;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\OpeningBalance\OpeningItems;
use App\Domain\Reporting\LedgerReports;
use App\Domain\Reporting\ReportService;
use App\Domain\Shared\DomainException;
use App\Enums\SaldoRincianJenis;
use App\Enums\TransaksiStatus;
use App\Livewire\Cash\Settlements;
use App\Livewire\Lending\Payments;
use App\Models\Akun;
use App\Models\SaldoAwalRincian;
use App\Models\Transaksi;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\ProduksiBumdesSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nota piutang & pinjaman pedagang lama dari saldo awal (cutover) dapat
 * ditagih lewat menu Pelunasan Piutang Usaha dan Pembayaran Angsuran, dengan
 * jurnal benar dan subledger tetap = ledger.
 */
class OpeningSettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'seed.admin_email' => 'admin@bumdesmunggu.id',
            'seed.admin_password' => 'rahasia-admin-123',
            'seed.pimpinan_email' => 'pimpinan@bumdesmunggu.id',
            'seed.pimpinan_password' => 'rahasia-pimpinan-123',
            'seed.periode_awal' => null,
        ]);
        // Data Juni 2026 (nota piutang & pinjaman lama) dari seeder alternatif.
        $this->seed([MasterDataSeeder::class, UserSeeder::class, ProduksiBumdesSeeder::class]);
        $this->admin = User::where('email', 'admin@bumdesmunggu.id')->firstOrFail();
    }

    private function rincian(SaldoRincianJenis $jenis, string $kode): SaldoAwalRincian
    {
        return SaldoAwalRincian::where('jenis', $jenis->value)->where('kode_referensi', $kode)->firstOrFail();
    }

    private function assertRekonsiliasiCocok(): void
    {
        foreach (app(ReportService::class)->reconciliation('2026-07-01', '2026-07-31', null, '2026-07-01') as $c) {
            $this->assertTrue($c['match'], "Rekonsiliasi {$c['label']}: {$c['expected']} ≠ {$c['actual']}");
        }
    }

    public function test_nota_piutang_lama_dilunasi_dari_menu_pelunasan_piutang(): void
    {
        $nota = $this->rincian(SaldoRincianJenis::PiutangUsaha, '406'); // 1.650.000, bidang UMUM

        Livewire::actingAs($this->admin)->test(Settlements::class, ['mode' => 'piutang'])
            ->set('pihakId', $nota->pihak_id)
            ->assertSee('Nota 406')
            ->set('tanggal', '2026-07-10')
            ->set("pilih.O{$nota->id}", true)
            ->set("bayar.O{$nota->id}", '1000000')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $kas = Transaksi::where('kategori_kode', 'pelunasan_piutang')->firstOrFail();
        $this->assertSame(TransaksiStatus::Posted, $kas->status);
        $this->assertDatabaseHas('transaksi_detail', [
            'transaksi_id' => $kas->id, 'akun_id' => Akun::where('system_key', 'trade_receivable')->value('id'), 'kredit' => '1000000.00',
        ]);

        $sisa = app(OpeningItems::class)->outstanding(SaldoRincianJenis::PiutangUsaha)->firstWhere('rincian.id', $nota->id);
        $this->assertSame('650000.00', $sisa['sisa']);
        $this->assertSame('751016100.00', app(LedgerReports::class)->piutangHutang('2026-07-31')['piutang']['total']);
        $this->assertRekonsiliasiCocok();
    }

    public function test_setoran_borongan_pinjaman_lama_dengan_bunga(): void
    {
        $alit = $this->rincian(SaldoRincianJenis::Pinjaman, 'PK-055'); // 3.844.000
        $sariati = $this->rincian(SaldoRincianJenis::Pinjaman, 'PK-021'); // 2.258.000

        Livewire::actingAs($this->admin)->test(Payments::class)
            ->assertSee('PK-055')
            ->set('lamaTanggal', '2026-07-05')
            ->set("lamaPokok.{$alit->id}", '61000')->set("lamaBunga.{$alit->id}", '39000')
            ->set("lamaPokok.{$sariati->id}", '172000')->set("lamaBunga.{$sariati->id}", '28000')
            ->call('terimaLama')
            ->assertHasNoErrors();

        $kas = Transaksi::where('kategori_kode', 'setoran_pinjaman_lama')->firstOrFail();
        $this->assertSame(TransaksiStatus::Posted, $kas->status);
        $this->assertSame('300000.00', (string) $kas->total);
        $this->assertDatabaseHas('transaksi_detail', ['transaksi_id' => $kas->id, 'akun_id' => Akun::where('system_key', 'loan_receivable')->value('id'), 'kredit' => '233000.00']);
        $this->assertDatabaseHas('transaksi_detail', ['transaksi_id' => $kas->id, 'akun_id' => Akun::where('system_key', 'interest_income')->value('id'), 'kredit' => '67000.00']);

        $kredit = app(ReportService::class)->kredit('2026-07-31');
        $this->assertSame('125890000.00', $kredit['total_sisa_pokok']);
        $this->assertRekonsiliasiCocok();

        // Batal posting memulihkan sisa pinjaman.
        app(ReverseTransaction::class)->reverse($kas, '2026-07-06', 'salah input', $this->admin, 'rev-'.$kas->id, 'h');
        $this->assertSame('126123000.00', app(ReportService::class)->kredit('2026-07-31')['total_sisa_pokok']);
        $this->assertRekonsiliasiCocok();
    }

    public function test_setoran_bunga_saja_diterima_tanpa_mengurangi_pokok(): void
    {
        $heriyani = $this->rincian(SaldoRincianJenis::Pinjaman, 'PK-008');

        Livewire::actingAs($this->admin)->test(Payments::class)
            ->set('lamaTanggal', '2026-07-05')
            ->set("lamaBunga.{$heriyani->id}", '22000')
            ->call('terimaLama')
            ->assertHasNoErrors();

        $this->assertSame('126123000.00', app(ReportService::class)->kredit('2026-07-31')['total_sisa_pokok']);
        $this->assertRekonsiliasiCocok();
    }

    public function test_setoran_melebihi_sisa_pinjaman_ditolak(): void
    {
        $alit = $this->rincian(SaldoRincianJenis::Pinjaman, 'PK-055');

        Livewire::actingAs($this->admin)->test(Payments::class)
            ->set('lamaTanggal', '2026-07-05')
            ->set("lamaPokok.{$alit->id}", '5000000')
            ->call('terimaLama')
            ->assertHasErrors("lama.{$alit->id}");

        $this->assertSame(0, Transaksi::where('kategori_kode', 'setoran_pinjaman_lama')->count());
    }

    public function test_nota_lama_tidak_bisa_dilunasi_dengan_kategori_yang_salah(): void
    {
        $nota = $this->rincian(SaldoRincianJenis::PiutangUsaha, '406');

        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-07-10', 'kategori_kode' => 'setoran_pinjaman_lama',
            'akun_kas_id' => Akun::where('system_key', 'cash')->value('id'),
            'allocations' => [['opening_rincian_id' => $nota->id, 'nominal' => '100000']],
        ], $this->admin);

        $this->expectException(DomainException::class);
        app(PostDocument::class)->handle($draft, $this->admin);
    }
}
