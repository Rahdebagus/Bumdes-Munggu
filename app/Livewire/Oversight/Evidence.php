<?php

namespace App\Livewire\Oversight;

use App\Models\BuktiTransaksi;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Bukti Transaksi')]
class Evidence extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $bukti = BuktiTransaksi::query()
            ->with(['uploadedBy', 'verifiedBy', 'transaksi'])
            ->when($this->status !== '', fn ($q) => $q->where('status_verifikasi', $this->status))
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.oversight.evidence', ['bukti' => $bukti]);
    }
}
