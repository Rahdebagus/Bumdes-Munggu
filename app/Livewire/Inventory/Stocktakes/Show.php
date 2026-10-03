<?php

namespace App\Livewire\Inventory\Stocktakes;

use App\Domain\Inventory\StocktakeService;
use App\Domain\Shared\DomainException;
use App\Models\StokOpname;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use AuthorizesRequests;

    public StokOpname $opname;

    public function mount(StokOpname $opname): void
    {
        $this->opname = $opname;
    }

    public function complete(StocktakeService $s): void
    {
        $this->run(fn () => $s->complete($this->opname), 'perform-admin-mutation');
    }

    public function submit(StocktakeService $s): void
    {
        $this->run(fn () => $s->submit($this->opname), 'perform-admin-mutation');
    }

    public function approve(StocktakeService $s): void
    {
        $this->run(fn () => $s->approve($this->opname, auth()->user()), 'approve-stocktake');
    }

    public function requestRevision(StocktakeService $s): void
    {
        $this->run(fn () => $s->requestRevision($this->opname), 'approve-stocktake');
    }

    public function post(StocktakeService $s): void
    {
        $this->run(fn () => $s->post($this->opname, auth()->user(), 'opn:'.$this->opname->id.':'.$this->opname->revision, 'h'), 'perform-admin-mutation');
    }

    private function run(callable $fn, string $ability): void
    {
        $this->authorize($ability);
        try {
            $fn();
            $this->opname->refresh();
            session()->flash('status', 'Opname diperbarui.');
        } catch (DomainException $e) {
            $this->addError('opname', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.inventory.stocktakes.show', [
            'title' => $this->opname->nomor_opname,
            'o' => $this->opname->load(['unitUsaha', 'details.produk']),
        ]);
    }
}
