<?php

namespace App\Livewire\MasterData;

use App\Domain\MasterData\SaveAccount;
use App\Livewire\Forms\AccountForm;
use App\Models\Akun;
use App\Models\SaldoAwalAkun;
use App\Models\TransaksiDetail;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Bagan akun: satu halaman dikelompokkan per jenis (tanpa paging). Akun
 * nonaktif (jarang dipakai) disembunyikan kecuali "Tampilkan nonaktif".
 */
#[Layout('components.layouts.app')]
#[Title('Akun')]
class Accounts extends Component
{
    use AuthorizesRequests;

    public AccountForm $form;

    #[Url]
    public string $q = '';

    #[Url]
    public string $jenis = '';

    #[Url]
    public bool $tampilNonaktif = false;

    public bool $showModal = false;

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

        // Akun sistem wajib (dipakai posting) & akun bersaldo tidak boleh dinonaktifkan:
        // posting akan gagal / saldonya hilang dari laporan.
        if ($akun->is_active && ($reason = $this->cannotDeactivate($akun))) {
            $this->dispatch('toast', message: "{$akun->kode_akun} {$akun->nama_akun} tidak dapat dinonaktifkan: {$reason}", type: 'error');

            return;
        }

        $akun->update(['is_active' => ! $akun->is_active]);
    }

    private function cannotDeactivate(Akun $akun): ?string
    {
        if ($akun->system_key && in_array($akun->system_key, config('accounting.system_keys', []), true)) {
            return 'akun sistem yang dipakai pencatatan otomatis.';
        }
        if (TransaksiDetail::where('akun_id', $akun->id)->exists() || SaldoAwalAkun::where('akun_id', $akun->id)->exists()) {
            return 'sudah dipakai di jurnal/saldo awal.';
        }

        return null;
    }

    public function render()
    {
        $akun = Akun::query()
            ->when($this->q, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('kode_akun', 'like', "%{$this->q}%")
                ->orWhere('nama_akun', 'like', "%{$this->q}%")))
            ->when($this->jenis, fn ($qq) => $qq->where('jenis_akun', $this->jenis))
            ->when(! $this->tampilNonaktif, fn ($qq) => $qq->where('is_active', true))
            ->orderBy('kode_akun')
            ->get();

        return view('livewire.master-data.accounts', [
            'groups' => $akun->groupBy(fn ($a) => $a->jenis_akun->value),
            'jumlahNonaktif' => Akun::where('is_active', false)->count(),
        ]);
    }
}
