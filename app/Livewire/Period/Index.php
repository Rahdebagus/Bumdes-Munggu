<?php

namespace App\Livewire\Period;

use App\Domain\Period\ClosePeriod;
use App\Domain\Period\ReopenPeriod;
use App\Domain\Shared\DomainException;
use App\Models\PeriodeAkuntansi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Periode Akuntansi')]
class Index extends Component
{
    use AuthorizesRequests;

    public ?int $reopenId = null;

    public string $reopenReason = '';

    public function periods()
    {
        return PeriodeAkuntansi::orderByDesc('tahun')->orderByDesc('bulan')->get();
    }

    /** Pratinjau blocker/warning penutupan satu periode. */
    public function closeInfo(PeriodeAkuntansi $periode): array
    {
        $svc = app(ClosePeriod::class);

        return ['blockers' => $svc->blockers($periode), 'warnings' => $svc->warnings($periode)];
    }

    public function close(int $id): void
    {
        $this->authorize('manage-period');
        $periode = PeriodeAkuntansi::findOrFail($id);

        try {
            app(ClosePeriod::class)->handle($periode, auth()->user());
            session()->flash('status', "Periode {$periode->bulan}/{$periode->tahun} ditutup.");
        } catch (DomainException $e) {
            $this->addError('periode', $e->getMessage());
        }
    }

    public function startReopen(int $id): void
    {
        $this->authorize('manage-period');
        $this->reopenId = $id;
        $this->reopenReason = '';
    }

    public function reopen(): void
    {
        $this->authorize('manage-period');
        $periode = PeriodeAkuntansi::findOrFail($this->reopenId);

        try {
            app(ReopenPeriod::class)->handle($periode, auth()->user(), $this->reopenReason);
            session()->flash('status', "Periode {$periode->bulan}/{$periode->tahun} dibuka kembali.");
            $this->reopenId = null;
            $this->reopenReason = '';
        } catch (DomainException $e) {
            $this->addError('reopen', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.period.index');
    }
}
