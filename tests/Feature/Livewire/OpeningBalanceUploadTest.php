<?php

namespace Tests\Feature\Livewire;

use App\Livewire\OpeningBalance\Index;
use App\Models\PeriodeAkuntansi;
use App\Models\SaldoAwalAkun;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** Alur nyata halaman Saldo Awal: unggah berkas → preview → commit → kunci. */
class OpeningBalanceUploadTest extends TestCase
{
    use RefreshDatabase;

    private function berkas(string $path, string $nama): File
    {
        // Isi berkas sungguhan (bukan dummy) supaya parser benar-benar diuji.
        return UploadedFile::fake()->createWithContent($nama, file_get_contents($path));
    }

    private function template(string $nama): File
    {
        return $this->berkas(base_path('docs/templates/'.$nama), $nama);
    }

    /** @return array{0: User, 1: PeriodeAkuntansi} */
    private function siapkan(): array
    {
        $this->seed(MasterDataSeeder::class);

        $admin = User::create([
            'nama' => 'Admin Uji', 'email' => 'admin.uji@contoh.test',
            'role' => 'admin', 'status' => 'aktif', 'password' => bcrypt('rahasia12345'),
        ]);

        $periode = PeriodeAkuntansi::create([
            'bulan' => 10, 'tahun' => 2026,
            'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-10-31',
            'status' => 'terbuka', 'revision' => 0,
        ]);

        return [$admin, $periode];
    }

    public function test_template_mulai_cepat_bisa_preview_commit_dan_kunci(): void
    {
        [$admin, $periode] = $this->siapkan();

        $komponen = Livewire::actingAs($admin)
            ->test(Index::class, ['periodeId' => $periode->id])
            ->set('file', $this->template('saldo-awal-mulai-cepat.xlsx'))
            ->call('preview')
            ->assertHasNoErrors();

        $preview = $komponen->get('preview');
        $this->assertNotNull($preview, 'preview harus terisi');
        $this->assertSame(5, $preview['row_count']);
        $this->assertFalse($preview['has_errors']);
        $this->assertTrue($preview['balanced']);
        $this->assertSame('30000000.00', $preview['total_debit']);

        $komponen->call('commit')->assertHasNoErrors();
        $this->assertSame(5, SaldoAwalAkun::where('periode_id', $periode->id)->count());

        $komponen->call('lock')->assertHasNoErrors();
        $this->assertNotNull($periode->fresh()->opening_locked_at);
    }

    public function test_template_versi_csv_juga_bisa_preview(): void
    {
        [$admin, $periode] = $this->siapkan();

        $komponen = Livewire::actingAs($admin)
            ->test(Index::class, ['periodeId' => $periode->id])
            ->set('file', $this->template('saldo-awal-mulai-cepat.csv'))
            ->call('preview')
            ->assertHasNoErrors();

        $this->assertSame(5, $komponen->get('preview')['row_count']);
        $this->assertTrue($komponen->get('preview')['balanced']);
    }

    public function test_akun_kontrol_tanpa_rincian_tertahan_saat_kunci(): void
    {
        [$admin, $periode] = $this->siapkan();

        $komponen = Livewire::actingAs($admin)
            ->test(Index::class, ['periodeId' => $periode->id])
            ->set('file', $this->berkas(
                base_path('tests/Fixtures/opening-balance/akun-kontrol-tanpa-rincian.xlsx'),
                'akun-kontrol-tanpa-rincian.xlsx',
            ))
            ->call('preview')
            ->assertHasNoErrors();

        $this->assertTrue($komponen->get('preview')['balanced'], 'seimbang, tetapi rincian kontrol kosong');

        $komponen->call('commit')->assertHasNoErrors();
        $komponen->call('lock')->assertHasErrors('lock');
    }
}
