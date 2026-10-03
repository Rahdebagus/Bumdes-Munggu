<?php

namespace App\Livewire\Oversight;

use App\Models\LogAktivitas;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Histori Aktivitas')]
class Activity extends Component
{
    use WithPagination;

    #[Url]
    public string $modul = '';

    public function updatingModul(): void
    {
        $this->resetPage();
    }

    public function moduls(): array
    {
        return LogAktivitas::query()->select('modul')->distinct()->orderBy('modul')->pluck('modul')->all();
    }

    public function render()
    {
        $logs = LogAktivitas::query()
            ->with('user')
            ->when($this->modul !== '', fn ($q) => $q->where('modul', $this->modul))
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.oversight.activity', ['logs' => $logs]);
    }
}
