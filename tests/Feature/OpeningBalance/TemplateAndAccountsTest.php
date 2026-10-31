<?php

namespace Tests\Feature\OpeningBalance;

use App\Domain\OpeningBalance\OpeningImportParser;
use App\Domain\OpeningBalance\PreviewImport;
use App\Livewire\MasterData\Accounts;
use App\Livewire\OpeningBalance\Index as OpeningBalanceIndex;
use App\Models\Akun;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** Petunjuk + unduh format Saldo Awal, dan halaman Akun yang disederhanakan. */
class TemplateAndAccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->admin = User::factory()->create();
    }

    public function test_halaman_saldo_awal_menampilkan_petunjuk_dan_tautan_format(): void
    {
        Livewire::actingAs($this->admin)->test(OpeningBalanceIndex::class)
            ->assertSee('Petunjuk: cara mengisi saldo awal')
            ->assertSee('Unduh format Excel')
            ->assertSee(route('opening-balance.template', 'xlsx'));
    }

    public function test_format_excel_bisa_diunduh_dan_langsung_valid_diimpor(): void
    {
        $res = $this->actingAs($this->admin)->get(route('opening-balance.template', 'xlsx'))->assertOk()->assertDownload('format-saldo-awal.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'sa').'.xlsx';
        file_put_contents($path, $res->streamedContent());

        $book = IOFactory::load($path);
        $this->assertSame(['saldo_awal', 'Petunjuk', 'Daftar Akun', 'Daftar Unit'], $book->getSheetNames());
        $kodes = array_column($book->getSheetByName('Daftar Akun')->toArray(null, false, false, false), 0);
        $this->assertContains('4.4.1', $kodes, 'kode akun tersimpan sebagai teks');
        $this->assertContains('8.0', $kodes);

        // Walau lembar aktif = Petunjuk, parser tetap membaca lembar saldo_awal.
        $book->setActiveSheetIndexByName('Petunjuk');
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        $preview = app(PreviewImport::class)->handle(app(OpeningImportParser::class)->parse($path));
        $this->assertFalse($preview['has_errors'], 'contoh baris valid');
        $this->assertTrue($preview['balanced']);
        $this->assertSame(3, $preview['row_count']);
        $this->assertSame(0, bccomp($preview['total_debit'], '50000000', 2));
        @unlink($path);

        $this->actingAs($this->admin)->get(route('opening-balance.template', 'csv'))
            ->assertOk()->assertSee('kode_akun,unit_kode,saldo_debit,saldo_kredit,keterangan', false);
    }

    public function test_akun_dikelompokkan_dan_akun_jarang_nonaktif(): void
    {
        $this->assertFalse((bool) Akun::where('kode_akun', '3.1.02.00')->value('is_active'), 'Modal Ketahanan Pangan nonaktif');

        Livewire::actingAs($this->admin)->test(Accounts::class)
            ->assertSee('1. Aset (Harta)')
            ->assertSee('6. Biaya')
            ->assertSee('Kas Tunai')
            ->assertDontSee('Modal Ketahanan Pangan')
            ->set('tampilNonaktif', true)
            ->assertSee('Modal Ketahanan Pangan');
    }

    public function test_akun_sistem_tidak_bisa_dinonaktifkan(): void
    {
        $kas = Akun::where('system_key', 'cash')->firstOrFail();

        Livewire::actingAs($this->admin)->test(Accounts::class)
            ->call('toggleStatus', $kas->id)
            ->assertDispatched('toast', type: 'error');

        $this->assertTrue($kas->fresh()->is_active);
    }
}
