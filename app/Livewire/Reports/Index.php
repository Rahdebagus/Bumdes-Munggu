<?php

namespace App\Livewire\Reports;

use App\Domain\Reporting\ReportDataset;
use App\Domain\Reporting\ReportService;
use App\Models\PeriodeAkuntansi;
use App\Models\UnitUsaha;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Laporan')]
class Index extends Component
{
    #[Url]
    public string $type = 'laba-rugi';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public ?int $unitId = null;

    public string $cutoverFrom = '2026-01-01';

    public function mount(): void
    {
        $cutover = PeriodeAkuntansi::whereNotNull('opening_locked_at')->orderBy('tanggal_mulai')->first();
        $terbaru = PeriodeAkuntansi::orderByDesc('tahun')->orderByDesc('bulan')->first();

        $this->cutoverFrom = $cutover?->tanggal_mulai?->toDateString() ?? now()->startOfYear()->toDateString();
        $this->from = $this->from ?: $this->cutoverFrom;
        $this->to = $this->to ?: ($terbaru?->tanggal_selesai?->toDateString() ?? now()->toDateString());
    }

    /** @return array{from: string, to: string, asOf: string, unitId: ?int, cutoverFrom: string} */
    public function filters(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'asOf' => $this->to,
            'unitId' => $this->unitId,
            'cutoverFrom' => $this->cutoverFrom,
        ];
    }

    public function dataset(): array
    {
        return app(ReportDataset::class)->build($this->type, $this->filters());
    }

    public function reconciliation(): array
    {
        return app(ReportService::class)->reconciliation($this->from, $this->to, $this->unitId, $this->cutoverFrom);
    }

    public function warnings(): array
    {
        return app(ReportService::class)->reconciliationWarnings($this->to, $this->unitId);
    }

    public function units()
    {
        return UnitUsaha::orderBy('nama_unit')->get();
    }

    public function typeOptions(): array
    {
        return ReportDataset::TYPES;
    }

    public function render()
    {
        return view('livewire.reports.index');
    }
}
