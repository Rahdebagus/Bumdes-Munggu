<?php

namespace App\Livewire\Inventory\Stocktakes;

use App\Models\StokOpname;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Stok Opname')]
class Index extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.inventory.stocktakes.index', [
            'opname' => StokOpname::with('unitUsaha')->orderByDesc('id')->paginate(15),
        ]);
    }
}
