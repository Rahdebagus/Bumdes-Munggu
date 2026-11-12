<?php

namespace App\Livewire\MasterData;

use App\Domain\MasterData\PartyCode;
use App\Domain\MasterData\SaveParty;
use App\Enums\PihakJenis;
use App\Enums\StatusUmum;
use App\Livewire\Forms\PartyForm;
use App\Models\Pihak;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Pihak')]
class Parties extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public PartyForm $form;

    #[Url]
    public string $q = '';

    #[Url]
    public string $jenis = '';

    public bool $showModal = false;

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedJenis(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('perform-admin-mutation');
        $this->form->reset();
        $this->updatedFormJenisPihak($this->form->jenis_pihak);
        $this->showModal = true;
    }

    /** Pratinjau kode (read-only): mengikuti awalan jenis yang dipilih. */
    public function updatedFormJenisPihak(string $value): void
    {
        $jenis = PihakJenis::tryFrom($value);
        $pihak = $this->form->pihakId ? Pihak::find($this->form->pihakId) : null;

        $this->form->kode_pihak = $jenis ? app(PartyCode::class)->for($pihak, $jenis) : ($pihak->kode_pihak ?? '');
    }

    public function edit(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $this->form->setParty(Pihak::findOrFail($id));
        $this->showModal = true;
    }

    public function save(SaveParty $action): void
    {
        $this->authorize('perform-admin-mutation');
        $data = $this->form->validate();
        $pihak = $this->form->pihakId ? Pihak::findOrFail($this->form->pihakId) : null;
        $action->handle($data, $pihak);

        $this->showModal = false;
        $this->form->reset();
        session()->flash('status', 'Pihak disimpan.');
    }

    public function toggleStatus(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $pihak = Pihak::findOrFail($id);
        $pihak->update([
            'status' => $pihak->status === StatusUmum::Aktif ? StatusUmum::Nonaktif->value : StatusUmum::Aktif->value,
        ]);
    }

    public function render()
    {
        $pihak = Pihak::query()
            ->when($this->q, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('kode_pihak', 'like', "%{$this->q}%")
                ->orWhere('nama', 'like', "%{$this->q}%")))
            ->when($this->jenis, fn ($qq) => $qq->where('jenis_pihak', $this->jenis))
            ->orderBy('kode_pihak')
            ->paginate(10);

        return view('livewire.master-data.parties', ['pihak' => $pihak]);
    }
}
