<?php

namespace App\Livewire\Accounting\Transactions;

use App\Domain\Accounting\DocumentActions;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar Transaksi = daftar DOKUMEN/nota (penjualan, pembelian, kas,
 * angsuran, ...). Bukan jurnal: hasil double-entry ada di menu Jurnal Umum.
 */
#[Layout('components.layouts.app')]
#[Title('Daftar Transaksi')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $jenis = '';

    #[Url]
    public string $status = '';

    #[Url]
    public ?int $unitId = null;

    #[Url]
    public string $dari = '';

    #[Url]
    public string $sampai = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function resetFilter(): void
    {
        $this->reset('q', 'jenis', 'status', 'unitId', 'dari', 'sampai');
        $this->resetPage();
    }

    public function deleteDraft(int $id, DocumentActions $actions): void
    {
        $this->authorize('perform-admin-mutation');

        try {
            $trx = Transaksi::findOrFail($id);
            $nomor = $trx->nomor_transaksi;
            $actions->deleteDraft($trx, auth()->user());
            $this->dispatch('toast', message: "Draft {$nomor} dihapus.");
        } catch (DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render()
    {
        $transaksi = Transaksi::query()
            ->with(['unitUsaha', 'pihak'])
            ->when($this->q, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('nomor_transaksi', 'like', "%{$this->q}%")
                ->orWhere('keterangan', 'like', "%{$this->q}%")
                ->orWhereHas('pihak', fn ($p) => $p->where('nama', 'like', "%{$this->q}%"))))
            ->when($this->jenis, fn ($qq) => $qq->where('jenis_transaksi', $this->jenis))
            ->when($this->status, fn ($qq) => $qq->where('status', $this->status))
            ->when($this->unitId, fn ($qq) => $qq->where('unit_usaha_id', $this->unitId))
            ->when($this->dari, fn ($qq) => $qq->whereDate('tanggal', '>=', $this->dari))
            ->when($this->sampai, fn ($qq) => $qq->whereDate('tanggal', '<=', $this->sampai))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->paginate(20);

        return view('livewire.accounting.transactions.index', [
            'transaksi' => $transaksi,
            'jenisList' => TransaksiJenis::cases(),
            'statusList' => TransaksiStatus::cases(),
            'units' => UnitUsaha::orderBy('nama_unit')->get(),
        ]);
    }
}
