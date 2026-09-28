<?php

namespace App\Livewire\Forms;

use App\Enums\PihakJenis;
use App\Enums\StatusUmum;
use App\Models\Pihak;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PartyForm extends Form
{
    public ?int $pihakId = null;

    public string $kode_pihak = '';

    public string $jenis_pihak = 'pelanggan';

    public string $nama = '';

    public ?string $nomor_identitas = null;

    public ?string $alamat = null;

    public ?string $telepon = null;

    public string $status = 'aktif';

    public function rules(): array
    {
        return [
            'kode_pihak' => ['required', 'string', 'max:20', Rule::unique('pihak', 'kode_pihak')->ignore($this->pihakId)],
            'jenis_pihak' => ['required', PihakJenis::rule()],
            'nama' => ['required', 'string', 'max:100'],
            'nomor_identitas' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'status' => ['required', StatusUmum::rule()],
        ];
    }

    public function setParty(Pihak $pihak): void
    {
        $this->pihakId = $pihak->id;
        $this->kode_pihak = $pihak->kode_pihak;
        $this->jenis_pihak = $pihak->jenis_pihak->value;
        $this->nama = $pihak->nama;
        $this->nomor_identitas = $pihak->nomor_identitas;
        $this->alamat = $pihak->alamat;
        $this->telepon = $pihak->telepon;
        $this->status = $pihak->status->value;
    }
}
