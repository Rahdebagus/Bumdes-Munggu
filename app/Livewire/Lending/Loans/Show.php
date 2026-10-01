<?php

namespace App\Livewire\Lending\Loans;

use App\Domain\Accounting\PostingService;
use App\Domain\Lending\DisburseLoan;
use App\Domain\Lending\SaveInstallmentPayment;
use App\Domain\Shared\DomainException;
use App\Enums\PinjamanStatus;
use App\Models\Akun;
use App\Models\Pinjaman;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use AuthorizesRequests;

    public Pinjaman $pinjaman;

    public string $disburseTanggal = '';

    public ?int $akunKasId = null;

    public ?int $payAngsuranId = null;

    public string $payPokok = '0';

    public string $payBunga = '0';

    public string $payDenda = '0';

    public string $payTanggal = '';

    public function mount(Pinjaman $pinjaman): void
    {
        $this->pinjaman = $pinjaman;
        $this->disburseTanggal = now()->toDateString();
        $this->payTanggal = now()->toDateString();
    }

    public function disburse(DisburseLoan $action): void
    {
        $this->authorize('perform-admin-mutation');
        $this->validate([
            'disburseTanggal' => ['required', 'date'],
            'akunKasId' => ['required', 'integer', 'exists:akun,id'],
        ]);

        try {
            $action->handle($this->pinjaman, $this->disburseTanggal, $this->akunKasId, auth()->user(), 'disb:'.$this->pinjaman->id, 'h');
            session()->flash('status', 'Pinjaman dicairkan.');
            $this->pinjaman->refresh();
        } catch (DomainException $e) {
            $this->addError('disburse', $e->getMessage());
        }
    }

    public function pay(SaveInstallmentPayment $save, PostingService $posting): void
    {
        $this->authorize('perform-admin-mutation');
        $this->validate([
            'payAngsuranId' => ['required', 'integer', 'exists:angsuran,id'],
            'akunKasId' => ['required', 'integer', 'exists:akun,id'],
            'payTanggal' => ['required', 'date'],
            'payPokok' => ['required', 'numeric', 'min:0'],
            'payBunga' => ['required', 'numeric', 'min:0'],
            'payDenda' => ['required', 'numeric', 'min:0'],
        ]);

        if ((float) $this->payPokok + (float) $this->payBunga + (float) $this->payDenda <= 0) {
            $this->addError('pay', 'Nominal pembayaran harus lebih dari nol.');

            return;
        }

        try {
            $draft = $save->handle([
                'tanggal' => $this->payTanggal,
                'akun_kas_id' => $this->akunKasId,
                'allocations' => [[
                    'angsuran_id' => $this->payAngsuranId,
                    'pokok' => $this->payPokok, 'bunga' => $this->payBunga, 'denda' => $this->payDenda,
                ]],
            ], auth()->user());

            $posting->post($draft, 1, auth()->user(), 'pay:'.$draft->id, 'h');

            $this->reset('payAngsuranId', 'payPokok', 'payBunga', 'payDenda');
            $this->payPokok = $this->payBunga = $this->payDenda = '0';
            session()->flash('status', 'Pembayaran angsuran diposting.');
            $this->pinjaman->refresh();
        } catch (DomainException $e) {
            $this->addError('pay', $e->getMessage());
        }
    }

    public function render()
    {
        $loan = $this->pinjaman->load(['peminjam', 'unitUsaha', 'angsuran', 'transaksiPencairan']);

        return view('livewire.lending.loans.show', [
            'title' => $loan->nomor_pinjaman,
            'loan' => $loan,
            'isDraft' => $loan->status === PinjamanStatus::Draft,
            'isAktif' => $loan->status === PinjamanStatus::Aktif,
            'akunKas' => Akun::where('is_cash', true)->where('is_active', true)->orderBy('kode_akun')->get(),
        ]);
    }
}
