<?php

namespace App\Livewire\MasterData;

use App\Domain\MasterData\SaveAccount;
use App\Livewire\Forms\AccountForm;
use App\Models\Akun;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Akun')]
class Accounts extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public AccountForm $form;

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
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $this->form->setAccount(Akun::findOrFail($id));
        $this->showModal = true;
    }

    public function save(SaveAccount $action): void
    {
        $this->authorize('perform-admin-mutation');
        $data = $this->form->validate();
        $data['system_key'] = $data['system_key'] ?: null;
        $akun = $this->form->akunId ? Akun::findOrFail($this->form->akunId) : null;
        $action->handle($data, $akun);

        $this->showModal = false;
        $this->form->reset();
        session()->flash('status', 'Akun disimpan.');
    }

    public function toggleStatus(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $akun = Akun::findOrFail($id);
        $akun->update(['is_active' => ! $akun->is_active]);
    }

    public function render()
    {
        $akun = Akun::query()
            ->when($this->q, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('kode_akun', 'like', "%{$this->q}%")
                ->orWhere('nama_akun', 'like', "%{$this->q}%")
                ->orWhere('system_key', 'like', "%{$this->q}%")))
            ->when($this->jenis, fn ($qq) => $qq->where('jenis_akun', $this->jenis))
            ->orderBy('kode_akun')
            ->paginate(15);

        return view('livewire.master-data.accounts', ['akun' => $akun]);
    }
}
