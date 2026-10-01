<?php

namespace App\Livewire\Lending\Loans;

use App\Models\Pinjaman;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Pinjaman')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $status = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $pinjaman = Pinjaman::query()
            ->with('peminjam')
            ->when($this->q, fn ($qq) => $qq->where('nomor_pinjaman', 'like', "%{$this->q}%"))
            ->when($this->status, fn ($qq) => $qq->where('status', $this->status))
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.lending.loans.index', ['pinjaman' => $pinjaman]);
    }
}
