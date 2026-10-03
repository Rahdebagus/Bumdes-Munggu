<?php

namespace App\Livewire\FixedAssets;

use App\Models\AsetTetap;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Aset Tetap')]
class Index extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.fixed-assets.index', [
            'aset' => AsetTetap::with('unitUsaha')->orderByDesc('id')->paginate(15),
        ]);
    }
}
