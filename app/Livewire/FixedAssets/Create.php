<?php

namespace App\Livewire\FixedAssets;

use App\Domain\FixedAssets\RegisterAsset;
use App\Models\Akun;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Daftar Aset')]
class Create extends Component
{
    use AuthorizesRequests;

    public string $kode_aset = '';

    public ?int $unit_usaha_id = null;

    public string $nama_aset = '';

    public string $tanggal_perolehan = '';

    public string $harga_perolehan = '0';

    public string $nilai_residu = '0';

    public int $umur_manfaat = 12;

    public string $tanggal_mulai_susut = '';

    public string $akumulasi_awal = '0';

    public function mount(): void
    {
        $this->tanggal_perolehan = now()->toDateString();
        $this->tanggal_mulai_susut = now()->toDateString();
    }

    public function save(RegisterAsset $action)
    {
        $this->authorize('perform-admin-mutation');

        $data = $this->validate([
            'kode_aset' => ['required', 'string', 'max:20', Rule::unique('aset_tetap', 'kode_aset')],
            'unit_usaha_id' => ['nullable', 'integer', 'exists:unit_usaha,id'],
            'nama_aset' => ['required', 'string', 'max:100'],
            'tanggal_perolehan' => ['required', 'date'],
            'harga_perolehan' => ['required', 'numeric', 'min:0'],
            'nilai_residu' => ['required', 'numeric', 'min:0', 'lte:harga_perolehan'],
            'umur_manfaat' => ['required', 'integer', 'min:1', 'max:600'],
            'tanggal_mulai_susut' => ['required', 'date'],
            'akumulasi_awal' => ['required', 'numeric', 'min:0'],
        ]);

        // Akun penyusutan default dari system_key (dapat dikustom kemudian).
        $data['akun_aset_id'] = Akun::where('system_key', 'fixed_asset')->value('id');
        $data['akun_akumulasi_id'] = Akun::where('system_key', 'accumulated_depreciation')->value('id');
        $data['akun_beban_id'] = Akun::where('system_key', 'depreciation_expense')->value('id');

        $aset = $action->handle($data, auth()->user());
        session()->flash('status', 'Aset terdaftar.');

        return $this->redirectRoute('assets.show', $aset, navigate: true);
    }

    public function render()
    {
        return view('livewire.fixed-assets.create', [
            'units' => UnitUsaha::where('status', 'aktif')->orderBy('nama_unit')->get(),
        ]);
    }
}
