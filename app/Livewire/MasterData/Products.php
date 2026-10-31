<?php

namespace App\Livewire\MasterData;

use App\Domain\MasterData\ImportProducts;
use App\Domain\MasterData\SaveProduct;
use App\Enums\ProdukKategori;
use App\Enums\StatusUmum;
use App\Livewire\Forms\ProductForm;
use App\Models\Produk;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Produk')]
class Products extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;
    use WithPagination;

    public ProductForm $form;

    #[Url]
    public string $q = '';

    #[Url]
    public string $kategori = '';

    #[Url]
    public bool $perluVerifikasi = false;

    public bool $showModal = false;

    public bool $showImport = false;

    public $importFile;

    /** @var array<string, mixed>|null */
    public ?array $importReport = null;

    public function updated(string $name): void
    {
        if (in_array($name, ['q', 'kategori', 'perluVerifikasi'], true)) {
            $this->resetPage();
        }
    }

    public function updatedFormKategori(string $value): void
    {
        // Produk baru: default lacak stok mengikuti kategori (jasa = tanpa stok).
        if (! $this->form->produkId && $kat = ProdukKategori::tryFrom($value)) {
            $this->form->lacak_stok = $kat->lacakStok();
        }
    }

    public function create(): void
    {
        $this->authorize('perform-admin-mutation');
        $this->form->reset();
        $this->form->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $this->form->resetValidation();
        $this->form->setProduct(Produk::findOrFail($id));
        $this->showModal = true;
    }

    public function save(SaveProduct $action): void
    {
        $this->authorize('perform-admin-mutation');
        $data = $this->form->validate();
        $produk = $this->form->produkId ? Produk::findOrFail($this->form->produkId) : null;
        if ($produk && (string) (float) $produk->harga_jual !== (string) (float) $data['harga_jual']) {
            $data['harga_jual_perlu_verifikasi'] = false; // harga dikoreksi admin
        }
        $saved = $action->handle($data, $produk);

        $this->showModal = false;
        $this->form->reset();
        $this->dispatch('toast', message: "Produk {$saved->kode_produk} — {$saved->nama_produk} disimpan.");
    }

    /** Edit harga inline (tanpa membuka modal). */
    public function updatePrice(int $id, string $field, $value): void
    {
        $this->authorize('perform-admin-mutation');
        abort_unless(in_array($field, ['harga_beli', 'harga_jual'], true), 422);

        $value = str_replace(['.', ' '], '', (string) $value);
        $value = str_replace(',', '.', $value);
        if (! is_numeric($value) || (float) $value < 0) {
            $this->dispatch('toast', message: 'Harga tidak valid.', type: 'error');

            return;
        }

        $produk = Produk::findOrFail($id);
        $update = [$field => $value];
        if ($field === 'harga_jual') {
            $update['harga_jual_perlu_verifikasi'] = false;
        }
        $produk->update($update);
        $this->dispatch('toast', message: "Harga {$produk->nama_produk} diperbarui.");
    }

    public function markVerified(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        Produk::whereKey($id)->update(['harga_jual_perlu_verifikasi' => false]);
    }

    public function toggleStatus(int $id): void
    {
        $this->authorize('perform-admin-mutation');
        $produk = Produk::findOrFail($id);
        $produk->update([
            'status' => $produk->status === StatusUmum::Aktif ? StatusUmum::Nonaktif->value : StatusUmum::Aktif->value,
        ]);
    }

    public function import(ImportProducts $service): void
    {
        $this->authorize('perform-admin-mutation');
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120']]);

        // File upload sementara tidak berekstensi asli; reader memilih format dari ekstensi.
        $copy = $this->importFile->getRealPath().'.'.strtolower($this->importFile->getClientOriginalExtension());
        copy($this->importFile->getRealPath(), $copy);

        try {
            $this->importReport = $service->import($service->read($copy));
        } catch (\Throwable $e) {
            $this->addError('importFile', 'Impor gagal: '.$e->getMessage());

            return;
        } finally {
            @unlink($copy);
        }

        $this->reset('importFile');
        $this->dispatch('toast', message: "Impor selesai: {$this->importReport['dibuat']} baru, {$this->importReport['diperbarui']} diperbarui.");
    }

    public function render()
    {
        $produk = Produk::query()
            ->with('unitUsaha')
            ->when($this->q, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('kode_produk', 'like', "%{$this->q}%")
                ->orWhere('nama_produk', 'like', "%{$this->q}%")))
            ->when($this->kategori, fn ($qq) => $qq->where('kategori', $this->kategori))
            ->when($this->perluVerifikasi, fn ($qq) => $qq->where('harga_jual_perlu_verifikasi', true))
            ->orderBy('kode_produk')
            ->paginate(25);

        return view('livewire.master-data.products', [
            'produk' => $produk,
            'units' => UnitUsaha::orderBy('nama_unit')->get(),
            'kategoriList' => ProdukKategori::cases(),
            'jumlahVerifikasi' => Produk::where('harga_jual_perlu_verifikasi', true)->count(),
        ]);
    }
}
