<?php

namespace Tests;

use App\Domain\Reporting\LaporanDesaService;
use App\Models\LaporanDesa;
use App\Models\PeriodeAkuntansi;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Draft → ajukan → setujui laporan omzet periode (syarat tutup periode). */
    protected function approveLaporanDesa(PeriodeAkuntansi $periode): LaporanDesa
    {
        $svc = app(LaporanDesaService::class);
        $laporan = $svc->generate($periode, User::factory()->create());
        $svc->submit($laporan, $laporan->createdBy);

        return $svc->approve($laporan->fresh(), User::factory()->pimpinan()->create());
    }
}
