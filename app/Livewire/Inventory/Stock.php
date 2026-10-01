<?php

namespace App\Livewire\Inventory;

use App\Models\MutasiStok;
use App\Models\Produk;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Persediaan')]
class Stock extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public ?int $kartu = null; // produk_id untuk kartu stok

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function showCard(int $produkId): void
    {
        $this->kartu = $produkId;
    }

    public function render()
    {
        $produk = Produk::query()
            ->with('unitUsaha')
            ->when($this->q, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('kode_produk', 'like', "%{$this->q}%")
                ->orWhere('nama_produk', 'like', "%{$this->q}%")))
            ->orderBy('nama_produk')
            ->paginate(10);

        $kartuProduk = $this->kartu ? Produk::find($this->kartu) : null;
        $mutasi = $this->kartu
            ? MutasiStok::where('produk_id', $this->kartu)->orderBy('urutan')->get()
            : collect();

        return view('livewire.inventory.stock', [
            'produk' => $produk,
            'kartuProduk' => $kartuProduk,
            'mutasi' => $mutasi,
        ]);
    }
}
