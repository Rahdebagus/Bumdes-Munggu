<?php

namespace App\Livewire\Accounting\Transactions;

use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\Transaksi;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Daftar Transaksi')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $jenis = '';

    #[Url]
    public string $status = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $transaksi = Transaksi::query()
            ->with(['unitUsaha', 'pihak'])
            ->when($this->q, fn ($qq) => $qq->where('nomor_transaksi', 'like', "%{$this->q}%"))
            ->when($this->jenis, fn ($qq) => $qq->where('jenis_transaksi', $this->jenis))
            ->when($this->status, fn ($qq) => $qq->where('status', $this->status))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->paginate(15);

        return view('livewire.accounting.transactions.index', [
            'transaksi' => $transaksi,
            'jenisList' => TransaksiJenis::cases(),
            'statusList' => TransaksiStatus::cases(),
        ]);
    }
}
