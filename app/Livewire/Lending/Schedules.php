<?php

namespace App\Livewire\Lending;

use App\Models\Angsuran;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Jadwal Angsuran')]
class Schedules extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $status = '';

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $angsuran = Angsuran::query()
            ->with(['pinjaman.peminjam'])
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->whereHas('pinjaman', fn ($q) => $q->when($this->q !== '', fn ($w) => $w
                ->where('nomor_pinjaman', 'like', "%{$this->q}%")
                ->orWhereHas('peminjam', fn ($p) => $p->where('nama', 'like', "%{$this->q}%"))))
            ->orderByDesc('tanggal_jatuh_tempo')
            ->paginate(20);

        return view('livewire.lending.schedules', [
            'angsuran' => $angsuran,
            'today' => now()->toDateString(),
        ]);
    }
}
