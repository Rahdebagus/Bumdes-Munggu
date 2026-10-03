<?php

namespace App\Livewire\Inventory\Stocktakes;

use App\Domain\Inventory\StocktakeService;
use App\Domain\Shared\DomainException;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Opname Baru')]
class Create extends Component
{
    use AuthorizesRequests;

    public ?int $unit_usaha_id = null;

    public string $tanggal = '';

    /** @var array<int, array{produk_id: ?int, stok_fisik: string}> */
    public array $items = [];

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
        $this->items = [['produk_id' => null, 'stok_fisik' => '0']];
    }

    public function addItem(): void
    {
        $this->items[] = ['produk_id' => null, 'stok_fisik' => '0'];
    }

    public function removeItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
    }

    public function save(StocktakeService $service)
    {
        $this->authorize('perform-admin-mutation');

        $data = $this->validate([
            'unit_usaha_id' => ['required', 'integer', 'exists:unit_usaha,id'],
            'tanggal' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'integer', 'distinct', 'exists:produk,id'],
            'items.*.stok_fisik' => ['required', 'numeric', 'min:0'],
        ]);

        $periode = PeriodeAkuntansi::whereDate('tanggal_mulai', '<=', $data['tanggal'])
            ->whereDate('tanggal_selesai', '>=', $data['tanggal'])->first();
        if (! $periode) {
            $this->addError('tanggal', 'Tidak ada periode akuntansi untuk tanggal ini.');

            return null;
        }

        try {
            $opname = $service->start([
                'tanggal' => $data['tanggal'],
                'periode_id' => $periode->id,
                'unit_usaha_id' => $data['unit_usaha_id'],
                'items' => $data['items'],
            ], auth()->user());
        } catch (DomainException $e) {
            $this->addError('form', $e->getMessage());

            return null;
        }

        session()->flash('status', 'Draft opname dibuat.');

        return $this->redirectRoute('stocktakes.show', $opname, navigate: true);
    }

    public function render()
    {
        return view('livewire.inventory.stocktakes.create', [
            'units' => UnitUsaha::where('status', 'aktif')->orderBy('nama_unit')->get(),
            'produk' => Produk::where('status', 'aktif')
                ->when($this->unit_usaha_id, fn ($q) => $q->where('unit_usaha_id', $this->unit_usaha_id))
                ->orderBy('nama_produk')->get(),
        ]);
    }
}
