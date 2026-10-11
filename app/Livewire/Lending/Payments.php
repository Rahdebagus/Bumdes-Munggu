<?php

namespace App\Livewire\Lending;

use App\Domain\Accounting\PostingService;
use App\Domain\Lending\SaveInstallmentPayment;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Models\Akun;
use App\Models\Angsuran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Pembayaran Angsuran')]
class Payments extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $q = '';

    public ?int $akunKasId = null;

    public string $payTanggal = '';

    public ?int $payAngsuranId = null;

    public string $payPokok = '0';

    public string $payBunga = '0';

    public string $payDenda = '0';

    public function mount(): void
    {
        $this->payTanggal = now()->toDateString();
        $this->akunKasId = (int) Akun::where('system_key', 'cash')->value('id') ?: null;
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    /** Pilih angsuran untuk dibayar; prefill sisa tagihan per komponen. */
    public function startPay(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $a = Angsuran::findOrFail($id);
        $this->payAngsuranId = $id;
        $this->payPokok = Decimal::sub((string) $a->pokok_tagihan, (string) $a->pokok_dibayar);
        $this->payBunga = Decimal::sub((string) $a->bunga_tagihan, (string) $a->bunga_dibayar);
        $this->payDenda = Decimal::sub((string) $a->denda_tagihan, (string) $a->denda_dibayar);
        $this->payTanggal = now()->toDateString();
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

            $this->reset('payAngsuranId');
            $this->payPokok = $this->payBunga = $this->payDenda = '0';
            session()->flash('status', 'Pembayaran angsuran diposting.');
        } catch (DomainException $e) {
            $this->addError('pay', $e->getMessage());
        }
    }

    public function render()
    {
        $angsuran = Angsuran::query()
            ->with(['pinjaman.peminjam'])
            ->where('status', '!=', 'lunas')
            ->whereHas('pinjaman', fn ($q) => $q->where('status', 'aktif')
                ->when($this->q !== '', fn ($w) => $w->where(fn ($s) => $s
                    ->where('nomor_pinjaman', 'like', "%{$this->q}%")
                    ->orWhereHas('peminjam', fn ($p) => $p->where('nama', 'like', "%{$this->q}%")))))
            ->orderBy('tanggal_jatuh_tempo')
            ->paginate(15);

        return view('livewire.lending.payments', [
            'angsuran' => $angsuran,
            'akunKas' => Akun::where('is_cash', true)->where('is_active', true)->orderBy('kode_akun')->get(),
            'today' => now()->toDateString(),
        ]);
    }
}
