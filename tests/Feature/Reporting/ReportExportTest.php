<?php

namespace Tests\Feature\Reporting;

use App\Domain\Reporting\ReportExporter;
use App\Livewire\Reports\Index;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Reporting\Concerns\BuildsFixtureA;
use Tests\TestCase;

/**
 * Ekspor & UI laporan: angka XLSX/PDF/HTML harus sama dengan dataset
 * ReportService (dokumen10, dokumen11). Termasuk sanitasi formula-injection.
 */
class ReportExportTest extends TestCase
{
    use BuildsFixtureA;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtureA();
    }

    private function filters(): array
    {
        return ['from' => self::FROM, 'to' => self::TO, 'asOf' => self::TO, 'unitId' => null];
    }

    public function test_xlsx_ringkasan_cocok_dengan_dataset(): void
    {
        $this->runFixture();
        $result = app(ReportExporter::class)->xlsx('laba-rugi', $this->filters());

        $this->assertTrue(Storage::disk('local')->exists($result['path']));
        $this->assertSame('laba-rugi_2026-01_semua-unit.xlsx', $result['filename']);

        $book = IOFactory::load(Storage::disk('local')->path($result['path']));
        $ringkasan = $book->getSheetByName('Ringkasan');

        // Baris terakhir ringkasan laba rugi = Laba bersih 112.000.
        $labels = [];
        foreach ($ringkasan->getRowIterator(5) as $row) {
            $i = $row->getRowIndex();
            $labels[(string) $ringkasan->getCell("A{$i}")->getValue()] = $ringkasan->getCell("B{$i}")->getValue();
        }
        $this->assertSame(750000.0, (float) $labels['Penjualan bersih']);
        $this->assertSame(500000.0, (float) $labels['Harga pokok penjualan']);
        $this->assertSame(112000.0, (float) $labels['Laba bersih']);

        // Sheet wajib ada: Ringkasan, Rincian, Metadata.
        $this->assertNotNull($book->getSheetByName('Rincian'));
        $this->assertNotNull($book->getSheetByName('Metadata'));
    }

    public function test_xlsx_sanitasi_formula_injection(): void
    {
        // Peminjam dengan nama berawalan '=' tidak boleh menjadi formula.
        $this->peminjam->update(['nama' => '=HYPERLINK("http://x")']);
        $this->runFixture();

        $result = app(ReportExporter::class)->xlsx('kredit', $this->filters());
        $book = IOFactory::load(Storage::disk('local')->path($result['path']));
        $rincian = $book->getSheetByName('Rincian');

        // Kolom B baris 2 = nama peminjam, harus diawali apostrof (disanitasi).
        $nama = (string) $rincian->getCell('B2')->getValue();
        $this->assertStringStartsWith("'=", $nama);
    }

    public function test_pdf_dihasilkan_sebagai_file_privat(): void
    {
        $this->runFixture();
        $result = app(ReportExporter::class)->pdf('neraca', $this->filters());

        $this->assertTrue(Storage::disk('local')->exists($result['path']));
        $this->assertSame('neraca_2026-01_semua-unit.pdf', $result['filename']);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($result['path']));
    }

    public function test_halaman_laporan_menampilkan_angka_live(): void
    {
        $this->runFixture();

        Livewire::actingAs($this->actor)
            ->withQueryParams(['type' => 'laba-rugi'])
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Laporan Laba Rugi')
            ->assertSee('112.000,00'); // laba bersih
    }

    public function test_export_route_mengunduh_attachment(): void
    {
        $this->runFixture();

        $response = $this->actingAs($this->actor)->get(route('reports.export', [
            'type' => 'laba-rugi', 'format' => 'xlsx', 'from' => self::FROM, 'to' => self::TO,
        ]));

        $response->assertOk();
        $response->assertDownload('laba-rugi_2026-01_semua-unit.xlsx');
    }
}
