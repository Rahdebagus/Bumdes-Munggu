<?php

namespace Tests\Feature\Qa;

use App\Domain\Accounting\PostingService;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\Reporting\ReportService;
use App\Domain\Shared\DomainException;
use App\Domain\Trading\SaveTradingDraft;
use App\Enums\TransaksiJenis;
use App\Livewire\Accounting\Transactions\Show;
use App\Models\Akun;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Feature\Reporting\Concerns\BuildsFixtureA;
use Tests\TestCase;

/**
 * QA lintas modul (dokumen11 §Matriks) — mengisi celah yang belum dicakup tes
 * tahap sebelumnya. Dijalankan di MariaDB (bumdes_munggu_test). Lihat
 * docs/QA-TRACEABILITY.md untuk pemetaan T01–T26 → tes.
 */
class QaMatrixTest extends TestCase
{
    use BuildsFixtureA;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtureA();
    }

    /** T03: draft penjualan tersimpan tetapi belum ada efek jurnal/ledger. */
    public function test_t03_draft_tidak_mengubah_ledger(): void
    {
        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => '2026-01-10', 'unit_usaha_id' => $this->dagang->id, 'pihak_id' => null, 'metode_pembayaran' => 'tunai',
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => '1', 'harga' => '150000', 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Penjualan, $this->actor);

        $this->assertSame('draft', $draft->status->value);
        $this->assertSame(0, TransaksiDetail::where('transaksi_id', $draft->id)->count());
        $this->assertNull($draft->posted_at);
    }

    /** T08: posting dengan akun lawan nonaktif ditolak JournalValidator. */
    public function test_t08_posting_akun_nonaktif_ditolak(): void
    {
        $akunMati = Akun::create([
            'kode_akun' => '6-9999', 'nama_akun' => 'Beban Nonaktif', 'jenis_akun' => 'beban',
            'saldo_normal' => 'debit', 'report_group' => 'beban', 'is_cash' => false, 'is_active' => false,
        ]);

        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-10', 'unit_usaha_id' => $this->dagang->id,
            'kategori_kode' => 'beban', 'akun_kas_id' => $this->akunId('cash'),
            'akun_lawan_id' => $akunMati->id, 'nominal' => '10000',
        ], $this->actor);

        try {
            app(PostingService::class)->post($draft, 1, $this->actor, 't08', 'h');
            $this->fail('Seharusnya INVALID_STATE (akun nonaktif).');
        } catch (DomainException $e) {
            $this->assertSame('INVALID_STATE', $e->domainCode);
        }

        $this->assertSame('draft', $draft->fresh()->status->value);
        $this->assertSame(0, TransaksiDetail::where('transaksi_id', $draft->id)->count());
    }

    /** T17: filter unit memisahkan saldo; jumlah per-unit = konsolidasi. */
    public function test_t17_filter_unit_memisahkan_saldo(): void
    {
        $this->runFixture();
        $svc = app(ReportService::class);

        $dagang = $svc->labaRugi(self::FROM, self::TO, $this->dagang->id);
        $kredit = $svc->labaRugi(self::FROM, self::TO, $this->kredit->id);
        $konsolidasi = $svc->labaRugi(self::FROM, self::TO);

        $this->assertSame('100000.00', $dagang['laba_bersih']);        // dagang: 750-500-50-100
        $this->assertSame('0.00', $dagang['pendapatan_kredit']);
        $this->assertSame('12000.00', $kredit['pendapatan_kredit']);   // bunga angsuran
        $this->assertSame('12000.00', $kredit['laba_bersih']);
        $this->assertSame('112000.00', $konsolidasi['laba_bersih']);

        // Neraca per unit: piutang pinjaman hanya di unit kredit.
        $this->assertSame('1100000.00', $svc->neraca(self::TO, $this->kredit->id, self::FROM)['piutang_pinjaman']);
        $this->assertSame('0.00', $svc->neraca(self::TO, $this->dagang->id, self::FROM)['piutang_pinjaman']);
    }

    /** T20: unggah bukti menolak MIME terlarang dan ukuran >5MiB. */
    public function test_t20_upload_bukti_menolak_mime_dan_ukuran(): void
    {
        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-10', 'unit_usaha_id' => $this->dagang->id,
            'kategori_kode' => 'jasa', 'akun_kas_id' => $this->akunId('cash'),
            'akun_lawan_id' => $this->akunId('sales'), 'nominal' => '100000',
        ], $this->actor);
        app(PostingService::class)->post($draft, 1, $this->actor, 't20', 'h');
        $trx = Transaksi::findOrFail($draft->id);

        // MIME terlarang (exe).
        Livewire::actingAs($this->actor)->test(Show::class, ['transaksi' => $trx])
            ->set('buktiFile', UploadedFile::fake()->create('virus.exe', 100))
            ->call('uploadBukti')
            ->assertHasErrors(['buktiFile']);

        // Ukuran > 5MiB (5120 KB).
        Livewire::actingAs($this->actor)->test(Show::class, ['transaksi' => $trx])
            ->set('buktiFile', UploadedFile::fake()->create('besar.pdf', 6000))
            ->call('uploadBukti')
            ->assertHasErrors(['buktiFile']);
    }

    /** T21: unduh bukti tanpa login dialihkan ke login (route di grup auth). */
    public function test_t21_unduh_bukti_tanpa_login_ditolak(): void
    {
        $this->get(route('evidence.download', 1))->assertRedirect(route('login'));
    }
}
