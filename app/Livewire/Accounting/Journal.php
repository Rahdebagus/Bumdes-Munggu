<?php

namespace App\Livewire\Accounting;

use App\Domain\Reporting\LedgerReports;
use App\Enums\TransaksiJenis;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Models\PeriodeAkuntansi;
use App\Models\UnitUsaha;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Jurnal Umum (read-only): hasil double-entry dari dokumen terposting.
 * Tidak dapat diedit — koreksi dilakukan pada dokumennya (Batal Posting →
 * edit → posting ulang) sehingga jurnal ikut berubah lewat pembalik.
 */
#[Layout('components.layouts.app')]
#[Title('Jurnal Umum')]
class Journal extends Component
{
    #[Url]
    public ?int $bulan = null;

    #[Url]
    public ?int $tahun = null;

    #[Url]
    public ?int $unitId = null;

    #[Url]
    public string $jenis = '';

    #[Url]
    public string $q = '';

    public function mount(): void
    {
        $this->bulan ??= (int) now()->month;
        $this->tahun ??= (int) now()->year;
    }

    /** @return array{0: string, 1: string} */
    public function range(): array
    {
        $awal = Carbon::create($this->tahun, $this->bulan, 1);

        return [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()];
    }

    public function render()
    {
        [$from, $to] = $this->range();
        $jurnal = app(LedgerReports::class)->jurnalUmum($from, $to, $this->unitId, $this->jenis ?: null, $this->q ?: null);

        $years = PeriodeAkuntansi::query()->distinct()->pluck('tahun')->push((int) now()->year, $this->tahun)->unique()->sortDesc()->values();

        return view('livewire.accounting.journal', [
            'jurnal' => $jurnal,
            'groups' => collect($jurnal['entries'])->groupBy('transaksi_id'),
            'from' => $from,
            'to' => $to,
            'bulanList' => ReportsIndex::BULAN,
            'tahunList' => $years,
            'units' => UnitUsaha::orderBy('nama_unit')->get(),
            'jenisList' => TransaksiJenis::cases(),
        ]);
    }
}
