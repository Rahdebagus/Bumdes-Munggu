<?php

namespace App\Livewire\MasterData;

use App\Domain\MasterData\SaveProduct;
use App\Enums\StatusUmum;
use App\Livewire\Forms\ProductForm;
use App\Models\Produk;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Produk')]
class Products extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public ProductForm $form;

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
        $this->form->setProduct(Produk::findOrFail($id));
        $this->showModal = true;
    }

    public function save(SaveProduct $action): void
    {
        $this->authorize('perform-admin-mutation');
        $data = $this->form->validate();
        $produk = $this->form->produkId ? Produk::findOrFail($this->form->produkId) : null;
        $action->handle($data, $produk);

        $this->showModal = false;
        $this->form->reset();
        session()->flash('status', 'Produk disimpan.');
    }

    public function toggleStatus(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $produk = Produk::findOrFail($id);
        $produk->update([
            'status' => $produk->status === StatusUmum::Aktif ? StatusUmum::Nonaktif->value : StatusUmum::Aktif->value,
        ]);
    }

    public function render()
    {
        $produk = Produk::query()
            ->with('unitUsaha')
            ->when($this->q, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('kode_produk', 'like', "%{$this->q}%")
                ->orWhere('nama_produk', 'like', "%{$this->q}%")))
            ->orderBy('kode_produk')
            ->paginate(10);

        return view('livewire.master-data.products', [
            'produk' => $produk,
            'units' => UnitUsaha::orderBy('nama_unit')->get(),
        ]);
    }
}
