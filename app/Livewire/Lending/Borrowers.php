<?php

namespace App\Livewire\Lending;

use App\Domain\OpeningBalance\OpeningItems;
use App\Domain\Shared\Decimal;
use App\Enums\SaldoRincianJenis;
use App\Models\Pihak;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Data Peminjam')]
class Borrowers extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $peminjam = Pihak::query()
            ->where('jenis_pihak', 'peminjam')
            ->when($this->q !== '', fn ($w) => $w->where(fn ($s) => $s
                ->where('nama', 'like', "%{$this->q}%")
                ->orWhere('kode_pihak', 'like', "%{$this->q}%")))
            ->withCount([
                'pinjaman as pinjaman_aktif' => fn ($q) => $q->where('status', 'aktif'),
                'pinjaman as pinjaman_total' => fn ($q) => $q,
            ])
            ->withSum(['pinjaman as sisa_pokok_total' => fn ($q) => $q->whereIn('status', ['aktif', 'macet'])], 'sisa_pokok')
            ->orderBy('nama')
            ->paginate(15);

        // Pinjaman lama dari saldo awal ikut dihitung per nasabah.
        $lama = app(OpeningItems::class)
            ->outstanding(SaldoRincianJenis::Pinjaman, termasukLunas: true)
            ->groupBy(fn ($o) => $o['rincian']->pihak_id);
        $peminjam->getCollection()->each(function (Pihak $p) use ($lama) {
            foreach ($lama->get($p->id, []) as $o) {
                $p->pinjaman_total += 1;
                if (Decimal::isPositive($o['sisa'])) {
                    $p->pinjaman_aktif += 1;
                    $p->sisa_pokok_total = Decimal::add((string) ($p->sisa_pokok_total ?? '0'), $o['sisa']);
                }
            }
        });

        return view('livewire.lending.borrowers', ['peminjam' => $peminjam]);
    }
}
