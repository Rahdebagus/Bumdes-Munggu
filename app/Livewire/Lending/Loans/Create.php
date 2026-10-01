<?php

namespace App\Livewire\Lending\Loans;

use App\Domain\Lending\SaveLoanDraft;
use App\Domain\Lending\ScheduleCalculator;
use App\Domain\Shared\DomainException;
use App\Models\Pihak;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Buat Pinjaman')]
class Create extends Component
{
    use AuthorizesRequests;

    public ?int $unit_usaha_id = null;

    public ?int $peminjam_id = null;

    public string $tanggal_pinjaman = '';

    public string $pokok_pinjaman = '0';

    public string $persentase_bunga = '0';

    public int $tenor = 12;

    public string $basis_bunga = 'flat_total';

    public string $tanggal_angsuran_pertama = '';

    public function mount(): void
    {
        $this->tanggal_pinjaman = now()->toDateString();
        $this->tanggal_angsuran_pertama = now()->addMonth()->toDateString();
    }

    public function preview(ScheduleCalculator $calc): ?array
    {
        if (! is_numeric($this->pokok_pinjaman) || ! is_numeric($this->persentase_bunga) || $this->tenor < 1
            || (float) $this->pokok_pinjaman <= 0 || ! $this->tanggal_angsuran_pertama) {
            return null;
        }

        try {
            return [
                'total_bunga' => $calc->totalBunga($this->pokok_pinjaman, $this->persentase_bunga, $this->tenor, $this->basis_bunga),
                'jadwal' => $calc->build($this->pokok_pinjaman, $this->persentase_bunga, $this->tenor, $this->basis_bunga, $this->tanggal_angsuran_pertama),
            ];
        } catch (DomainException) {
            return null;
        }
    }

    public function save(SaveLoanDraft $action)
    {
        $this->authorize('perform-admin-mutation');

        $data = $this->validate([
            'unit_usaha_id' => ['required', 'integer', 'exists:unit_usaha,id'],
            'peminjam_id' => ['required', 'integer', 'exists:pihak,id'],
            'tanggal_pinjaman' => ['required', 'date'],
            'pokok_pinjaman' => ['required', 'numeric', 'gt:0'],
            'persentase_bunga' => ['required', 'numeric', 'min:0'],
            'tenor' => ['required', 'integer', 'min:1', 'max:120'],
            'basis_bunga' => ['required', 'in:flat_total,flat_bulanan'],
            'tanggal_angsuran_pertama' => ['required', 'date'],
        ]);

        $loan = $action->handle($data, auth()->user());
        session()->flash('status', 'Draft pinjaman dibuat. Cairkan dari halaman detail.');

        return $this->redirectRoute('loans.show', $loan, navigate: true);
    }

    public function render()
    {
        return view('livewire.lending.loans.create', [
            'units' => UnitUsaha::where('status', 'aktif')->where('jenis_usaha', 'kredit')->orderBy('nama_unit')->get(),
            'peminjam' => Pihak::where('status', 'aktif')->where('jenis_pihak', 'peminjam')->orderBy('nama')->get(),
            'previewData' => $this->preview(app(ScheduleCalculator::class)),
        ]);
    }
}
