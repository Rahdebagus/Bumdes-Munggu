<?php

namespace App\Livewire\MasterData;

use App\Domain\MasterData\SaveUnit;
use App\Enums\StatusUmum;
use App\Livewire\Forms\UnitForm;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Unit Usaha')]
class Units extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public UnitForm $form;

    #[Url]
    public string $q = '';

    public bool $showModal = false;

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('perform-admin-mutation');
        $this->form->reset();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $this->form->setUnit(UnitUsaha::findOrFail($id));
        $this->showModal = true;
    }

    public function save(SaveUnit $action): void
    {
        $this->authorize('perform-admin-mutation');
        $data = $this->form->validate();
        $unit = $this->form->unitId ? UnitUsaha::findOrFail($this->form->unitId) : null;
        $action->handle($data, $unit);

        $this->showModal = false;
        $this->form->reset();
        session()->flash('status', 'Unit usaha disimpan.');
    }

    public function toggleStatus(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $unit = UnitUsaha::findOrFail($id);
        $unit->update([
            'status' => $unit->status === StatusUmum::Aktif ? StatusUmum::Nonaktif->value : StatusUmum::Aktif->value,
        ]);
    }

    public function render()
    {
        $units = UnitUsaha::query()
            ->when($this->q, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('kode_unit', 'like', "%{$this->q}%")
                ->orWhere('nama_unit', 'like', "%{$this->q}%")))
            ->orderBy('kode_unit')
            ->paginate(10);

        return view('livewire.master-data.units', ['units' => $units]);
    }
}
