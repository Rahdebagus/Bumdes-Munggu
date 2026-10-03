<?php

namespace Tests\Feature\FixedAssets;

use App\Domain\Accounting\ReverseTransaction;
use App\Domain\FixedAssets\PostDepreciation;
use App\Domain\FixedAssets\RegisterAsset;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Models\Akun;
use App\Models\AsetTetap;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepreciationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    /** @var array<int, PeriodeAkuntansi> */
    private array $periode = [];

    private AsetTetap $aset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->actor = User::factory()->create();
        for ($b = 1; $b <= 8; $b++) {
            $this->periode[$b] = PeriodeAkuntansi::factory()->bulan($b)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        }
        $unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();
        $this->aset = app(RegisterAsset::class)->handle([
            'kode_aset' => 'AST-001', 'unit_usaha_id' => $unit->id, 'nama_aset' => 'Mesin',
            'tanggal_perolehan' => '2026-01-05', 'harga_perolehan' => '1000000', 'nilai_residu' => '100000',
            'umur_manfaat' => 7, 'tanggal_mulai_susut' => '2026-01-31',
        ], $this->actor);
    }

    private function postDep(int $bulan): Transaksi
    {
        return app(PostDepreciation::class)->handle($this->aset, $this->periode[$bulan], $this->actor, 'dep-'.$bulan, 'h');
    }

    private function amount(Transaksi $trx, string $key, string $side): string
    {
        return Decimal::of((string) TransaksiDetail::where('transaksi_id', $trx->id)
            ->where('akun_id', (int) Akun::where('system_key', $key)->value('id'))->sum($side));
    }

    public function test_posts_monthly_depreciation(): void
    {
        $trx = $this->postDep(1);

        $this->assertSame('128571.43', $trx->total);
        $this->assertSame('128571.43', $this->amount($trx, 'depreciation_expense', 'debit'));
        $this->assertSame('128571.43', $this->amount($trx, 'accumulated_depreciation', 'kredit'));
    }

    public function test_duplicate_period_rejected(): void
    {
        $this->postDep(1);

        try {
            $this->postDep(1);
            $this->fail('Seharusnya DEPRECIATION_EXISTS');
        } catch (DomainException $e) {
            $this->assertSame('DEPRECIATION_EXISTS', $e->domainCode);
        }
    }

    public function test_full_life_caps_at_basis(): void
    {
        $total = '0';
        for ($b = 1; $b <= 7; $b++) {
            $total = Decimal::add($total, $this->postDep($b)->total);
        }
        $this->assertSame('900000.00', $total); // persis basis

        // Bulan ke-8: sudah tersusut penuh.
        try {
            $this->postDep(8);
            $this->fail('Seharusnya ditolak (penuh)');
        } catch (DomainException $e) {
            $this->assertSame('INVALID_STATE', $e->domainCode);
        }
    }

    public function test_reverse_then_repost_allowed(): void
    {
        $trx = $this->postDep(1);

        app(ReverseTransaction::class)->reverse($trx, $trx->tanggal->toDateString(), 'koreksi', $this->actor, 'rev-dep', 'h');
        $this->assertNull($trx->fresh()->posting_key); // key dibebaskan

        // Repost periode yang sama boleh, nominal dihitung ulang.
        $repost = $this->postDep(1);
        $this->assertSame('128571.43', $repost->total);
    }
}
